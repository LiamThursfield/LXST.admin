<?php

namespace App\Http\Requests\Central\Admin;

use App\Http\Requests\Central\Admin\Concerns\ValidatesTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantStoreRequest extends FormRequest
{
    use ValidatesTenant;

    /**
     * Every central user can manage tenants (central users are all super users)
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain' => strtolower(trim((string) $this->input('domain'))),
            'admin_email' => trim((string) $this->input('admin_email')) ?: null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->tenantRules(),
            // The first domain; more are managed on the tenant's page
            'domain' => [
                'required',
                'string',
                'max:253',
                'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/',
                Rule::unique('domains', 'domain'),
                Rule::notIn(config('tenancy.identification.central_domains')),
            ],
            // The tenant's first admin is optional: give an email to invite one
            'admin_email' => ['nullable', 'email', 'max:255'],
            'admin_first_name' => ['nullable', 'required_with:admin_email', 'string', 'max:255'],
            'admin_last_name' => ['nullable', 'required_with:admin_email', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->tenantMessages(),
            'domain.regex' => __('tenants.validation.domain_format'),
            'domain.unique' => __('tenants.validation.domain_taken'),
            'domain.not_in' => __('tenants.validation.domain_central'),
        ];
    }

    /**
     * @return array{first_name: string, last_name: string, email: string}|null
     */
    public function admin(): ?array
    {
        if (! $this->filled('admin_email')) {
            return null;
        }

        return [
            'first_name' => $this->string('admin_first_name')->toString(),
            'last_name' => $this->string('admin_last_name')->toString(),
            'email' => $this->string('admin_email')->toString(),
        ];
    }
}
