<?php

namespace App\Models\Bookings;

use App\Models\Crm\OrganisationUnit;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\ServiceSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scheduled time of a class or event, which bookings take places on
 *
 * @property int $id
 * @property int $service_id
 * @property int|null $location_id
 * @property CarbonInterface $starts_at In UTC
 * @property CarbonInterface $ends_at In UTC
 * @property int|null $capacity Null uses the service's
 */
class ServiceSession extends Model
{
    /** @use HasFactory<ServiceSessionFactory> */
    use HasFactory;

    protected $table = 'bookings_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'location_id',
        'starts_at',
        'ends_at',
        'capacity',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
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
     * @return BelongsTo<OrganisationUnit, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class, 'location_id');
    }

    /**
     * @return BelongsToMany<BookableResource, $this>
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(BookableResource::class, 'bookings_session_resource', 'session_id', 'resource_id');
    }

    /**
     * @return HasMany<WaitlistEntry, $this>
     */
    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class, 'session_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'session_id');
    }
}
