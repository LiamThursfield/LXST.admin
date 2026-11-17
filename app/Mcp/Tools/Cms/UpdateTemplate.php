<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Http\Requests\Tenant\Admin\Cms\TemplateUpdateRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Support\ToolRefused;
use App\Mcp\Tools\Cms\Concerns\FindsTemplates;
use App\Mcp\Tools\Concerns\KeepsBuilderFields;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\TemplateSection;
use App\Models\User;
use App\Services\Cms\TemplateWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateTemplate extends McpTool
{
    use FindsTemplates;
    use KeepsBuilderFields;

    protected string $name = 'update_template';

    protected string $description = 'Changes a template. Only what you send changes. "fields" and "sections", when sent, replace those lists: send every existing field (by id, or by slug) in its new order, as removing fields isn\'t possible through MCP. Changes go live straight away for every page using the template.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'type' => $schema->string()->enum(array_map(fn (TemplateType $type) => $type->value, TemplateType::cases())),
            'name' => $schema->string(),
            'slug' => $schema->string(),
            'description' => $schema->string(),
            'fields' => $schema->array()->items($schema->object())->description(CreateTemplate::FIELDS_HELP.' Include "id" (or the same slug) for existing fields.'),
            'sections' => $schema->array()->items($schema->object())->description(CreateTemplate::SECTIONS_HELP.' Include "id" (or the same name) for existing sections.'),
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
        return $user->can('viewAny', Template::class) && $user->can('create', Template::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $template = $this->findTemplate(new Request(['id' => $request->get('id')]));
        $call->target('template', $template);

        $before = Snapshots::template($template);
        $input = [
            ...Arr::except($before, 'id'),
            ...Arr::only($request->all(), ['type', 'name', 'slug', 'description', 'fields', 'sections']),
        ];

        $input = $this->keepingExisting($template, $input);

        app(TemplateWriter::class)->update($template, AdminRequest::validate(TemplateUpdateRequest::class, $input, $user, ['template' => $template]));

        $after = Snapshots::template($template->fresh() ?? $template);
        $call->changed($before, $after);

        return $this->result(['template' => $after]);
    }

    /**
     * Matches sent fields and sections to existing ones by slug and name, then refuses the update
     * if it would remove any
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function keepingExisting(Template $template, array $input): array
    {
        $template->loadMissing('fields', 'sections');
        $fieldIds = $template->fields->mapWithKeys(fn (TemplateField $field) => [$field->slug => $field->id])->all();
        $sectionIds = $template->sections->mapWithKeys(fn (TemplateSection $section) => [$section->name => $section->id])->all();

        $fields = $this->withExistingIds(array_values((array) ($input['fields'] ?? [])), $fieldIds);
        $sections = array_map(function (mixed $section) use ($fieldIds, $sectionIds) {
            if (! is_array($section)) {
                return $section;
            }

            if (blank($section['id'] ?? null) && is_string($section['name'] ?? null) && isset($sectionIds[$section['name']])) {
                $section['id'] = $sectionIds[$section['name']];
            }

            $section['fields'] = $this->withExistingIds(array_values((array) ($section['fields'] ?? [])), $fieldIds);

            return $section;
        }, array_values((array) ($input['sections'] ?? [])));

        $allFields = $fields;

        foreach ($sections as $section) {
            if (is_array($section)) {
                array_push($allFields, ...$section['fields']);
            }
        }

        $this->refuseRemovedFields(
            $template->fields->mapWithKeys(fn (TemplateField $field) => [$field->id => $field->slug])->all(),
            $allFields,
        );

        $keptSectionIds = collect($sections)->map(fn (mixed $section) => is_array($section) && is_numeric($section['id'] ?? null) ? (int) $section['id'] : null)->filter()->all();
        $removedSections = $template->sections->whereNotIn('id', $keptSectionIds)->pluck('name')->all();

        if ($removedSections !== []) {
            throw new ToolRefused(__('mcp.errors.removes_sections', ['sections' => implode(', ', $removedSections)]));
        }

        return [...$input, 'fields' => $fields, 'sections' => $sections];
    }
}
