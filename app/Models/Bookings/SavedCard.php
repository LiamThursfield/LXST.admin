<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\SavedCardStatus;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\SavedCardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's card kept on the business's Stripe account, so a no-show or late cancellation fee can
 * be charged later. Saved through a link sent to them, or while paying online.
 *
 * @property int $id
 * @property string $public_id
 * @property int $booking_id
 * @property SavedCardStatus $status
 * @property string|null $stripe_checkout_session_id
 * @property string|null $checkout_url
 * @property string|null $stripe_customer_id
 * @property string|null $stripe_payment_method_id
 * @property string|null $brand
 * @property string|null $last4
 * @property CarbonInterface|null $saved_at
 * @property CarbonInterface|null $expires_at
 * @property int|null $created_by_id
 * @property CarbonInterface|null $created_at
 * @property-read Booking $booking
 */
class SavedCard extends Model
{
    /** @use HasFactory<SavedCardFactory> */
    use HasFactory, HasUlids;

    protected $table = 'bookings_saved_cards';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'status',
        'stripe_checkout_session_id',
        'checkout_url',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'brand',
        'last4',
        'saved_at',
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
            'status' => SavedCardStatus::class,
            'saved_at' => 'datetime',
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
     * Whether its link can still be used to save a card
     */
    public function isOpen(): bool
    {
        return $this->status === SavedCardStatus::Requested
            && $this->checkout_url !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * How staff see it, e.g. "Visa ending 4242"
     */
    public function label(): string
    {
        return __('bookings.payments.cards.label', ['brand' => ucfirst((string) ($this->brand ?? __('bookings.payments.cards.card'))), 'last4' => $this->last4 ?? '????']);
    }
}
