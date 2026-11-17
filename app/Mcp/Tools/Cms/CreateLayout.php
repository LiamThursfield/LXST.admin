<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Http\Requests\Tenant\Admin\Cms\LayoutStoreRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\ContentInput;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Layout;
use App\Models\User;
use App\Services\Cms\LayoutWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateLayout extends McpTool
{
    public const string CONTENT_HELP = 'Content keyed by the layout template\'s field slugs (see get_template, and list_field_types for each type\'s value). Rich text can be sent as HTML.';

    protected string $name = 'create_layout';

    protected string $description = 'Creates a layout: content shared by every page that uses it (e.g. a footer), defined by a template of type "layout". Layouts have no draft state, so their content is live for those pages straight away.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'slug' => $schema->string()->description('Made from the name if left out'),
            'template_id' => $schema->integer()->required()->description('A layout template (list_templates with type "layout")'),
            'content' => $schema->object()->description(self::CONTENT_HELP),
        ];
    }

    public function ability(): ApiTokenAbility
    {
        return ApiTokenAbility::McpCmsStructure;
    }

    protected function module(): TenantModule
    {
        return TenantModule::Cms;
    }

    protected function userCan(User $user): bool
    {
        return $user->can('create', Layout::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $input = [
            'template_id' => $request->get('template_id'),
            'name' => $request->get('name'),
            'slug' => $request->get('slug'),
            'content' => app(ContentInput::class)->forTemplate(TemplateType::Layout, $request->get('template_id'), (array) ($request->get('content') ?? [])),
        ];

        $layout = app(LayoutWriter::class)->create(AdminRequest::validate(LayoutStoreRequest::class, $input, $user));
        $snapshot = Snapshots::layout($layout->fresh() ?? $layout);

        $call->target('layout', $layout);
        $call->changed(null, $snapshot);

        return $this->result(['layout' => $snapshot]);
    }
}
