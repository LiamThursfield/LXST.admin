<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Models\Settings\MessagingSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessagingSettingsUpdateRequest extends FormRequest
{
    /** Twilio numbers, or an alphanumeric sender ID of up to 11 characters */
    protected const string SENDER = '/^(\+[1-9]\d{6,14}|[A-Za-z0-9 ]{1,11})$/';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(SettingsPermission::ManageSettings) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usesTwilio = $this->input('provider') === MessagingSettings::PROVIDER_TWILIO;
        $hasToken = filled(app(MessagingSettings::class)->twilio_auth_token);

        return [
            'provider' => ['nullable', Rule::in(MessagingSettings::PROVIDERS)],
            'twilio_account_sid' => [Rule::requiredIf($usesTwilio), 'nullable', 'string', 'regex:/^AC[0-9a-fA-F]{32}$/'],
            'twilio_auth_token' => [Rule::requiredIf($usesTwilio && ! $hasToken), 'nullable', 'string', 'max:255'],
            'sms_from' => ['nullable', 'string', 'regex:'.self::SENDER],
            'whatsapp_from' => ['nullable', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'twilio_account_sid.regex' => __('site_settings.messaging.validation.account_sid'),
            'sms_from.regex' => __('site_settings.messaging.validation.sms_from'),
            'whatsapp_from.regex' => __('site_settings.messaging.validation.whatsapp_from'),
        ];
    }

    /**
     * The settings to save. Without a provider its details are cleared, and a saved token is kept
     * when left empty.
     *
     * @return array<string, mixed>
     */
    public function settingsAttributes(MessagingSettings $current): array
    {
        $provider = $this->validated('provider');

        if ($provider === null) {
            return ['provider' => null, 'twilio_account_sid' => null, 'twilio_auth_token' => null, 'sms_from' => null, 'whatsapp_from' => null];
        }

        return [
            'provider' => $provider,
            'twilio_account_sid' => $this->validated('twilio_account_sid'),
            'twilio_auth_token' => filled($this->validated('twilio_auth_token')) ? $this->validated('twilio_auth_token') : $current->twilio_auth_token,
            'sms_from' => $this->validated('sms_from'),
            'whatsapp_from' => $this->validated('whatsapp_from'),
        ];
    }
}
