<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Foundation\Http\FormRequest;

class MessagingTestRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'regex:/^\+[1-9][\d\s]{6,18}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.regex' => __('bookings.bookings.validation.international_phone'),
        ];
    }
}
