<?php

namespace App\Mcp\Tools\Crm;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Crm\Concerns\FindsForms;
use App\Mcp\Tools\McpTool;
use App\Models\Crm\Form;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetForm extends McpTool
{
    use FindsForms;

    protected string $name = 'get_form';

    protected string $description = 'A CRM form with its settings and fields, in the shape update_form takes. Submissions aren\'t available through MCP.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The form\'s id'),
            'slug' => $schema->string()->description('Or its slug, e.g. "contact"'),
        ];
    }

    public function ability(): ApiTokenAbility
    {
        return ApiTokenAbility::McpCrmForms;
    }

    protected function module(): TenantModule
    {
        return TenantModule::Crm;
    }

    protected function userCan(User $user): bool
    {
        return $user->can('viewAny', Form::class);
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $form = $this->findForm($request);
        Gate::forUser($user)->authorize('view', $form);
        $call->target('form', $form);

        return $this->result(['form' => Snapshots::form($form)]);
    }
}
