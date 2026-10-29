<?php

namespace App\Http\Requests\Tenant\Admin\Crm\Concerns;

use App\Data\Crm\FormFieldSettings;
use App\Enums\Crm\FormFieldType;
use App\Models\Crm\Form;
use App\Services\Crm\FormSpamCheck;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesForm
{
    /**
     * Fill in blank field slugs from the field names, so they can be checked for duplicates
     */
    protected function prepareForValidation(): void
    {
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
     * The shared validation rules for creating or updating a form.
     *
     * @return array<string, mixed>
     */
    protected function formRules(?Form $form = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // Unique across every form, including deleted ones
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('crm_forms', 'slug')->ignore($form?->id)],
            'email_recipients' => ['nullable', 'array'],
            // Blank rows are allowed, and dropped when saving
            'email_recipients.*' => ['nullable', 'email', 'max:255'],
            'marketing_email' => ['boolean'],
            'marketing_sms' => ['boolean'],
            'marketing_telephone' => ['boolean'],
            'submit_button_text' => ['nullable', 'string', 'max:50'],
            'redirect_url' => ['nullable', 'url', 'max:150'],
            'success_message' => ['nullable', 'string', 'max:1024'],

            'fields' => ['nullable', 'array', 'max:50', $this->contactFieldsOnceRule()],
            'fields.*' => ['array'],
            'fields.*.id' => ['nullable', 'integer', Rule::exists('crm_form_fields', 'id')->where('form_id', $form->id ?? 0)],
            'fields.*.type' => ['required', Rule::enum(FormFieldType::class)],
            'fields.*.name' => ['required', 'string', 'max:120'],
            'fields.*.slug' => [
                'required',
                'string',
                'max:120',
                'alpha_dash',
                'distinct',
                // The marketing consent answers and spam check fields are submitted alongside the answers, so can't be used as keys
                Rule::notIn([...FormSpamCheck::reservedFields(), 'marketing_email', 'marketing_sms', 'marketing_telephone']),
            ],
            'fields.*.is_required' => ['boolean'],
            'fields.*.settings' => ['nullable', 'array'],
            'fields.*.settings.max_length' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'fields.*.settings.rows' => ['nullable', 'integer', 'min:1', 'max:50'],
            'fields.*.settings.min' => ['nullable', 'numeric'],
            'fields.*.settings.max' => ['nullable', 'numeric', $this->maxNotBelowMinRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.*.slug.distinct' => __('crm.forms.validation.duplicate_field_slug'),
            'fields.*.slug.not_in' => __('crm.forms.validation.reserved_field_slug'),
        ];
    }

    /**
     * Fields named as in the builder, e.g. "The Label field is required" rather than "The
     * fields.0.name field is required"
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['name', 'slug', 'type', 'settings.max_length', 'settings.rows', 'settings.min', 'settings.max'] as $key) {
            $attributes["fields.*.{$key}"] = __('crm.forms.builder.'.str_replace('settings.', '', $key));
        }

        return $attributes;
    }

    /**
     * A number field's maximum cannot be below its minimum (either can be left blank)
     */
    protected function maxNotBelowMinRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $min = $this->input(Str::replaceLast('.max', '.min', $attribute));

            if (is_numeric($value) && is_numeric($min) && $value < $min) {
                $fail(__('crm.forms.validation.max_below_min'));
            }
        };
    }

    /**
     * Each contact detail (e.g. the contact's email) can only be asked for once per form
     */
    protected function contactFieldsOnceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $duplicates = collect($value)
                ->map(fn (mixed $field) => is_array($field) ? FormFieldType::tryFrom((string) ($field['type'] ?? '')) : null)
                ->filter(fn (?FormFieldType $type) => $type?->isContactField() ?? false)
                ->map(fn (FormFieldType $type) => $type->value)
                ->duplicates();

            if ($duplicates->isNotEmpty()) {
                $fail(__('crm.forms.validation.duplicate_contact_field', [
                    'type' => FormFieldType::from((string) $duplicates->first())->label(),
                ]));
            }
        };
    }

    /**
     * The validated form attributes, without the fields
     *
     * @return array<string, mixed>
     */
    public function formAttributes(): array
    {
        $attributes = $this->safe()->except('fields');

        /** @var list<string|null> $recipients */
        $recipients = $attributes['email_recipients'] ?? [];
        $attributes['email_recipients'] = array_values(array_filter($recipients)) ?: null;

        return $attributes;
    }

    /**
     * The validated fields in their submitted order, with only the settings that apply to each type
     *
     * @return list<array{id: int|null, type: FormFieldType, name: string, slug: string, is_required: bool, order: int, settings: FormFieldSettings|null}>
     */
    public function fieldsAttributes(): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = $this->validated('fields') ?? [];

        return array_map(function (array $field, int $order) {
            $type = FormFieldType::from((string) $field['type']);

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
                'is_required' => (bool) ($field['is_required'] ?? false),
                'order' => $order,
                'settings' => $settings === [] ? null : FormFieldSettings::from($settings),
            ];
        }, $fields, array_keys($fields));
    }
}
