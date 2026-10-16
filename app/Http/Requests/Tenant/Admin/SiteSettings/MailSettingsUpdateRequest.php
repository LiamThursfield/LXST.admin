<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Models\Settings\MailSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class MailSettingsUpdateRequest extends FormRequest
{
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
     * Secrets are never sent to the browser, so leaving one empty keeps the saved one. It's only
     * required when the driver is chosen and nothing is saved yet.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $driver = $this->input('driver');

        return [
            'driver' => ['nullable', Rule::in(MailSettings::DRIVERS)],
            'from_address' => [Rule::requiredIf(filled($driver)), 'nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],

            'smtp_host' => ['required_if:driver,smtp', 'nullable', 'string', 'max:255'],
            'smtp_port' => ['required_if:driver,smtp', 'nullable', 'integer', 'between:1,65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],

            'mailgun_domain' => ['required_if:driver,mailgun', 'nullable', 'string', 'max:255'],
            'mailgun_secret' => [$this->requiredUnlessSaved(MailSettings::DRIVER_MAILGUN, 'mailgun_secret'), 'nullable', 'string', 'max:500'],
            'mailgun_endpoint' => ['required_if:driver,mailgun', 'nullable', Rule::in(MailSettings::MAILGUN_ENDPOINTS)],

            'ses_key' => ['required_if:driver,ses', 'nullable', 'string', 'max:255'],
            'ses_secret' => [$this->requiredUnlessSaved(MailSettings::DRIVER_SES, 'ses_secret'), 'nullable', 'string', 'max:500'],
            'ses_region' => ['required_if:driver,ses', 'nullable', 'regex:/^[a-z]{2}(-[a-z]+)+-\d+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ses_region.regex' => __('site_settings.mail.validation.ses_region'),
        ];
    }

    /**
     * The settings to save: the chosen driver's fields, with every other driver's cleared so
     * unused credentials aren't kept, and saved secrets kept when left empty
     *
     * @return array<string, mixed>
     */
    public function settingsAttributes(MailSettings $current): array
    {
        $driver = $this->validated('driver');
        $attributes = [
            'driver' => $driver,
            'from_address' => $driver ? $this->validated('from_address') : null,
            'from_name' => $driver ? $this->validated('from_name') : null,
        ];

        foreach (MailSettings::driverFields() as $fieldsDriver => $fields) {
            foreach ($fields as $field) {
                if ($fieldsDriver !== $driver) {
                    $attributes[$field] = null;
                } elseif (in_array($field, MailSettings::SECRETS, true) && blank($this->validated($field))) {
                    $attributes[$field] = $current->{$field};
                } else {
                    $attributes[$field] = $this->validated($field);
                }
            }
        }

        return $attributes;
    }

    /**
     * Required when the driver is chosen and no secret is saved yet
     */
    protected function requiredUnlessSaved(string $driver, string $secret): RequiredIf
    {
        return Rule::requiredIf(fn () => $this->input('driver') === $driver
            && blank(app(MailSettings::class)->{$secret}));
    }
}
