<?php

namespace App\Http\Requests\Central\Admin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Deleting a tenant removes its database for good, so it must be disabled first, and its name typed
 */
class TenantDestroyRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirm_name' => ['required', 'string', Rule::in([$this->tenant()->name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_name.required' => __('tenants.validation.confirm_name'),
            'confirm_name.in' => __('tenants.validation.confirm_name'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->tenant()->isDisabled()) {
                    $validator->errors()->add('tenant', __('tenants.validation.disable_first'));
                }
            },
        ];
    }

    protected function tenant(): Tenant
    {
        /** @var Tenant */
        return $this->route('tenant');
    }
}
