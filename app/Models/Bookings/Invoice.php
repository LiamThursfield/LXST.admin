<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\InvoiceStatus;
use App\Models\Bookings\Concerns\HasLineItems;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\InvoiceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bill for a booking, numbered in order, made of lines. The customer sees it, and pays what's
 * owed, through a link. Payments are the booking's, so what's paid is what's been paid towards it.
 *
 * @property int $id
 * @property string $public_id
 * @property string $number
 * @property int $booking_id
 * @property int|null $quote_id
 * @property InvoiceStatus $status
 * @property int $subtotal In minor units
 * @property string|null $tax_rate A percentage, e.g. 20.00
 * @property int $tax In minor units
 * @property int $total In minor units
 * @property string $currency
 * @property string|null $notes
 * @property CarbonInterface $issued_on
 * @property CarbonInterface|null $due_on
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $voided_at
 * @property int|null $created_by_id
 * @property CarbonInterface|null $created_at
 * @property-read Booking $booking
 * @property-read Quote|null $quote
 * @property-read Collection<int, LineItem> $lines
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasLineItems, HasUlids;

    protected $table = 'bookings_invoices';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'booking_id',
        'quote_id',
        'status',
        'subtotal',
        'tax_rate',
        'tax',
        'total',
        'currency',
        'notes',
        'issued_on',
        'due_on',
        'sent_at',
        'voided_at',
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
            'status' => InvoiceStatus::class,
            'subtotal' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax' => 'integer',
            'total' => 'integer',
            'issued_on' => 'date',
            'due_on' => 'date',
            'sent_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Its status now, given what's still owed on it: paid when nothing is, overdue when something
     * is after its due date
     */
    public function currentStatus(int $owed): InvoiceStatus
    {
        return match (true) {
            $this->status === InvoiceStatus::Void => InvoiceStatus::Void,
            $owed === 0 => InvoiceStatus::Paid,
            $this->due_on !== null && $this->due_on->lt(today()) => InvoiceStatus::Overdue,
            default => $this->status,
        };
    }
}
