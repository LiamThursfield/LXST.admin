<?php

namespace App\Services\Cms\Portable;

use App\Enums\Crm\FormFieldType;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;

/**
 * Turns CRM forms into portable items. Email recipients and submissions belong to the environment,
 * so they're never included: staging addresses must not reach prod.
 */
class FormExporter
{
    public function __construct(protected PortableContent $portable) {}

    /**
     * @param  iterable<Form>  $forms
     * @return array{format: string, version: int, items: list<array<string, mixed>>}
     */
    public function export(iterable $forms): array
    {
        $items = [];

        foreach ($forms as $form) {
            $items[] = $this->item($form);
        }

        return $this->portable->wrap($items);
    }

    /**
     * @return array<string, mixed>
     */
    public function item(Form $form): array
    {
        return [
            'kind' => 'form',
            'slug' => $form->slug,
            'name' => $form->name,
            'marketing_email' => $form->marketing_email,
            'marketing_sms' => $form->marketing_sms,
            'marketing_telephone' => $form->marketing_telephone,
            'submit_button_text' => $form->submit_button_text,
            'redirect_url' => $form->redirect_url,
            'success_message' => $form->success_message,
            'fields' => $form->fields()->get()->map(fn (FormField $field) => [
                'slug' => $field->slug,
                'type' => $field->type->value,
                'name' => $field->name,
                'is_required' => $field->is_required,
                'settings' => $this->settings($field),
            ])->all(),
        ];
    }

    /**
     * Each type's settings, with no default case so a new field type can't be forgotten
     *
     * @return array<string, mixed>
     */
    protected function settings(FormField $field): array
    {
        $settings = $field->settings;

        return array_filter(match ($field->type) {
            FormFieldType::Text => ['max_length' => $settings?->max_length],
            FormFieldType::Textarea => ['max_length' => $settings?->max_length, 'rows' => $settings?->rows],
            FormFieldType::Number => ['min' => $settings?->min, 'max' => $settings?->max],
            FormFieldType::Email,
            FormFieldType::Hidden,
            FormFieldType::ContactEmail,
            FormFieldType::ContactTitle,
            FormFieldType::ContactFirstName,
            FormFieldType::ContactLastName,
            FormFieldType::ContactTelephone => [],
        }, fn (mixed $value) => $value !== null);
    }
}
