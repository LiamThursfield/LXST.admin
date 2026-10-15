<?php

namespace App\Http\Requests\Tenant\Admin\Crm;

use App\Enums\Crm\OrganisationUnitType;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganisationUnitIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', OrganisationUnit::class) ?? false;
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
            'filter.name' => ['nullable', Rule::string()],
            'filter.type' => ['nullable', Rule::enum(OrganisationUnitType::class)],
            'filter.parent_id' => ['nullable', 'integer'],
        ];
    }
}
