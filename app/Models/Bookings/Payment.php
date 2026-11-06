<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentPurpose;
use App\Enums\Bookings\PaymentStatus;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\PaymentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money towards a booking, or a fee for not coming or cancelling late: asked for online through
 * Stripe, charged to a saved card, or taken in person
 *
 * @property int $id
 * @property string $public_id
 * @property int $booking_id
 * @property PaymentPurpose $purpose
 * @property int $amount In minor units, e.g. pence
 * @property string $currency
 * @property PaymentStatus $status
 * @property PaymentMethod $method
 * @property int $application_fee In minor units
 * @property string|null $stripe_checkout_session_id
 * @property string|null $checkout_url
 * @property string|null $stripe_payment_intent_id
 * @property string|null $note
 * @property string|null $failure_message
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $expires_at
 * @property int|null $created_by_id
 * @property CarbonInterface|null $created_at
 * @property-read Booking $booking
 * @property-read Collection<int, Refund> $refunds
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlids;

    protected $table = 'bookings_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'purpose',
        'amount',
        'currency',
        'status',
        'method',
        'application_fee',
        'stripe_checkout_session_id',
        'checkout_url',
        'stripe_payment_intent_id',
        'note',
        'failure_message',
        'paid_at',
        'expires_at',
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
            'amount' => 'integer',
            'purpose' => PaymentPurpose::class,
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'application_fee' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Whether its link can still be paid
     */
    public function isPayable(): bool
    {
        return $this->status === PaymentStatus::Pending
            && $this->checkout_url !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
