<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Cms\PublishStatus;
use App\Enums\TenantModule;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Page;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListPages extends McpTool
{
    protected string $name = 'list_pages';

    protected string $description = 'The site\'s pages, in URL order, with their status (draft, published, scheduled or expired) and template. Use get_page for a page\'s content.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Only pages whose name or URL contains this'),
        ];
    }

    public function ability(): ApiTokenAbility
    {
        return ApiTokenAbility::McpCmsRead;
    }

    protected function module(): TenantModule
    {
        return TenantModule::Cms;
    }

    protected function userCan(User $user): bool
    {
        return $user->can('viewAny', Page::class);
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = $validated['search'] ?? null;

        return $this->result([
            'pages' => Page::query()
                ->with('url', 'template:id,slug')
                ->get()
                ->filter(fn (Page $page) => $search === null
                    || str_contains(strtolower($page->name), strtolower($search))
                    || str_contains(strtolower($page->url->url_full ?? ''), strtolower($search)))
                ->sortBy(fn (Page $page) => $page->url->url_full ?? '')
                ->map(fn (Page $page) => [
                    'id' => $page->id,
                    'name' => $page->name,
                    'url' => $page->url?->url_full,
                    'status' => ($page->url?->status() ?? PublishStatus::Draft)->value,
                    'template' => $page->template->slug,
                    'parent_id' => $page->parent_id,
                ])
                ->values()
                ->all(),
        ]);
    }
}
