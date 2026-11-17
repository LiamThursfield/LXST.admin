<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Layout;
use App\Models\User;
use App\Services\Authorisation\Enums\CmsPermission;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListLayouts extends McpTool
{
    protected string $name = 'list_layouts';

    protected string $description = 'The site\'s layouts. Every page has one (its layout_id), which holds content shared by the pages using it.';

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
        return $user->can(CmsPermission::ViewContent) || $user->can(CmsPermission::ViewStructure);
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        return $this->result([
            'layouts' => Layout::query()->with('template:id,slug')->orderBy('name')->get()
                ->map(fn (Layout $layout) => [
                    'id' => $layout->id,
                    'name' => $layout->name,
                    'slug' => $layout->slug,
                    'template' => $layout->template->slug,
                ])
                ->all(),
        ]);
    }
}
