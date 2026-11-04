<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\ServicePriceType;
use App\Enums\BookingType;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use Database\Factories\Bookings\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Something that can be booked or asked about. Its booking type decides which details apply: see
 * BookingType::details().
 *
 * @property int $id
 * @property int|null $category_id
 * @property int|null $image_file_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property BookingType $booking_type
 * @property ServicePriceType $price_type
 * @property int|null $price In minor units, e.g. pence. A hire's price is per hire unit.
 * @property int|null $duration_minutes
 * @property int $buffer_before_minutes
 * @property int $buffer_after_minutes
 * @property int|null $capacity
 * @property int|null $min_party_size
 * @property int|null $max_party_size
 * @property HireUnit|null $hire_unit
 * @property int|null $min_hire_units
 * @property int|null $max_hire_units
 * @property int|null $travel_minutes
 * @property bool $is_bookable_online
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    /** Every price is in this currency until a tenant can choose its own */
    public const string CURRENCY = 'GBP';

    protected $table = 'bookings_services';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'image_file_id',
        'name',
        'slug',
        'description',
        'booking_type',
        'price_type',
        'price',
        'duration_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'capacity',
        'min_party_size',
        'max_party_size',
        'hire_unit',
        'min_hire_units',
        'max_hire_units',
        'travel_minutes',
        'is_bookable_online',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_type' => BookingType::class,
            'price_type' => ServicePriceType::class,
            'price' => 'integer',
            'duration_minutes' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'capacity' => 'integer',
            'min_party_size' => 'integer',
            'max_party_size' => 'integer',
            'hire_unit' => HireUnit::class,
            'min_hire_units' => 'integer',
            'max_hire_units' => 'integer',
            'travel_minutes' => 'integer',
            'is_bookable_online' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Generate a unique slug from the name when one isn't given
        static::saving(function (Service $service) {
            if (blank($service->slug)) {
                $service->slug = static::uniqueSlugFor($service->name, $service->id);
            }
        });
    }

    /**
     * Returns a slug for the name that no other service uses, including deleted ones, adding a
     * numeric suffix if needed
     */
    public static function uniqueSlugFor(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(File::class, 'image_file_id');
    }

    /**
     * @return HasMany<ServiceOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ServiceOption::class)->orderBy('order');
    }

    /**
     * The locations chosen for this service. None chosen means it's offered at every location.
     *
     * @return BelongsToMany<OrganisationUnit, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(OrganisationUnit::class, 'bookings_service_location', 'service_id', 'organisation_unit_id');
    }

    /**
     * Scope to the services offered at a location: those choosing it, and those with no locations chosen
     *
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeOfferedAt(Builder $query, OrganisationUnit $location): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereDoesntHave('locations')
            ->orWhereHas('locations', fn (Builder $query) => $query->whereKey($location->id)));
    }
}
