<?php

namespace App\Http\Requests\Central\Admin;

use App\Http\Requests\Central\Admin\Concerns\ValidatesDomain;
use App\Http\Requests\Central\Admin\Concerns\ValidatesTenant;
use Illuminate\Foundation\Http\FormRequest;

class TenantStoreRequest extends FormRequest
{
    use ValidatesDomain;
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
            'domain' => $this->normaliseDomain($this->input('domain')),
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
            'domain' => $this->domainRules(),
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
            ...$this->domainMessages(),
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
