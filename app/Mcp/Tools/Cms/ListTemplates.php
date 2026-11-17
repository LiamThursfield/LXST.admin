<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Template;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTemplates extends McpTool
{
    protected string $name = 'list_templates';

    protected string $description = 'The site\'s templates, which define the fields of pages (type "page"), layouts, components and repeater rows. Use get_template for a template\'s fields.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(array_map(fn (TemplateType $type) => $type->value, TemplateType::cases()))
                ->description('Only templates of this type'),
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
        return $user->can('viewAny', Template::class);
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $validated = $request->validate(['type' => ['nullable', Rule::enum(TemplateType::class)]]);

        return $this->result([
            'templates' => Template::query()
                ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
                ->withCount('fields')
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(fn (Template $template) => [
                    'id' => $template->id,
                    'type' => $template->type->value,
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'description' => $template->description,
                    'fields_count' => $template->fields_count,
                ])
                ->all(),
        ]);
    }
}
