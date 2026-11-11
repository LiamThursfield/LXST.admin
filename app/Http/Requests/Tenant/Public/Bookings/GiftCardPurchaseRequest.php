<?php

namespace App\Http\Requests\Tenant\Public\Bookings;

use App\Enums\Bookings\GiftCardKind;
use App\Enums\Bookings\ServicePriceType;
use App\Models\Bookings\Service;
use App\Services\Bookings\GiftCards;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GiftCardPurchaseRequest extends FormRequest
{
    /**
     * Anyone can buy one, once the business sells them online
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. Amounts are entered in pounds.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(GiftCardKind::class)],
            'amount' => ['nullable', 'required_if:kind,amount', 'numeric', 'decimal:0,2', 'min:'.(GiftCards::ONLINE_MINIMUM / 100), 'max:'.(GiftCards::ONLINE_MAXIMUM / 100)],
            // Only services customers can book online, with a set price
            'service_id' => ['nullable', 'required_if:kind,service', 'integer', Rule::exists('bookings_services', 'id')
                ->whereNull('deleted_at')
                ->where('is_bookable_online', true)
                ->where('price_type', ServicePriceType::Fixed->value)],
            'purchaser_name' => ['required', 'string', 'max:255'],
            'purchaser_email' => ['required', 'email', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{kind: GiftCardKind, service: Service|null, amount: int|null, purchaser_name: string, purchaser_email: string, recipient_name: string|null, recipient_email: string|null, message: string|null}
     */
    public function details(): array
    {
        $kind = GiftCardKind::from($this->string('kind')->toString());
        $amount = $this->validated('amount');

        return [
            'kind' => $kind,
            'service' => $kind === GiftCardKind::Service ? Service::query()->find($this->integer('service_id')) : null,
            // Pounds to pence, through round() as 19.99 * 100 is just under 1999 as a float
            'amount' => $kind === GiftCardKind::Amount && $amount !== null ? (int) round((float) $amount * 100) : null,
            'purchaser_name' => $this->string('purchaser_name')->toString(),
            'purchaser_email' => $this->string('purchaser_email')->toString(),
            'recipient_name' => $this->validated('recipient_name'),
            'recipient_email' => $this->validated('recipient_email'),
            'message' => $this->validated('message'),
        ];
    }
}
