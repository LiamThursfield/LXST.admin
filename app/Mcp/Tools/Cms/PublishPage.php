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
class PublishPage extends McpTool
{
    use FindsPages;
    use SavesPages;

    protected string $name = 'publish_page';

    protected string $description = 'Publishes a page, so websites can see it (within any publishing window set in the admin). Its content must be valid, as for saving it in the admin.';

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

        $input = [...Snapshots::pageInput($page, $user), 'is_enabled' => true];

        return $this->result(['page' => $this->savePage($page, $input, $user, $call)]);
    }
}
