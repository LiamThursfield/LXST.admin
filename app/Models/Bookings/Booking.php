<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\ServicePriceType;
use App\Models\Crm\Contact;
use App\Models\Crm\OrganisationUnit;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One customer's booking of a service, or their enquiry until a time is set
 *
 * @property int $id
 * @property int $service_id
 * @property int|null $session_id
 * @property int|null $location_id
 * @property int|null $contact_id
 * @property string $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property BookingStatus $status
 * @property CarbonInterface|null $starts_at In UTC
 * @property CarbonInterface|null $ends_at In UTC
 * @property CarbonInterface|null $blocked_from In UTC
 * @property CarbonInterface|null $blocked_until In UTC
 * @property string $timezone
 * @property int $quantity
 * @property bool $is_walk_in
 * @property string $service_name
 * @property ServicePriceType $price_type
 * @property int|null $price In minor units, e.g. pence
 * @property string|null $notes
 * @property int|null $created_by_id
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $table = 'bookings_bookings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'session_id',
        'location_id',
        'contact_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',
        'starts_at',
        'ends_at',
        'blocked_from',
        'blocked_until',
        'timezone',
        'quantity',
        'is_walk_in',
        'service_name',
        'price_type',
        'price',
        'notes',
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
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'blocked_from' => 'datetime',
            'blocked_until' => 'datetime',
            'quantity' => 'integer',
            'is_walk_in' => 'boolean',
            'price_type' => ServicePriceType::class,
            'price' => 'integer',
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
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Who and what it ties up, even when since deleted
     *
     * @return BelongsToMany<BookableResource, $this>
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(BookableResource::class, 'bookings_booking_resource', 'booking_id', 'resource_id')->withTrashed();
    }

    /**
     * @return HasMany<BookingOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(BookingOption::class);
    }

    /**
     * Whether it can be moved to another time or resource: it has a time of its own, rather than a
     * session's, and hasn't started or ended
     */
    public function isMovable(): bool
    {
        return $this->starts_at !== null
            && $this->session_id === null
            && in_array($this->status, [BookingStatus::Requested, BookingStatus::Confirmed], true);
    }

    /**
     * Whether this is an enquiry still waiting for a time
     */
    public function needsTime(): bool
    {
        return $this->starts_at === null;
    }
}
