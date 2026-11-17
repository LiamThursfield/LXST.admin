<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Cms\Concerns\FindsPages;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Page;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetPage extends McpTool
{
    use FindsPages;

    protected string $name = 'get_page';

    protected string $description = 'A page with its URL, status, metadata and content (keyed by its template\'s field slugs). Publishing times are on the token owner\'s clock.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The page\'s id'),
            'url' => $schema->string()->description('Or its full URL on the site, e.g. "/about/team" ("/" is the home page)'),
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
        $page = $this->findPage($request);
        Gate::forUser($user)->authorize('view', $page);
        $call->target('page', $page);

        return $this->result(['page' => Snapshots::page($page, $user)]);
    }
}
