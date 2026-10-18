<?php

namespace App\Models\Dj;

use App\Data\Dj\EffectiveRequestSettings;
use App\Data\Dj\GigRequestSettings;
use App\Enums\Dj\RequestsMode;
use App\Models\Settings\DjSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Dj\GigFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A gig: the backbone for requests, setlists and stats. Times are stored in UTC and shown in the
 * gig's own timezone.
 *
 * @property int $id
 * @property string $name
 * @property string|null $venue
 * @property string|null $location
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property string|null $notes
 * @property bool $is_pinned Pinned as live: the current gig, whatever the time
 * @property GigRequestSettings|null $request_settings Only the settings this gig overrides
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Gig extends Model
{
    /** @use HasFactory<GigFactory> */
    use HasFactory;

    protected $table = 'dj_gigs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'venue',
        'location',
        'starts_at',
        'ends_at',
        'timezone',
        'notes',
        'is_pinned',
        'request_settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_pinned' => 'boolean',
            'request_settings' => GigRequestSettings::class,
        ];
    }

    /**
     * Gigs on at the given moment
     *
     * @param  Builder<Gig>  $query
     */
    public function scopeOnAt(Builder $query, CarbonInterface $at): void
    {
        $query->where('starts_at', '<=', $at)->where('ends_at', '>', $at);
    }

    /**
     * Other gigs whose times overlap this one's
     *
     * @param  Builder<Gig>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $exceptId = null): void
    {
        $query->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId));
    }

    public function requestSettings(): EffectiveRequestSettings
    {
        return ($this->request_settings ?? new GigRequestSettings)->effective(app(DjSettings::class));
    }

    /**
     * Whether visitors can make requests at the given moment
     */
    public function requestsOpenAt(CarbonInterface $at): bool
    {
        $settings = $this->requestSettings();

        return match ($settings->requests_mode) {
            RequestsMode::Open => true,
            RequestsMode::Closed => false,
            RequestsMode::Scheduled => $at->greaterThanOrEqualTo($this->starts_at->subMinutes($settings->requests_open_minutes_before))
                && $at->lessThan($this->ends_at),
        };
    }

    public function isOnAt(CarbonInterface $at): bool
    {
        return $at->greaterThanOrEqualTo($this->starts_at) && $at->lessThan($this->ends_at);
    }

    /**
     * Where visitors request songs for this gig, if the DJ settings say where the request page is
     */
    public function requestUrl(): ?string
    {
        $url = app(DjSettings::class)->request_page_url;

        if ($url === null || trim($url) === '') {
            return null;
        }

        return str_replace('{gig}', (string) $this->id, $url);
    }
}
