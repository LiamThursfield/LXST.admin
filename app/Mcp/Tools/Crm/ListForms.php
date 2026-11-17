<?php

namespace App\Mcp\Tools\Crm;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Crm\Form;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListForms extends McpTool
{
    protected string $name = 'list_forms';

    protected string $description = 'The site\'s CRM forms. Websites fetch a form by its slug (GET /api/v1/crm/forms/{slug}) and post answers to it.';

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
        return $this->result([
            'forms' => Form::query()->withCount('fields')->orderBy('name')->get()
                ->map(fn (Form $form) => [
                    'id' => $form->id,
                    'name' => $form->name,
                    'slug' => $form->slug,
                    'fields_count' => $form->fields_count,
                ])
                ->all(),
        ]);
    }
}
