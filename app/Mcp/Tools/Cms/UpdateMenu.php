<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Http\Requests\Tenant\Admin\Cms\MenuUpdateRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Cms\Concerns\FindsMenus;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Menu;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateMenu extends McpTool
{
    use FindsMenus;

    protected string $name = 'update_menu';

    protected string $description = 'Changes a menu. Only what you send changes; "menu_items", when sent, replaces every item. Changes go live straight away.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string(),
            'slug' => $schema->string(),
            'menu_items' => $schema->array()->items($schema->object())->description(CreateMenu::ITEMS_HELP),
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
        return $user->can('viewAny', Menu::class) && $user->can('create', Menu::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $menu = $this->findMenu(new Request(['id' => $request->get('id')]));
        $call->target('menu', $menu);

        $before = Snapshots::menu($menu);
        $input = [...Arr::except($before, 'id'), ...Arr::only($request->all(), ['name', 'slug', 'menu_items'])];

        $validated = AdminRequest::validate(MenuUpdateRequest::class, $input, $user, ['menu' => $menu]);

        // As the admin saves it
        $menu->update($validated->menuAttributes());

        $after = Snapshots::menu($menu->fresh() ?? $menu);
        $call->changed($before, $after);

        return $this->result(['menu' => $after]);
    }
}
