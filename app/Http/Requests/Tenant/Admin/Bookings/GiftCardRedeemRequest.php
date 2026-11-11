<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Services\Authorisation\Enums\BookingsPermission;
use Illuminate\Foundation\Http\FormRequest;

class GiftCardRedeemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(BookingsPermission::ManagePayments) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. A blank amount takes as much as it can.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30'],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
        ];
    }

    /**
     * Pounds to pence, through round() as 19.99 * 100 is just under 1999 as a float
     */
    public function amountInMinorUnits(): ?int
    {
        $amount = $this->validated('amount');

        return $amount === null ? null : (int) round((float) $amount * 100);
    }
}
