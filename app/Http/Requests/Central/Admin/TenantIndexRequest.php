<?php

namespace App\Http\Requests\Central\Admin;

use App\Enums\TenantModule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantIndexRequest extends FormRequest
{
    /**
     * Every central user can manage tenants (central users are all super users)
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'filter.module' => ['nullable', Rule::enum(TenantModule::class)],
            'sort' => ['nullable', Rule::in(['name', '-name', 'id', '-id', 'created_at', '-created_at'])],
        ];
    }
}
