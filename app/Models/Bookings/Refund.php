<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\RefundStatus;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money given back from a payment: through Stripe for an online payment, or by hand for one taken in person
 *
 * @property int $id
 * @property int $payment_id
 * @property int $amount In minor units, e.g. pence
 * @property RefundStatus $status
 * @property string|null $stripe_refund_id
 * @property int $application_fee_refunded In minor units
 * @property string|null $reason
 * @property CarbonInterface|null $refunded_at
 * @property int|null $created_by_id
 * @property CarbonInterface|null $created_at
 * @property-read Payment $payment
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected $table = 'bookings_refunds';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'amount',
        'status',
        'stripe_refund_id',
        'application_fee_refunded',
        'reason',
        'refunded_at',
        'created_by_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RefundStatus::class,
            'application_fee_refunded' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
