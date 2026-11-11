<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Foundation\Http\FormRequest;

class PaymentSettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(SettingsPermission::ManageSettings) ?? false;
    }

    /**
     * Get the validation rules that apply to the request: gift cards, and tax and terms for quotes
     * and invoices. Blank months means gift cards never expire, and a blank rate means no tax.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gift_card_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'gift_cards_online' => ['required', 'boolean'],
            'tax_rate' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:100'],
            'tax_label' => ['required', 'string', 'max:20'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'business_details' => ['nullable', 'string', 'max:1000'],
            'quote_valid_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'invoice_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
