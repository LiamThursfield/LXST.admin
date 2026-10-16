<?php

namespace App\Http\Requests\Tenant\Admin\Cms\Concerns;

use App\Data\Cms\TemplateFieldSettings;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Template;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesTemplate
{
    /**
     * Fill in a blank template slug from its name, and blank field slugs from the field names,
     * so they can be checked for duplicates
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && is_string($this->input('name'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }

        $fields = $this->input('fields');

        if (! is_array($fields)) {
            return;
        }

        $this->merge([
            'fields' => array_map(function (mixed $field) {
                if (is_array($field) && blank($field['slug'] ?? null) && is_string($field['name'] ?? null)) {
                    $field['slug'] = Str::slug($field['name'], '_');
                }

                return $field;
            }, $fields),
        ]);
    }

    /**
     * The shared validation rules for creating or updating a template.
     *
     * @return array<string, mixed>
     */
    protected function templateRules(?Template $template = null): array
    {
        $type = TemplateType::tryFrom((string) $this->input('type'));

        return [
            'type' => [
                'required',
                Rule::enum(TemplateType::class),
                $this->typeUnchangedWhileInUseRule($template),
            ],
            'name' => ['required', 'string', 'max:120'],
            // Unique per type, as legacy
            'slug' => [
                'required',
                'string',
                'max:120',
                'alpha_dash',
                Rule::unique('cms_templates', 'slug')->where('type', $type?->value)->ignore($template?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],

            'fields' => ['nullable', 'array', 'max:100'],
            'fields.*' => ['array'],
            'fields.*.id' => ['nullable', 'integer', Rule::exists('cms_template_fields', 'id')->where('template_id', $template->id ?? 0)],
            'fields.*.type' => ['required', Rule::enum(TemplateFieldType::class), $this->fieldTypeAllowedRule($type)],
            'fields.*.name' => ['required', 'string', 'max:120'],
            'fields.*.slug' => ['required', 'string', 'max:120', 'alpha_dash', 'distinct'],
            'fields.*.description' => ['nullable', 'string', 'max:500'],
            'fields.*.is_required' => ['boolean'],
            'fields.*.settings' => ['nullable', 'array'],
            'fields.*.settings.max_length' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'fields.*.settings.rows' => ['nullable', 'integer', 'min:1', 'max:50'],
            'fields.*.settings.min' => ['nullable', 'numeric'],
            'fields.*.settings.max' => ['nullable', 'numeric', $this->maxNotBelowMinRule()],
            'fields.*.settings.template_id' => ['required_if:fields.*.type,component,repeater', 'nullable', 'integer', $this->nestedTemplateRule($template)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => __('cms.templates.validation.slug_taken'),
            'fields.*.slug.distinct' => __('cms.templates.validation.duplicate_field_slug'),
        ];
    }

    /**
     * Other templates' component and repeater fields expect this template's type, so it can't
     * change while they use it
     */
    protected function typeUnchangedWhileInUseRule(?Template $template): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($template): void {
            if ($template !== null && $value !== $template->type->value && $template->isInUse()) {
                $fail(__('cms.templates.validation.type_in_use'));
            }
        };
    }

    /**
     * A field's type must be allowed in this type of template, have its content editor, and have
     * any module it needs switched on. Existing fields of a type that's since become unavailable
     * can be kept.
     */
    protected function fieldTypeAllowedRule(?TemplateType $templateType): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($templateType): void {
            $type = TemplateFieldType::tryFrom((string) $value);

            if ($type === null || $templateType === null) {
                return;
            }

            if (! $templateType->allowsFieldType($type)) {
                $fail(__('cms.templates.validation.field_type_not_allowed', [
                    'field' => $type->label(),
                    'template' => $templateType->label(),
                ]));

                return;
            }

            $module = $type->requiredModule();
            $isNew = blank($this->input(Str::replaceLast('.type', '.id', $attribute)));

            if ($isNew && (! $type->isAvailable() || ($module !== null && ! (tenant()?->hasModule($module) ?? false)))) {
                $fail(__('cms.templates.validation.field_type_unavailable', ['field' => $type->label()]));
            }
        };
    }

    /**
     * A component or repeater field must use a template of the matching type, and not this one
     */
    protected function nestedTemplateRule(?Template $template): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($template): void {
            $fieldType = TemplateFieldType::tryFrom((string) $this->input(Str::replaceLast('.settings.template_id', '.type', $attribute)));
            $expectedType = $fieldType?->nestedTemplateType();

            if ($expectedType === null) {
                return;
            }

            $nested = is_numeric($value) ? Template::query()->whereKey((int) $value)->first() : null;

            if ($nested === null || $nested->type !== $expectedType || $nested->id === $template?->id) {
                $fail(__('cms.templates.validation.nested_template', ['type' => $expectedType->label()]));
            }
        };
    }

    /**
     * A number field's maximum cannot be below its minimum (either can be left blank)
     */
    protected function maxNotBelowMinRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $min = $this->input(Str::replaceLast('.max', '.min', $attribute));

            if (is_numeric($value) && is_numeric($min) && $value < $min) {
                $fail(__('cms.templates.validation.max_below_min'));
            }
        };
    }

    /**
     * The validated template attributes, without the fields
     *
     * @return array<string, mixed>
     */
    public function templateAttributes(): array
    {
        return $this->safe()->except('fields');
    }

    /**
     * The validated fields in their submitted order, with only the settings that apply to each type
     *
     * @return list<array{id: int|null, type: TemplateFieldType, name: string, slug: string, description: string|null, is_required: bool, order: int, settings: TemplateFieldSettings|null}>
     */
    public function fieldsAttributes(): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = $this->validated('fields') ?? [];

        return array_map(function (array $field, int $order) {
            $type = TemplateFieldType::from((string) $field['type']);

            /** @var array<string, mixed> $settings */
            $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];
            $settings = array_filter(
                array_intersect_key($settings, array_flip($type->settingKeys())),
                fn (mixed $value) => $value !== null && $value !== '',
            );

            return [
                'id' => isset($field['id']) ? (int) $field['id'] : null,
                'type' => $type,
                'name' => (string) $field['name'],
                'slug' => (string) $field['slug'],
                'description' => filled($field['description'] ?? null) ? (string) $field['description'] : null,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'order' => $order,
                'settings' => $settings === [] ? null : TemplateFieldSettings::from($settings),
            ];
        }, $fields, array_keys($fields));
    }
}
