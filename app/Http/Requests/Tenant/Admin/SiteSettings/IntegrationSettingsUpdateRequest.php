<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class IntegrationSettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(SettingsPermission::ManageSettings) ?? false;
    }

    /**
     * Container IDs are always upper case, so accept e.g. "gtm-abc1234" and store it as GTM-ABC1234
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('gtm_id'))) {
            $this->merge(['gtm_id' => Str::upper(trim($this->input('gtm_id')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gtm_id' => ['nullable', 'string', 'max:20', 'regex:/^GTM-[A-Z0-9]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gtm_id.regex' => __('site_settings.integrations.validation.gtm_id_format'),
        ];
    }
}
