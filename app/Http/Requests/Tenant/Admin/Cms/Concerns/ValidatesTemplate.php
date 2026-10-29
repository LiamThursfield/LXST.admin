<?php

namespace App\Http\Requests\Tenant\Admin\Cms\Concerns;

use App\Data\Cms\TemplateFieldSettings;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateFieldWidth;
use App\Enums\Cms\TemplateType;
use App\Enums\Crm\OrganisationUnitType;
use App\Models\Cms\Template;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesTemplate
{
    /** The most fields a template can have, across all its sections */
    protected const int MAX_FIELDS = 100;

    /**
     * Fill in a blank template slug from its name, and blank field slugs (in sections too) from the
     * field names, so they can be checked for duplicates
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && is_string($this->input('name'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }

        $fields = $this->input('fields');

        if (is_array($fields)) {
            $this->merge(['fields' => $this->withFieldSlugs($fields)]);
        }

        $sections = $this->input('sections');

        if (is_array($sections)) {
            $this->merge([
                'sections' => array_map(function (mixed $section) {
                    if (is_array($section) && is_array($section['fields'] ?? null)) {
                        $section['fields'] = $this->withFieldSlugs($section['fields']);
                    }

                    return $section;
                }, $sections),
            ]);
        }
    }

    /**
     * @param  array<mixed>  $fields
     * @return array<mixed>
     */
    protected function withFieldSlugs(array $fields): array
    {
        return array_map(function (mixed $field) {
            if (is_array($field) && blank($field['slug'] ?? null) && is_string($field['name'] ?? null)) {
                $field['slug'] = Str::slug($field['name'], '_');
            }

            return $field;
        }, $fields);
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

            'fields' => ['nullable', 'array', 'max:'.self::MAX_FIELDS],
            ...$this->fieldRules('fields', $type, $template),

            'sections' => ['nullable', 'array', 'max:50'],
            'sections.*' => ['array'],
            'sections.*.id' => ['nullable', 'integer', Rule::exists('cms_template_sections', 'id')->where('template_id', $template->id ?? 0)],
            'sections.*.name' => ['required', 'string', 'max:120'],
            'sections.*.description' => ['nullable', 'string', 'max:500'],
            'sections.*.is_collapsed' => ['boolean'],
            'sections.*.fields' => ['nullable', 'array', 'max:'.self::MAX_FIELDS],
            ...$this->fieldRules('sections.*.fields', $type, $template),
        ];
    }

    /**
     * The rules for a list of fields, either the fields with no section or a section's fields
     *
     * @param  string  $list  e.g. `fields` or `sections.*.fields`
     * @return array<string, mixed>
     */
    protected function fieldRules(string $list, ?TemplateType $type, ?Template $template): array
    {
        return [
            "{$list}.*" => ['array'],
            "{$list}.*.id" => ['nullable', 'integer', Rule::exists('cms_template_fields', 'id')->where('template_id', $template->id ?? 0)],
            "{$list}.*.type" => ['required', Rule::enum(TemplateFieldType::class), $this->fieldTypeAllowedRule($type)],
            "{$list}.*.name" => ['required', 'string', 'max:120'],
            "{$list}.*.slug" => ['required', 'string', 'max:120', 'alpha_dash'],
            "{$list}.*.description" => ['nullable', 'string', 'max:500'],
            "{$list}.*.is_required" => ['boolean'],
            "{$list}.*.settings" => ['nullable', 'array'],
            "{$list}.*.settings.max_length" => ['nullable', 'integer', 'min:1', 'max:65535'],
            "{$list}.*.settings.rows" => ['nullable', 'integer', 'min:1', 'max:50'],
            "{$list}.*.settings.min" => ['nullable', 'numeric'],
            "{$list}.*.settings.max" => ['nullable', 'numeric', $this->maxNotBelowMinRule()],
            "{$list}.*.settings.template_id" => ["required_if:{$list}.*.type,component,repeater", 'nullable', 'integer', $this->nestedTemplateRule($template)],
            "{$list}.*.settings.min_items" => ['nullable', 'integer', 'min:0', 'max:100'],
            "{$list}.*.settings.max_items" => ['nullable', 'integer', 'min:1', 'max:100', $this->maxItemsNotBelowMinRule()],
            "{$list}.*.settings.organisation_unit_type" => ['nullable', Rule::enum(OrganisationUnitType::class)],
            "{$list}.*.settings.width" => ['nullable', Rule::enum(TemplateFieldWidth::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => __('cms.templates.validation.slug_taken'),
        ];
    }

    /**
     * Fields and sections named as in the builder, e.g. "The Name field is required" rather than
     * "The sections.0.fields.1.name field is required"
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'sections.*.name' => __('cms.templates.builder.section_name'),
            'sections.*.description' => __('cms.templates.builder.section_description'),
        ];

        $keys = ['name', 'slug', 'type', 'description', ...array_map(
            fn (string $setting) => "settings.{$setting}",
            ['max_length', 'rows', 'min', 'max', 'template_id', 'min_items', 'max_items', 'organisation_unit_type', 'width'],
        )];

        foreach (['fields', 'sections.*.fields'] as $list) {
            foreach ($keys as $key) {
                $attributes["{$list}.*.{$key}"] = __('cms.templates.builder.'.str_replace('settings.', '', $key));
            }
        }

        return $attributes;
    }

    /**
     * Content is keyed by field slug, so each slug must be unique across the whole template, sections
     * included, and the template can only have so many fields in all
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $paths = $this->fieldPaths();

                if (count($paths) > self::MAX_FIELDS) {
                    $validator->errors()->add('fields', __('cms.templates.validation.too_many_fields', ['max' => self::MAX_FIELDS]));
                }

                $seen = [];

                foreach ($paths as $path) {
                    $slug = $this->input("{$path}.slug");

                    if (! is_string($slug) || $slug === '') {
                        continue;
                    }

                    if (isset($seen[$slug]) && ! $validator->errors()->has("{$path}.slug")) {
                        $validator->errors()->add("{$path}.slug", __('cms.templates.validation.duplicate_field_slug'));
                    }

                    $seen[$slug] = true;
                }
            },
        ];
    }

    /**
     * Where each submitted field is, in template order: the fields with no section first, e.g.
     * `fields.0`, then each section's, e.g. `sections.1.fields.2`
     *
     * @return list<string>
     */
    protected function fieldPaths(): array
    {
        $paths = [];

        foreach (array_keys((array) $this->input('fields', [])) as $index) {
            $paths[] = "fields.{$index}";
        }

        foreach ((array) $this->input('sections', []) as $sectionIndex => $section) {
            foreach (array_keys(is_array($section) ? (array) ($section['fields'] ?? []) : []) as $index) {
                $paths[] = "sections.{$sectionIndex}.fields.{$index}";
            }
        }

        return $paths;
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

            if (! $isNew) {
                return;
            }

            if ($module !== null && ! (tenant()?->hasModule($module) ?? false)) {
                $fail(__('cms.templates.validation.field_type_needs_module', ['field' => $type->label(), 'module' => $module->label()]));
            } elseif (! $type->isAvailable()) {
                $fail(__('cms.templates.validation.field_type_unavailable', ['field' => $type->label()]));
            }
        };
    }

    /**
     * A component or repeater field must use a template of the matching type, and that template
     * can't contain this one (directly or through its own nested templates), or content would nest
     * forever
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

            if ($nested === null || $nested->type !== $expectedType) {
                $fail(__('cms.templates.validation.nested_template', ['type' => $expectedType->label()]));

                return;
            }

            if ($template !== null && ($nested->id === $template->id || $nested->nestsTemplate($template))) {
                $fail(__('cms.templates.validation.nests_itself'));
            }
        };
    }

    /**
     * A repeater's most rows can't be below its fewest (either can be left blank)
     */
    protected function maxItemsNotBelowMinRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $min = $this->input(Str::replaceLast('.max_items', '.min_items', $attribute));

            if (is_numeric($value) && is_numeric($min) && $value < $min) {
                $fail(__('cms.templates.validation.max_below_min'));
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
     * The validated template attributes, without the fields or sections
     *
     * @return array<string, mixed>
     */
    public function templateAttributes(): array
    {
        return $this->safe()->except(['fields', 'sections']);
    }

    /**
     * The validated sections in their submitted order, without their fields
     *
     * @return list<array{id: int|null, name: string, description: string|null, is_collapsed: bool, order: int}>
     */
    public function sectionsAttributes(): array
    {
        /** @var list<array<string, mixed>> $sections */
        $sections = $this->validated('sections') ?? [];

        return array_map(fn (array $section, int $order) => [
            'id' => isset($section['id']) ? (int) $section['id'] : null,
            'name' => (string) $section['name'],
            'description' => filled($section['description'] ?? null) ? (string) $section['description'] : null,
            'is_collapsed' => (bool) ($section['is_collapsed'] ?? false),
            'order' => $order,
        ], $sections, array_keys($sections));
    }

    /**
     * The validated fields in template order (the fields with no section, then each section's), with
     * the section each is in and only the settings that apply to its type
     *
     * @param  list<int>  $sectionIds  The saved sections' ids, in their submitted order
     * @return list<array{id: int|null, section_id: int|null, type: TemplateFieldType, name: string, slug: string, description: string|null, is_required: bool, order: int, settings: TemplateFieldSettings|null}>
     */
    public function fieldsAttributes(array $sectionIds = []): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = $this->validated('fields') ?? [];
        /** @var list<array<string, mixed>> $sections */
        $sections = $this->validated('sections') ?? [];

        $placed = array_map(fn (array $field) => [$field, null], $fields);

        foreach ($sections as $index => $section) {
            /** @var list<array<string, mixed>> $sectionFields */
            $sectionFields = $section['fields'] ?? [];

            foreach ($sectionFields as $field) {
                $placed[] = [$field, $sectionIds[$index] ?? null];
            }
        }

        return array_map(
            fn (array $place, int $order) => $this->fieldAttributes($place[0], $place[1], $order),
            $placed,
            array_keys($placed),
        );
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{id: int|null, section_id: int|null, type: TemplateFieldType, name: string, slug: string, description: string|null, is_required: bool, order: int, settings: TemplateFieldSettings|null}
     */
    protected function fieldAttributes(array $field, ?int $sectionId, int $order): array
    {
        $type = TemplateFieldType::from((string) $field['type']);

        /** @var array<string, mixed> $settings */
        $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];
        $width = TemplateFieldWidth::tryFrom((string) ($settings['width'] ?? ''));
        $settings = array_filter(
            array_intersect_key($settings, array_flip($type->settingKeys())),
            fn (mixed $value) => $value !== null && $value !== '',
        );

        // Full width is the default, so only half width is kept
        if ($width === TemplateFieldWidth::Half) {
            $settings['width'] = $width;
        }

        return [
            'id' => isset($field['id']) ? (int) $field['id'] : null,
            'section_id' => $sectionId,
            'type' => $type,
            'name' => (string) $field['name'],
            'slug' => (string) $field['slug'],
            'description' => filled($field['description'] ?? null) ? (string) $field['description'] : null,
            'is_required' => (bool) ($field['is_required'] ?? false),
            'order' => $order,
            'settings' => $settings === [] ? null : TemplateFieldSettings::from($settings),
        ];
    }
}
