<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Cms\Concerns\FindsPages;
use App\Mcp\Tools\Cms\Concerns\SavesPages;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Page;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdatePage extends McpTool
{
    use FindsPages;
    use SavesPages;

    protected string $name = 'update_page';

    protected string $description = 'Changes a draft page. Only what you send changes; content and metadata are merged field by field (send a field as null to clear it). Live pages can\'t be edited without the mcp:cms:publish ability, and publishing is done with publish_page.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string(),
            'slug' => $schema->string(),
            'parent_id' => $schema->integer()->nullable()->description('Null to make it a top-level page'),
            'template_id' => $schema->integer()->description('Changing the template drops content for fields the new one doesn\'t have'),
            'layout_id' => $schema->integer(),
            'metadata' => $schema->object()->description(CreatePage::METADATA_HELP),
            'content' => $schema->object()->description(CreatePage::CONTENT_HELP),
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
        return $user->can('viewAny', Page::class) && $user->can('create', Page::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $page = $this->findPage(new Request(['id' => $request->get('id')]));
        $call->target('page', $page);
        $this->refuseUnlessDraft($page);

        $current = Snapshots::pageInput($page, $user);
        $sent = $request->all();

        $input = [
            ...$current,
            ...Arr::only($sent, ['name', 'slug', 'parent_id', 'template_id', 'layout_id']),
            'metadata' => [...$current['metadata'], ...(array) ($sent['metadata'] ?? [])],
            'content' => [...$current['content'], ...(array) ($sent['content'] ?? [])],
        ];
        $input['content'] = $this->contentInput($input['template_id'], $input['content']);

        $this->refuseMovingLivePages($page, $input);

        return $this->result(['page' => $this->savePage($page, $input, $user, $call)]);
    }
}
