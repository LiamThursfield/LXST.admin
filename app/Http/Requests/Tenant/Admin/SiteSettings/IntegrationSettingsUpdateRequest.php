<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Enums\CaptchaProvider;
use App\Models\Settings\IntegrationSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

        foreach (['captcha_site_key', 'captcha_secret_key'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usesCaptcha = filled($this->input('captcha_provider'));
        $hasSecret = filled(app(IntegrationSettings::class)->captcha_secret_key);

        return [
            'gtm_id' => ['nullable', 'string', 'max:20', 'regex:/^GTM-[A-Z0-9]+$/'],
            'captcha_provider' => ['nullable', Rule::enum(CaptchaProvider::class)],
            'captcha_site_key' => [Rule::requiredIf($usesCaptcha), 'nullable', 'string', 'max:255'],
            'captcha_secret_key' => [Rule::requiredIf($usesCaptcha && ! $hasSecret), 'nullable', 'string', 'max:255'],
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'captcha_site_key' => __('site_settings.integrations.captcha_site_key'),
            'captcha_secret_key' => __('site_settings.integrations.captcha_secret_key'),
        ];
    }

    /**
     * The settings to save. Without a captcha provider its keys are cleared, and a saved secret key
     * is kept when left empty.
     *
     * @return array<string, mixed>
     */
    public function settingsAttributes(IntegrationSettings $current): array
    {
        $attributes = ['gtm_id' => $this->validated('gtm_id') ?: null];
        $provider = $this->validated('captcha_provider');

        if (blank($provider)) {
            return [...$attributes, 'captcha_provider' => null, 'captcha_site_key' => null, 'captcha_secret_key' => null];
        }

        return [
            ...$attributes,
            'captcha_provider' => CaptchaProvider::from((string) $provider),
            'captcha_site_key' => $this->validated('captcha_site_key'),
            'captcha_secret_key' => filled($this->validated('captcha_secret_key')) ? $this->validated('captcha_secret_key') : $current->captcha_secret_key,
        ];
    }
}
