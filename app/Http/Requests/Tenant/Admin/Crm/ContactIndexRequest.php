<?php

namespace App\Http\Requests\Tenant\Admin\Crm;

use App\Models\Crm\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Contact::class) ?? false;
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
            'filter.search' => ['nullable', Rule::string()],
            'filter.is_spam' => ['nullable', Rule::in(['0', '1'])],
            'filter.trashed' => ['nullable', Rule::in(['only'])],
        ];
    }
}
