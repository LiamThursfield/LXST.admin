<?php

namespace App\Models\Crm;

use App\Enums\Crm\OrganisationUnitType;
use Database\Factories\Crm\OrganisationUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A company, location or department. The hierarchy is stored only as the parent chain,
 * so a unit's company and location are derived by walking up its parents.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property OrganisationUnitType $type
 * @property string $name
 * @property string $slug
 * @property string $email
 * @property string|null $telephone
 * @property string|null $mobile_phone
 * @property array<string, string>|null $socials
 * @property list<string>|null $notification_emails
 * @property-read OrganisationUnit|null $parent
 * @property-read Collection<int, OrganisationUnit> $children
 * @property-read Address|null $address
 * @property-read int|null $children_count
 */
class OrganisationUnit extends Model
{
    /** @use HasFactory<OrganisationUnitFactory> */
    use HasFactory;

    protected $table = 'crm_organisation_units';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'slug',
        'email',
        'telephone',
        'mobile_phone',
        'socials',
        'notification_emails',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganisationUnitType::class,
            'socials' => 'array',
            'notification_emails' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Generate a unique slug from the name when one isn't given
        static::saving(function (OrganisationUnit $unit) {
            if (blank($unit->slug)) {
                $unit->slug = static::uniqueSlugFor($unit->name, $unit->id);
            }
        });
    }

    /**
     * Returns a slug for the name that no other unit uses, adding a numeric suffix if needed
     */
    public static function uniqueSlugFor(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (static::query()
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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class, 'parent_id');
    }

    /**
     * @return HasMany<OrganisationUnit, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(OrganisationUnit::class, 'parent_id');
    }

    /**
     * @return MorphOne<Address, $this>
     */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    /**
     * The unit's parents, nearest first
     *
     * @return Collection<int, OrganisationUnit>
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent !== null && ! $ancestors->contains('id', $parent->id)) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * The company this unit belongs to: itself if it is a company, otherwise its nearest company ancestor
     */
    public function company(): ?OrganisationUnit
    {
        return $this->nearestOfType(OrganisationUnitType::Company);
    }

    /**
     * The location this unit belongs to: itself if it is a location, otherwise its nearest location ancestor
     */
    public function location(): ?OrganisationUnit
    {
        return $this->nearestOfType(OrganisationUnitType::Location);
    }

    /**
     * Returns true if the given unit is somewhere in this unit's parent chain
     */
    public function isDescendantOf(OrganisationUnit $unit): bool
    {
        return $this->ancestors()->contains('id', $unit->id);
    }

    /**
     * Returns this unit or its nearest ancestor of the given type
     */
    protected function nearestOfType(OrganisationUnitType $type): ?OrganisationUnit
    {
        if ($this->type === $type) {
            return $this;
        }

        return $this->ancestors()->first(fn (OrganisationUnit $unit) => $unit->type === $type);
    }
}
