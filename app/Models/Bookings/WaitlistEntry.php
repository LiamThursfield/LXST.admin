<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\WaitlistStatus;
use App\Models\Crm\OrganisationUnit;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone waiting for a place on a full timetable entry, or for any time on a date. Staff book them
 * when there's room, until messages to customers (#179) can offer it to them.
 *
 * @property int $id
 * @property int $service_id
 * @property int|null $session_id
 * @property CarbonInterface|null $wanted_on
 * @property int|null $location_id
 * @property string $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property int $quantity
 * @property string|null $notes
 * @property WaitlistStatus $status
 * @property int|null $booking_id
 * @property int|null $created_by_id
 */
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory;

    protected $table = 'bookings_waitlist_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'session_id',
        'wanted_on',
        'location_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'quantity',
        'notes',
        'status',
        'booking_id',
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
            'wanted_on' => 'date',
            'quantity' => 'integer',
            'status' => WaitlistStatus::class,
        ];
    }

    /**
     * The service, even when since deleted
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ServiceSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ServiceSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<OrganisationUnit, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class, 'location_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
