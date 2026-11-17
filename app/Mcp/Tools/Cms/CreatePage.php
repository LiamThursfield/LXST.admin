<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Http\Requests\Tenant\Admin\Cms\PageStoreRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Cms\Concerns\SavesPages;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Page;
use App\Models\User;
use App\Services\Cms\PageWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreatePage extends McpTool
{
    use SavesPages;

    public const string CONTENT_HELP = 'Content keyed by the template\'s field slugs (see get_template, and list_field_types for each type\'s value). Rich text can be sent as HTML.';

    public const string METADATA_HELP = 'SEO metadata: title, description, keywords, canonical, og_title, og_description, og_type, og_url, og_image_file_id.';

    protected string $name = 'create_page';

    protected string $description = 'Creates a page as a draft, which websites can\'t see until a person publishes it. The URL is the parent\'s URL plus the slug; a top-level page with no slug is the home page.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The page\'s name in the admin'),
            'slug' => $schema->string()->description('Lower case letters, numbers and hyphens. Leave out for the home page.'),
            'parent_id' => $schema->integer()->description('The page this one sits under, if any'),
            'template_id' => $schema->integer()->required()->description('A page template (see list_templates)'),
            'layout_id' => $schema->integer()->required()->description('See list_layouts'),
            'metadata' => $schema->object()->description(self::METADATA_HELP),
            'content' => $schema->object()->description(self::CONTENT_HELP),
        ];
    }

    public function ability(): ApiTokenAbility
    {
        return ApiTokenAbility::McpCmsContent;
    }

    protected function module(): TenantModule
    {
        return TenantModule::Cms;
    }

    protected function userCan(User $user): bool
    {
        return $user->can('create', Page::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $input = [
            'template_id' => $request->get('template_id'),
            'layout_id' => $request->get('layout_id'),
            'parent_id' => $request->get('parent_id'),
            'name' => $request->get('name'),
            'slug' => $request->get('slug'),
            // Always a draft: publishing is for a person, or a token with mcp:cms:publish
            'is_enabled' => false,
            'published_at' => null,
            'expired_at' => null,
            'metadata' => (array) ($request->get('metadata') ?? []),
            'content' => $this->contentInput($request->get('template_id'), (array) ($request->get('content') ?? [])),
        ];

        $page = app(PageWriter::class)->create(AdminRequest::validate(PageStoreRequest::class, $input, $user));
        $snapshot = Snapshots::page($page->fresh() ?? $page, $user);

        $call->target('page', $page);
        $call->changed(null, $snapshot);

        return $this->result(['page' => $snapshot]);
    }
}
