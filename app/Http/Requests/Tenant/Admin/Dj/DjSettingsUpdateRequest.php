<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\RequestsMode;
use App\Services\Authorisation\Enums\DjPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DjSettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(DjPermission::ManageGigs) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'default_timezone' => ['required', 'timezone:all'],
            'request_page_url' => ['nullable', 'url:http,https', 'max:2048'],
            'requests_mode' => ['required', Rule::enum(RequestsMode::class)],
            'requests_open_minutes_before' => ['required', 'integer', 'min:0', 'max:1440'],
            'max_requests_per_visitor' => ['required', 'integer', 'min:0', 'max:1000'],
            'max_pending_requests' => ['required', 'integer', 'min:0', 'max:10000'],
            'allow_custom_requests' => ['required', 'boolean'],
            'show_public_list' => ['required', 'boolean'],
            'allow_upvotes' => ['required', 'boolean'],
            'hosted_page_enabled' => ['required', 'boolean'],
            'show_pass_labels' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'default_timezone' => __('dj.settings.fields.default_timezone'),
            'request_page_url' => __('dj.settings.fields.request_page_url'),
            'requests_open_minutes_before' => __('dj.request_settings.fields.requests_open_minutes_before'),
            'max_requests_per_visitor' => __('dj.request_settings.fields.max_requests_per_visitor'),
            'max_pending_requests' => __('dj.request_settings.fields.max_pending_requests'),
        ];
    }
}
