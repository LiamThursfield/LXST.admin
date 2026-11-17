<?php

namespace App\Mcp\Tools\Crm;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Http\Requests\Tenant\Admin\Crm\FormUpdateRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Concerns\KeepsBuilderFields;
use App\Mcp\Tools\Crm\Concerns\FindsForms;
use App\Mcp\Tools\McpTool;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\User;
use App\Services\Crm\FormWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateForm extends McpTool
{
    use FindsForms;
    use KeepsBuilderFields;

    protected string $name = 'update_form';

    protected string $description = 'Changes a CRM form. Only what you send changes; "fields", when sent, replaces the list: send every existing field (by id, or by slug) in its new order, as removing fields isn\'t possible through MCP. Changes go live straight away.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string(),
            'slug' => $schema->string(),
            'email_recipients' => $schema->array()->items($schema->string()),
            'marketing_email' => $schema->boolean(),
            'marketing_sms' => $schema->boolean(),
            'marketing_telephone' => $schema->boolean(),
            'captcha_enabled' => $schema->boolean(),
            'submit_button_text' => $schema->string(),
            'redirect_url' => $schema->string(),
            'success_message' => $schema->string(),
            'fields' => $schema->array()->items($schema->object())->description(CreateForm::fieldsHelp().' Include "id" (or the same slug) for existing fields.'),
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
        return $user->can('viewAny', Form::class) && $user->can('create', Form::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $form = $this->findForm(new Request(['id' => $request->get('id')]));
        $call->target('form', $form);

        $before = Snapshots::form($form);
        $input = [...Arr::except($before, 'id'), ...Arr::only($request->all(), CreateForm::ATTRIBUTES)];

        $form->loadMissing('fields');
        $input['fields'] = $this->withExistingIds(
            array_values((array) ($input['fields'] ?? [])),
            $form->fields->mapWithKeys(fn (FormField $field) => [$field->slug => $field->id])->all(),
        );
        $this->refuseRemovedFields($form->fields->mapWithKeys(fn (FormField $field) => [$field->id => $field->slug])->all(), $input['fields']);

        app(FormWriter::class)->update($form, AdminRequest::validate(FormUpdateRequest::class, $input, $user, ['form' => $form]));

        $after = Snapshots::form($form->fresh() ?? $form);
        $call->changed($before, $after);

        return $this->result(['form' => $after]);
    }
}
