<?php

namespace App\Http\Requests\Tenant\Admin\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', Rule::string()],
            'last_name' => ['nullable', Rule::string()],
            'email' => ['nullable', Rule::string()],
            'role' => ['nullable', 'array'],
        ];
    }
}
