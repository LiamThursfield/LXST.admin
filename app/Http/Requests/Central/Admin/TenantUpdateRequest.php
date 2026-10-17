<?php

namespace App\Http\Requests\Central\Admin;

use App\Http\Requests\Central\Admin\Concerns\ValidatesTenant;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class TenantUpdateRequest extends FormRequest
{
    use ValidatesTenant;

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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->tenantRules($tenant);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->tenantMessages();
    }
}
