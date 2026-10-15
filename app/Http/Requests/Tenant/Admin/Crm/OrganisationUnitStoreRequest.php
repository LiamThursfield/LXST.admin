<?php

namespace App\Http\Requests\Tenant\Admin\Crm;

use App\Http\Requests\Tenant\Admin\Crm\Concerns\ValidatesOrganisationUnit;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Foundation\Http\FormRequest;

class OrganisationUnitStoreRequest extends FormRequest
{
    use ValidatesOrganisationUnit;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', OrganisationUnit::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organisationUnitRules();
    }
}
