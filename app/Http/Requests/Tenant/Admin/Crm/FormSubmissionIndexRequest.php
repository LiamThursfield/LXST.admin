<?php

namespace App\Http\Requests\Tenant\Admin\Crm;

use App\Models\Crm\FormSubmission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormSubmissionIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FormSubmission::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.form_id' => ['nullable', 'integer'],
            'filter.contact_id' => ['nullable', 'integer'],
            'filter.is_spam' => ['nullable', Rule::in(['0', '1'])],
            'filter.submitted_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.submitted_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filter.submitted_from'],
        ];
    }
}
