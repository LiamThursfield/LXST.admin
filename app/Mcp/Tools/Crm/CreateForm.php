<?php

namespace App\Mcp\Tools\Crm;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Crm\FormFieldType;
use App\Enums\TenantModule;
use App\Http\Requests\Tenant\Admin\Crm\FormStoreRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Crm\Form;
use App\Models\User;
use App\Services\Crm\FormWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateForm extends McpTool
{
    public const array ATTRIBUTES = [
        'name', 'slug', 'email_recipients', 'marketing_email', 'marketing_sms', 'marketing_telephone',
        'captcha_enabled', 'submit_button_text', 'redirect_url', 'success_message', 'fields',
    ];

    protected string $name = 'create_form';

    protected string $description = 'Creates a CRM form. Forms go live straight away: websites fetch them by slug and post answers to them.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'slug' => $schema->string()->description('How websites address the form, e.g. "contact"'),
            'email_recipients' => $schema->array()->items($schema->string())->description('Email addresses told about each submission'),
            'marketing_email' => $schema->boolean()->description('Ask for consent to marketing by email'),
            'marketing_sms' => $schema->boolean(),
            'marketing_telephone' => $schema->boolean(),
            'captcha_enabled' => $schema->boolean()->description('Needs a captcha set up in the site\'s integrations'),
            'submit_button_text' => $schema->string(),
            'redirect_url' => $schema->string()->description('Where the website sends people after submitting'),
            'success_message' => $schema->string(),
            'fields' => $schema->array()->items($schema->object())->description(self::fieldsHelp()),
        ];
    }

    public static function fieldsHelp(): string
    {
        $types = implode(', ', array_map(fn (FormFieldType $type) => $type->value, FormFieldType::cases()));

        return 'Fields as objects: {"type", "name", "slug" (optional, made from the name), "is_required", "settings": {"max_length", "rows", "min", "max", "options", "default"}}. Types: '.$types.'. '
            .'Settings by type: text max_length; textarea max_length, rows; number min, max; select options (required, a list of {"label", "value"} where each value is unique and alpha-dash, e.g. {"label": "Web design", "value": "web-design"}) and default (optional, one of the option values). '
            .'A select answer must be one of its option values. The crm_* types fill in the contact\'s details and can each be used once.';
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
        return $user->can('create', Form::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $validated = AdminRequest::validate(FormStoreRequest::class, Arr::only($request->all(), self::ATTRIBUTES), $user);

        $form = app(FormWriter::class)->create($validated);
        $snapshot = Snapshots::form($form->fresh() ?? $form);

        $call->target('form', $form);
        $call->changed(null, $snapshot);

        return $this->result(['form' => $snapshot]);
    }
}
