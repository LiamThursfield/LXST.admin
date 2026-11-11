<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\GiftCardKind;
use App\Enums\Bookings\GiftCardStatus;
use App\Services\Authorisation\Enums\BookingsPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GiftCardIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(BookingsPermission::ViewPayments) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.status' => ['nullable', Rule::enum(GiftCardStatus::class)],
            'filter.kind' => ['nullable', Rule::enum(GiftCardKind::class)],
            'filter.search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
