<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Http\Requests\Tenant\Admin\Cms\MenuStoreRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Menu;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateMenu extends McpTool
{
    public const string ITEMS_HELP = 'Items as objects: {"label", "link_type": "page" with "page_id", or "url" with "url", "target": "_self"|"_blank", "rel", "custom_class", "children": [...]}. Up to three levels.';

    protected string $name = 'create_menu';

    protected string $description = 'Creates a menu. Menus go live straight away: websites receive them by slug.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'slug' => $schema->string()->description('Made from the name if left out'),
            'menu_items' => $schema->array()->items($schema->object())->description(self::ITEMS_HELP),
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
        return $user->can('create', Menu::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $validated = AdminRequest::validate(MenuStoreRequest::class, Arr::only($request->all(), ['name', 'slug', 'menu_items']), $user);

        // As the admin saves it
        $menu = Menu::create($validated->menuAttributes());
        $snapshot = Snapshots::menu($menu->fresh() ?? $menu);

        $call->target('menu', $menu);
        $call->changed(null, $snapshot);

        return $this->result(['menu' => $snapshot]);
    }
}
