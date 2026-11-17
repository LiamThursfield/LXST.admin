<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Http\Requests\Tenant\Admin\Cms\TemplateStoreRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Template;
use App\Models\User;
use App\Services\Cms\TemplateWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateTemplate extends McpTool
{
    public const string FIELDS_HELP = 'Fields as objects: {"type", "name", "slug" (optional, made from the name), "description", "is_required", "settings": {…}}. See list_field_types for each type\'s settings.';

    public const string SECTIONS_HELP = 'Optional groups of fields in the admin editor: {"name", "description", "is_collapsed", "fields": [...]}.';

    protected string $name = 'create_template';

    protected string $description = 'Creates a template: the fields a type of page, layout, component or repeater row has. Templates go live straight away: pages using one show its fields in the admin, and websites receive content keyed by its field slugs.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(array_map(fn (TemplateType $type) => $type->value, TemplateType::cases()))->required(),
            'name' => $schema->string()->required(),
            'slug' => $schema->string()->description('Unique per type. Made from the name if left out.'),
            'description' => $schema->string(),
            'fields' => $schema->array()->items($schema->object())->description(self::FIELDS_HELP),
            'sections' => $schema->array()->items($schema->object())->description(self::SECTIONS_HELP),
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
        return $user->can('create', Template::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $input = Arr::only($request->all(), ['type', 'name', 'slug', 'description', 'fields', 'sections']);

        $template = app(TemplateWriter::class)->create(AdminRequest::validate(TemplateStoreRequest::class, $input, $user));
        $snapshot = Snapshots::template($template->fresh() ?? $template);

        $call->target('template', $template);
        $call->changed(null, $snapshot);

        return $this->result(['template' => $snapshot]);
    }
}
