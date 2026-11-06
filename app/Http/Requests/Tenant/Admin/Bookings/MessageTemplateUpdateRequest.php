<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Services\Authorisation\Enums\BookingsPermission;
use Illuminate\Foundation\Http\FormRequest;

class MessageTemplateUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(BookingsPermission::ManageMessages) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. Blank wording uses the default.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['boolean'],
            // Up to a month before or after
            'offset_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'email_subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'string', 'max:5000'],
            // Long texts are split into several and cost more, so they're kept to a few
            'sms_body' => ['nullable', 'string', 'max:480'],
            'whatsapp_template_sid' => ['nullable', 'string', 'regex:/^HX[0-9a-fA-F]{32}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp_template_sid.regex' => __('bookings.messages.validation.whatsapp_template_sid'),
        ];
    }
}
