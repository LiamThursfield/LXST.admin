<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Cms\Concerns\FindsMenus;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Menu;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetMenu extends McpTool
{
    use FindsMenus;

    protected string $name = 'get_menu';

    protected string $description = 'A menu with its items (up to three levels), in the shape update_menu takes.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The menu\'s id'),
            'slug' => $schema->string()->description('Or its slug, e.g. "header"'),
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
        return $user->can('viewAny', Menu::class);
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $menu = $this->findMenu($request);
        Gate::forUser($user)->authorize('view', $menu);
        $call->target('menu', $menu);

        return $this->result(['menu' => Snapshots::menu($menu)]);
    }
}
