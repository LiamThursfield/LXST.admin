<?php

namespace App\Models\Cms;

use App\Enums\Cms\PublishStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Cms\UrlFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Where something (a page) lives on the site, and when it's live. `url_main` is the item's own
 * part (e.g. "/team") and `url_full` the whole path including its parents (e.g. "/about/team").
 *
 * @property int $id
 * @property string $urlable_type
 * @property int $urlable_id
 * @property string $url_main
 * @property string $url_full
 * @property bool $is_enabled
 * @property Carbon|null $published_at
 * @property Carbon|null $expired_at
 */
class Url extends Model
{
    /** @use HasFactory<UrlFactory> */
    use HasFactory;

    protected $table = 'cms_urls';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'url_main',
        'url_full',
        'is_enabled',
        'published_at',
        'expired_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'published_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function urlable(): MorphTo
    {
        return $this->morphTo();
    }

    public function status(): PublishStatus
    {
        $now = CarbonImmutable::now();

        return match (true) {
            ! $this->is_enabled => PublishStatus::Draft,
            $this->published_at?->isAfter($now) ?? false => PublishStatus::Scheduled,
            $this->expired_at?->isBefore($now) ?? false => PublishStatus::Expired,
            default => PublishStatus::Published,
        };
    }

    /**
     * URLs with the given status, matching status()
     *
     * @param  Builder<Url>  $query
     * @return Builder<Url>
     */
    public function scopeWithStatus(Builder $query, PublishStatus $status): Builder
    {
        $now = now();

        return match ($status) {
            PublishStatus::Draft => $query->where('is_enabled', false),
            PublishStatus::Scheduled => $query->where('is_enabled', true)->where('published_at', '>', $now),
            PublishStatus::Expired => $query->where('is_enabled', true)
                ->where(fn (Builder $query) => $query->whereNull('published_at')->orWhere('published_at', '<=', $now))
                ->where('expired_at', '<', $now),
            PublishStatus::Published => $query->where('is_enabled', true)
                ->where(fn (Builder $query) => $query->whereNull('published_at')->orWhere('published_at', '<=', $now))
                ->where(fn (Builder $query) => $query->whereNull('expired_at')->orWhere('expired_at', '>=', $now)),
        };
    }
}
