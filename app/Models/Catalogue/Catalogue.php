<?php

namespace App\Models\Catalogue;

use Carbon\CarbonInterface;
use Database\Factories\Catalogue\CatalogueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What a business shows or sells beyond its bookable services: a restaurant's menu, a salon's price
 * list, a shop's products. Split into sections of items, and can apply only at certain times.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_published
 * @property list<int>|null $available_days ISO days of the week, 1 being Monday. Null is every day.
 * @property string|null $available_from E.g. "12:00:00"
 * @property string|null $available_until Before the start when it runs past midnight
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int $order
 * @property-read Collection<int, Section> $sections
 * @property-read Collection<int, Item> $items
 * @property-read int|null $sections_count When loaded with withCount()
 * @property-read int|null $items_count When loaded with withCount()
 */
class Catalogue extends Model
{
    /** @use HasFactory<CatalogueFactory> */
    use HasFactory;

    /** Every price is in this currency until a tenant can choose its own */
    public const string CURRENCY = 'GBP';

    protected $table = 'catalogue_catalogues';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_published',
        'available_days',
        'available_from',
        'available_until',
        'starts_on',
        'ends_on',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'available_days' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Generate a unique slug from the name when one isn't given
        static::saving(function (Catalogue $catalogue) {
            if (blank($catalogue->slug)) {
                $catalogue->slug = static::uniqueSlugFor($catalogue->name, $catalogue->id);
            }
        });
    }

    /**
     * Returns a slug for the name that no other catalogue uses, adding a numeric suffix if needed
     */
    public static function uniqueSlugFor(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'catalogue';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order')->orderBy('id');
    }

    /**
     * @return HasManyThrough<Item, Section, $this>
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(Item::class, Section::class);
    }

    /**
     * Whether it has any limit on when it applies
     */
    public function hasSchedule(): bool
    {
        return $this->available_days !== null
            || $this->available_from !== null
            || $this->available_until !== null
            || $this->starts_on !== null
            || $this->ends_on !== null;
    }

    /**
     * Whether it's published and applies at the given time, e.g. a lunch menu at 1pm on a weekday.
     * A time range that runs past midnight belongs to the day it started on.
     */
    public function appliesAt(CarbonInterface $at): bool
    {
        if (! $this->is_published) {
            return false;
        }

        $time = $at->format('H:i:s');
        $from = $this->available_from;
        $until = $this->available_until;
        $day = $at;

        if ($from !== null && $until !== null && $until <= $from) {
            // Overnight, e.g. 22:00 to 02:00: after midnight it's still the evening before's
            if ($time >= $from) {
                $day = $at;
            } elseif ($time < $until) {
                $day = $at->copy()->subDay();
            } else {
                return false;
            }
        } elseif (($from !== null && $time < $from) || ($until !== null && $time >= $until)) {
            return false;
        }

        if ($this->available_days !== null && ! in_array($day->isoWeekday(), $this->available_days, true)) {
            return false;
        }

        $date = $day->toDateString();

        return ($this->starts_on === null || $date >= $this->starts_on->toDateString())
            && ($this->ends_on === null || $date <= $this->ends_on->toDateString());
    }

    /**
     * When it applies, in words, e.g. "Mon, Tue, Wed · 12:00–15:00 · 1 Dec 2026 to 31 Dec 2026", or
     * null when it always does
     */
    public function scheduleSummary(): ?string
    {
        $parts = [];

        if ($this->available_days !== null) {
            $days = $this->available_days;
            sort($days);

            $parts[] = count($days) === 7
                ? __('catalogue.schedule.every_day')
                : implode(', ', array_map(fn (int $day) => __("catalogue.schedule.days.{$day}"), $days));
        }

        if ($this->available_from !== null || $this->available_until !== null) {
            $parts[] = match (true) {
                $this->available_until === null => __('catalogue.schedule.from_time', ['time' => substr((string) $this->available_from, 0, 5)]),
                $this->available_from === null => __('catalogue.schedule.until_time', ['time' => substr($this->available_until, 0, 5)]),
                default => substr($this->available_from, 0, 5).'–'.substr($this->available_until, 0, 5),
            };
        }

        if ($this->starts_on !== null || $this->ends_on !== null) {
            $parts[] = match (true) {
                $this->ends_on === null => __('catalogue.schedule.from_date', ['date' => $this->starts_on->isoFormat('D MMM YYYY')]),
                $this->starts_on === null => __('catalogue.schedule.until_date', ['date' => $this->ends_on->isoFormat('D MMM YYYY')]),
                default => __('catalogue.schedule.between_dates', [
                    'start' => $this->starts_on->isoFormat('D MMM YYYY'),
                    'end' => $this->ends_on->isoFormat('D MMM YYYY'),
                ]),
            };
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
