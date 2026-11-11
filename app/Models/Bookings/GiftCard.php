<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\GiftCardKind;
use App\Enums\Bookings\GiftCardStatus;
use App\Enums\Bookings\PaymentMethod;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\GiftCardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A gift card or voucher: an amount spent over any number of bookings, or one booking of a service.
 * Sold by staff or online, or given free, and used by giving its code.
 *
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property GiftCardKind $kind
 * @property int|null $service_id
 * @property GiftCardStatus $status
 * @property int $amount In minor units. A voucher's is the service's price when issued.
 * @property int $balance In minor units
 * @property string $currency
 * @property int $price_paid In minor units
 * @property PaymentMethod|null $payment_method Null when given free
 * @property int $application_fee In minor units
 * @property string|null $stripe_checkout_session_id
 * @property string|null $checkout_url
 * @property string|null $stripe_payment_intent_id
 * @property string|null $purchaser_name
 * @property string|null $purchaser_email
 * @property string|null $recipient_name
 * @property string|null $recipient_email
 * @property string|null $message
 * @property CarbonInterface|null $expires_on The last day it can be used
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $link_expires_at
 * @property CarbonInterface|null $sent_at
 * @property int|null $created_by_id
 * @property CarbonInterface|null $created_at
 * @property-read Service|null $service
 * @property-read Collection<int, Payment> $payments
 */
class GiftCard extends Model
{
    /** @use HasFactory<GiftCardFactory> */
    use HasFactory, HasUlids;

    protected $table = 'bookings_gift_cards';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'kind',
        'service_id',
        'status',
        'amount',
        'balance',
        'currency',
        'price_paid',
        'payment_method',
        'application_fee',
        'stripe_checkout_session_id',
        'checkout_url',
        'stripe_payment_intent_id',
        'purchaser_name',
        'purchaser_email',
        'recipient_name',
        'recipient_email',
        'message',
        'expires_on',
        'paid_at',
        'link_expires_at',
        'sent_at',
        'created_by_id',
    ];

    /**
     * The id stays numeric; only the public one is a ULID
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => GiftCardKind::class,
            'status' => GiftCardStatus::class,
            'amount' => 'integer',
            'balance' => 'integer',
            'price_paid' => 'integer',
            'payment_method' => PaymentMethod::class,
            'application_fee' => 'integer',
            'expires_on' => 'date',
            'paid_at' => 'datetime',
            'link_expires_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * What's been paid towards bookings from it
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Cards with a status, counting ready ones past their last day as expired
     *
     * @param  Builder<self>  $query
     */
    public function scopeWhereCurrentStatus(Builder $query, GiftCardStatus $status): void
    {
        $today = today()->toDateString();

        match ($status) {
            GiftCardStatus::Active => $query->where('status', GiftCardStatus::Active)->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhere('expires_on', '>=', $today)),
            GiftCardStatus::Expired => $query->where(fn (Builder $query) => $query->where('status', GiftCardStatus::Expired)
                ->orWhere(fn (Builder $query) => $query->where('status', GiftCardStatus::Active)->where('expires_on', '<', $today))),
            default => $query->where('status', $status),
        };
    }

    /**
     * Its status now: a ready card past its last day has expired, though nothing has changed it
     */
    public function currentStatus(): GiftCardStatus
    {
        if ($this->status === GiftCardStatus::Active && $this->expires_on !== null && $this->expires_on->lt(today())) {
            return GiftCardStatus::Expired;
        }

        return $this->status;
    }

    public function isUsable(): bool
    {
        return $this->currentStatus() === GiftCardStatus::Active && $this->balance > 0;
    }

    /**
     * Whether its payment link can still be paid
     */
    public function isPayable(): bool
    {
        return $this->status === GiftCardStatus::Pending
            && $this->checkout_url !== null
            && ($this->link_expires_at === null || $this->link_expires_at->isFuture());
    }
}
