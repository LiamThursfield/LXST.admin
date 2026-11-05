<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\ResourceType;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use App\Models\User;
use Database\Factories\Bookings\BookableResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Who or what does the work: a person, a space, a piece of equipment or a vehicle
 *
 * @property int $id
 * @property ResourceType $type
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $image_file_id
 * @property int|null $location_id
 * @property int|null $user_id
 * @property string|null $job_title
 * @property int|null $capacity
 * @property string|null $combine_group
 * @property bool $is_active
 * @property bool $is_public
 */
class BookableResource extends Model
{
    /** @use HasFactory<BookableResourceFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'bookings_resources';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'slug',
        'description',
        'image_file_id',
        'location_id',
        'user_id',
        'job_title',
        'capacity',
        'combine_group',
        'is_active',
        'is_public',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Generate a unique slug from the name when one isn't given
        static::saving(function (BookableResource $resource) {
            if (blank($resource->slug)) {
                $resource->slug = static::uniqueSlugFor($resource->name, $resource->id);
            }
        });

        // Frees up the user, so they can be linked to someone new
        static::deleted(function (BookableResource $resource) {
            $resource->forceFill(['user_id' => null])->saveQuietly();
        });
    }

    /**
     * Returns a slug for the name that no other resource uses, including deleted ones, adding a
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
     * @return BelongsTo<OrganisationUnit, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class, 'location_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(File::class, 'image_file_id');
    }

    /**
     * The services it can do, with any price or duration of its own
     *
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'bookings_resource_service', 'resource_id', 'service_id')->withPivot(['price', 'duration_minutes']);
    }

    /**
     * @return HasMany<WorkingHour, $this>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class, 'resource_id');
    }

    /**
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'resource_id');
    }

    /**
     * @return HasMany<TimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(TimeOff::class, 'resource_id');
    }
}
