<?php

namespace App\Http\Requests\Tenant\Api\V1\Crm;

use App\Enums\Crm\FormFieldType;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A public submission of a CRM form. The rules are built from the form's fields.
 */
class FormSubmissionStoreRequest extends FormRequest
{
    /**
     * Anyone may submit a form; abuse is limited by rate limiting and the spam check
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $form = $this->crmForm();
        $rules = [];

        foreach ($form->fields as $field) {
            $rules[$field->slug] = [
                $field->is_required ? 'required' : 'nullable',
                ...$this->fieldRules($field),
            ];
        }

        foreach (Form::MARKETING_FIELDS as $marketingField) {
            if ($form->getAttribute($marketingField)) {
                $rules[$marketingField] = ['nullable', 'boolean'];
            }
        }

        return $rules;
    }

    /**
     * Use the fields' labels in validation messages, e.g. "The Your message field is required."
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->crmForm()->fields
            ->mapWithKeys(fn (FormField $field) => [$field->slug => $field->name])
            ->all();
    }

    /**
     * The validation rules for one field's answer, based on its type and settings
     *
     * @return list<string>
     */
    protected function fieldRules(FormField $field): array
    {
        $settings = $field->settings;

        return match ($field->type) {
            FormFieldType::Text => ['string', 'max:'.($settings->max_length ?? 255)],
            FormFieldType::Textarea => ['string', 'max:'.($settings->max_length ?? 10000)],
            FormFieldType::Number => array_values(array_filter([
                'numeric',
                $settings?->min !== null ? 'min:'.$settings->min : null,
                $settings?->max !== null ? 'max:'.$settings->max : null,
            ])),
            FormFieldType::Email, FormFieldType::ContactEmail => ['email', 'max:255'],
            FormFieldType::Hidden => ['string', 'max:1000'],
            FormFieldType::ContactTelephone => ['string', 'max:20'],
            FormFieldType::ContactTitle => ['string', 'max:50'],
            FormFieldType::ContactFirstName, FormFieldType::ContactLastName => ['string', 'max:255'],
        };
    }

    public function crmForm(): Form
    {
        /** @var Form $form */
        $form = $this->route('form');

        return $form->loadMissing('fields');
    }
}
