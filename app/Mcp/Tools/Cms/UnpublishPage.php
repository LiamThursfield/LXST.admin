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
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UnpublishPage extends McpTool
{
    use FindsPages;
    use SavesPages;

    protected string $name = 'unpublish_page';

    protected string $description = 'Unpublishes a page, making it a draft that websites can\'t see.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
        ];
    }

    public function ability(): ApiTokenAbility
    {
        return ApiTokenAbility::McpCmsPublish;
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

        $input = [...Snapshots::pageInput($page, $user), 'is_enabled' => false];

        return $this->result(['page' => $this->savePage($page, $input, $user, $call)]);
    }
}
