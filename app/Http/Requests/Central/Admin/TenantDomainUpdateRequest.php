<?php

namespace App\Http\Requests\Central\Admin;

use App\Http\Requests\Central\Admin\Concerns\ValidatesDomain;
use Illuminate\Foundation\Http\FormRequest;
use Stancl\Tenancy\Database\Models\Domain;

class TenantDomainUpdateRequest extends FormRequest
{
    use ValidatesDomain;

    /**
     * Every central user can manage tenants (central users are all super users)
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['domain' => $this->normaliseDomain($this->input('domain'))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Domain $domain */
        $domain = $this->route('domain');

        return [
            'domain' => $this->domainRules($domain->getKey()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->domainMessages();
    }
}
