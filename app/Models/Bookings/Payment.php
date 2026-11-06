<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentStatus;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money towards a booking: asked for online through Stripe, or taken in person
 *
 * @property int $id
 * @property string $public_id
 * @property int $booking_id
 * @property int $amount In minor units, e.g. pence
 * @property string $currency
 * @property PaymentStatus $status
 * @property PaymentMethod $method
 * @property int $application_fee In minor units
 * @property string|null $stripe_checkout_session_id
 * @property string|null $checkout_url
 * @property string|null $stripe_payment_intent_id
 * @property string|null $note
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $expires_at
 * @property int|null $created_by_id
 * @property CarbonInterface|null $created_at
 * @property-read Booking $booking
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
        'amount',
        'currency',
        'status',
        'method',
        'application_fee',
        'stripe_checkout_session_id',
        'checkout_url',
        'stripe_payment_intent_id',
        'note',
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
     * Whether its link can still be paid
     */
    public function isPayable(): bool
    {
        return $this->status === PaymentStatus::Pending
            && $this->checkout_url !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
