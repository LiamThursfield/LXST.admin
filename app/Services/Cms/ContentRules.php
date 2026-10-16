<?php

namespace App\Services\Cms;

use App\Enums\Cms\TemplateFieldType;
use App\Enums\TenantModule;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Crm\Form;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Builds the validation rules for content (keyed by field slug, under `content.`) from a
 * template's fields, respecting each field's required flag and settings
 */
class ContentRules
{
    /**
     * Text fields with no maximum set still fit in a normal string column's worth
     */
    public const int DEFAULT_TEXT_MAX_LENGTH = 255;

    /**
     * Text areas with no maximum set are kept to a sensible size
     */
    public const int DEFAULT_TEXTAREA_MAX_LENGTH = 65535;

    /**
     * Component and repeater templates, with their fields, by id
     *
     * @var array<int, Template|null>
     */
    protected array $nestedTemplates = [];

    public function __construct(protected RichText $richText) {}

    /**
     * @param  array<string, mixed>  $data  The request data, to tell whether an optional component is being filled in
     * @return array<string, list<mixed>>
     */
    public function rules(Template $template, string $prefix = 'content', array $data = []): array
    {
        return [$prefix => ['nullable', 'array'], ...$this->fieldsRules($template, $prefix, $data)];
    }

    /**
     * The template a component or repeater field uses, with its fields
     */
    public function nestedTemplate(TemplateField $field): ?Template
    {
        $id = $field->settings?->template_id;

        if ($id === null) {
            return null;
        }

        return $this->nestedTemplates[$id] ??= Template::query()->with('fields')->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<mixed>>
     */
    protected function fieldsRules(Template $template, string $prefix, array $data, bool $optional = false): array
    {
        $rules = [];

        foreach ($template->fields as $field) {
            $rules = [...$rules, ...$this->fieldRules($field, "{$prefix}.{$field->slug}", $data, $optional)];
        }

        return $rules;
    }

    /**
     * The fields' names, so messages say e.g. "The Hero title field is required". Fields inside
     * components and repeater rows are named after their parent, e.g. "Slides Title".
     *
     * @return array<string, string>
     */
    public function attributes(Template $template, string $prefix = 'content', string $parentName = ''): array
    {
        $attributes = [];

        foreach ($template->fields as $field) {
            $key = "{$prefix}.{$field->slug}";
            $name = trim("{$parentName} {$field->name}");

            $attributes[$key] = $name;
            // Image fields are validated by their file and alt text
            $attributes["{$key}.file_id"] = $name;
            $attributes["{$key}.alt"] = __('cms.content.image.alt_attribute', ['field' => $name]);

            $nested = in_array($field->type, [TemplateFieldType::Component, TemplateFieldType::Repeater], true)
                ? $this->nestedTemplate($field)
                : null;

            if ($nested !== null) {
                $nestedPrefix = $field->type === TemplateFieldType::Repeater ? "{$key}.*" : $key;
                $attributes = [...$attributes, ...$this->attributes($nested, $nestedPrefix, $name)];
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $optional  Whether the field is inside an optional component that's been left empty,
     *                          so none of its fields are required
     * @return array<string, list<mixed>>
     */
    protected function fieldRules(TemplateField $field, string $key, array $data, bool $optional = false): array
    {
        $required = $field->is_required && ! $optional ? 'required' : 'nullable';
        $settings = $field->settings;

        return match ($field->type) {
            TemplateFieldType::Text => [$key => [$required, 'string', 'max:'.($settings->max_length ?? self::DEFAULT_TEXT_MAX_LENGTH)]],
            TemplateFieldType::Textarea => [$key => [$required, 'string', 'max:'.($settings->max_length ?? self::DEFAULT_TEXTAREA_MAX_LENGTH)]],
            TemplateFieldType::Number => [$key => array_values(array_filter([
                $required,
                'numeric',
                $settings?->min !== null ? 'min:'.$settings->min : null,
                $settings?->max !== null ? 'max:'.$settings->max : null,
            ]))],
            // Sent as {json, html}; only the JSON is used, and the HTML is rebuilt from it
            TemplateFieldType::Wysiwyg => [$key => [$required, 'array', $this->richTextRule($field)]],
            // Sent as {file_id, alt}
            TemplateFieldType::Image => [
                $key => ['nullable', 'array'],
                "{$key}.file_id" => [
                    $required,
                    'integer',
                    Rule::exists(File::class, 'id')->where(fn (Builder $query) => $query->where('mime_type', 'like', 'image/%')),
                ],
                "{$key}.alt" => ['nullable', 'string', 'max:255'],
            ],
            // An object of the component template's fields. An optional component only needs its
            // required fields once something in it has been filled in.
            TemplateFieldType::Component => $this->nestedRules($field, $key, function (Template $template) use ($field, $key, $data, $required) {
                $isEmpty = ! $field->is_required && ! $this->hasValues(data_get($data, $key));

                return [$key => [$required, 'array'], ...$this->fieldsRules($template, $key, $data, $isEmpty)];
            }),
            // A list of rows, each an object of the repeater template's fields
            TemplateFieldType::Repeater => $this->nestedRules($field, $key, fn (Template $template) => [
                $key => array_values(array_filter([
                    $field->is_required && ! $optional ? 'required' : 'nullable',
                    'array',
                    'list',
                    $settings?->min_items !== null ? 'min:'.$settings->min_items : null,
                    $settings?->max_items !== null ? 'max:'.$settings->max_items : null,
                ])),
                "{$key}.*" => ['array'],
                ...$this->fieldsRules($template, "{$key}.*", $data, $optional),
            ]),
            // A menu's id
            TemplateFieldType::CmsMenu => [$key => [$required, 'integer', Rule::exists('cms_menus', 'id')]],
            // A CRM form's id. Without the CRM module the field is hidden, so its value is kept as it is.
            TemplateFieldType::CrmForm => $this->crmEnabled()
                ? [$key => [$required, 'integer', Rule::exists(Form::class, 'id')->whereNull('deleted_at')]]
                : [$key => ['nullable', 'integer']],
            // An organisation unit's id, of the type set in the field's settings if there is one
            TemplateFieldType::CrmOrganisationUnit => $this->crmEnabled()
                ? [$key => [
                    $required,
                    'integer',
                    Rule::exists(OrganisationUnit::class, 'id')->when(
                        $settings?->organisation_unit_type,
                        fn ($rule, string $type) => $rule->where('type', $type),
                    ),
                ]]
                : [$key => ['nullable', 'integer']],
        };
    }

    /**
     * Rules for a component or repeater, or a failure if its template has gone
     *
     * @param  Closure(Template): array<string, list<mixed>>  $rules
     * @return array<string, list<mixed>>
     */
    protected function nestedRules(TemplateField $field, string $key, Closure $rules): array
    {
        $template = $this->nestedTemplate($field);

        return $template === null ? [$key => ['prohibited']] : $rules($template);
    }

    /**
     * CRM fields only offer (and check) forms and units while the tenant has the CRM module
     */
    protected function crmEnabled(): bool
    {
        return tenant()?->hasModule(TenantModule::Crm) ?? false;
    }

    /**
     * Whether a component's value has anything filled in
     */
    public function hasValues(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasValues($item)) {
                    return true;
                }
            }

            return false;
        }

        return $value !== null && $value !== '' && $value !== false;
    }

    /**
     * The document must be one the editor could have produced, with safe URLs, and not be blank
     * when the field is required
     */
    protected function richTextRule(TemplateField $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            $document = is_array($value) ? ($value['json'] ?? null) : null;

            foreach ($this->richText->problems($document) as $problem) {
                $fail($problem);
            }

            if ($field->is_required && is_array($document) && $this->richText->isEmpty($document)) {
                $fail(__('validation.required', ['attribute' => $field->name]));
            }
        };
    }
}
