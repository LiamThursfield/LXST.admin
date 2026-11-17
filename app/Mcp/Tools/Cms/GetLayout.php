<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Cms\Concerns\FindsLayouts;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Layout;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetLayout extends McpTool
{
    use FindsLayouts;

    protected string $name = 'get_layout';

    protected string $description = 'A layout with its template, how many pages use it, and its content (keyed by its layout template\'s field slugs), in the shape update_layout takes.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The layout\'s id'),
            'slug' => $schema->string()->description('Or its slug'),
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
        return $user->can('viewAny', Layout::class);
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $layout = $this->findLayout($request);
        Gate::forUser($user)->authorize('view', $layout);
        $call->target('layout', $layout);

        return $this->result(['layout' => Snapshots::layout($layout)]);
    }
}
