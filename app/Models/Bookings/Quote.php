<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\QuoteStatus;
use App\Models\Bookings\Concerns\HasLineItems;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\QuoteFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A price for a booking, made of lines, that the customer accepts or declines through a link. Most
 * often for an enquiry, e.g. an event or a job, before it has a price.
 *
 * @property int $id
 * @property string $public_id
 * @property string $number
 * @property int $booking_id
 * @property QuoteStatus $status
 * @property int $subtotal In minor units
 * @property string|null $tax_rate A percentage, e.g. 20.00
 * @property int $tax In minor units
 * @property int $total In minor units
 * @property string $currency
 * @property string|null $notes
 * @property CarbonInterface|null $valid_until
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $accepted_at
 * @property CarbonInterface|null $declined_at
 * @property int|null $created_by_id
 * @property CarbonInterface|null $created_at
 * @property-read Booking $booking
 * @property-read Collection<int, LineItem> $lines
 */
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, HasLineItems, HasUlids;

    protected $table = 'bookings_quotes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'booking_id',
        'status',
        'subtotal',
        'tax_rate',
        'tax',
        'total',
        'currency',
        'notes',
        'valid_until',
        'sent_at',
        'accepted_at',
        'declined_at',
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
            'status' => QuoteStatus::class,
            'subtotal' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax' => 'integer',
            'total' => 'integer',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
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
     * Its status now: one sent and past its date without an answer has expired
     */
    public function currentStatus(): QuoteStatus
    {
        if ($this->status === QuoteStatus::Sent && $this->valid_until !== null && $this->valid_until->lt(today())) {
            return QuoteStatus::Expired;
        }

        return $this->status;
    }

    /**
     * Whether it can still be changed, before the customer has answered
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [QuoteStatus::Draft, QuoteStatus::Sent], true);
    }

    /**
     * Whether the customer can still accept or decline it
     */
    public function isOpen(): bool
    {
        return $this->currentStatus() === QuoteStatus::Sent;
    }
}
