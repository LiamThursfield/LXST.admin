<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\GiftCardKind;
use App\Enums\Bookings\PaymentMethod;
use App\Models\Bookings\Service;
use App\Services\Authorisation\Enums\BookingsPermission;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class GiftCardStoreRequest extends FormRequest
{
    /** How it was paid for, or 'free' when it's given */
    public const array METHODS = ['cash', 'card', 'other', 'online', 'free'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(BookingsPermission::ManagePayments) ?? false;
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
            'amount' => ['nullable', 'required_if:kind,amount', 'numeric', 'decimal:0,2', 'gt:0', 'max:100000'],
            'service_id' => ['nullable', 'required_if:kind,service', 'integer', Rule::exists('bookings_services', 'id')->whereNull('deleted_at')],
            'method' => ['required', Rule::in(self::METHODS)],
            'purchaser_name' => ['nullable', 'string', 'max:255'],
            // The link to pay goes to the buyer
            'purchaser_email' => ['nullable', 'required_if:method,online', 'email', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array{kind: GiftCardKind, service: Service|null, amount: int|null, method: PaymentMethod|null, purchaser_name: string|null, purchaser_email: string|null, recipient_name: string|null, recipient_email: string|null, message: string|null, expires_on: CarbonInterface|null}
     */
    public function details(): array
    {
        $kind = GiftCardKind::from($this->string('kind')->toString());
        $method = $this->string('method')->toString();
        $amount = $this->validated('amount');
        $expiresOn = $this->validated('expires_on');

        return [
            'kind' => $kind,
            'service' => $kind === GiftCardKind::Service ? Service::query()->find($this->integer('service_id')) : null,
            // Pounds to pence, through round() as 19.99 * 100 is just under 1999 as a float
            'amount' => $kind === GiftCardKind::Amount && $amount !== null ? (int) round((float) $amount * 100) : null,
            'method' => $method === 'free' ? null : PaymentMethod::from($method),
            'purchaser_name' => $this->validated('purchaser_name'),
            'purchaser_email' => $this->validated('purchaser_email'),
            'recipient_name' => $this->validated('recipient_name'),
            'recipient_email' => $this->validated('recipient_email'),
            'message' => $this->validated('message'),
            'expires_on' => $expiresOn === null ? null : Carbon::parse($expiresOn),
        ];
    }
}
