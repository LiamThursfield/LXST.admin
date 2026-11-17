<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Menu;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListMenus extends McpTool
{
    protected string $name = 'list_menus';

    protected string $description = 'The site\'s menus. Websites fetch a menu by its slug (GET /api/v1/cms/menus/{slug}), and every menu comes with GET /api/v1/site.';

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
        return $user->can('viewAny', Menu::class);
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        return $this->result([
            'menus' => Menu::query()->orderBy('name')->get()
                ->map(fn (Menu $menu) => [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                    'items_count' => count($menu->menu_items ?? []),
                ])
                ->all(),
        ]);
    }
}
