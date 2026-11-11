<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Foundation\Http\FormRequest;

class GiftCardSettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(SettingsPermission::ManageSettings) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. Blank months means they never expire.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gift_card_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'gift_cards_online' => ['required', 'boolean'],
        ];
    }
}
