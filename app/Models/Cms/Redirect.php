<?php

namespace App\Models\Cms;

use App\Enums\Cms\RedirectStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Cms\RedirectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sends visitors from one URL on the site to another. Applied by the headless API (#54), which
 * returns the redirect for a requested URL; this app doesn't serve the site itself.
 *
 * @property int $id
 * @property string $url_from A path on the site, e.g. /old-page
 * @property string $url_to A path on the site, or a full http(s) URL
 * @property bool $is_enabled
 * @property bool $is_permanent
 * @property Carbon|null $published_at
 * @property Carbon|null $expired_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    protected $table = 'cms_redirects';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'url_from',
        'url_to',
        'is_enabled',
        'is_permanent',
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
            'is_permanent' => 'boolean',
            'published_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /**
     * Tidies a path so the same page is always written the same way: a leading slash, no trailing
     * slash (except for the home page) and no repeated slashes. A pasted full URL for this site is
     * reduced to its path.
     */
    public static function normalisePath(string $url): string
    {
        $url = trim($url);

        if (preg_match('#^https?://#i', $url)) {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);
            $url = $path.($query ? '?'.$query : '');
        }

        $url = (string) preg_replace('#/{2,}#', '/', '/'.ltrim($url, '/'));

        return $url === '/' ? $url : (string) Str::of($url)->replaceMatches('#/+(\?|$)#', '$1');
    }

    /**
     * Tidies a destination: full http(s) URLs (e.g. to another site) are kept as they are, and
     * paths on this site are normalised like `url_from`
     */
    public static function normaliseDestination(string $url): string
    {
        $url = trim($url);

        return self::isExternal($url) ? $url : self::normalisePath($url);
    }

    public static function isExternal(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }

    /**
     * 301 for permanent redirects, 302 for temporary ones
     */
    public function httpStatus(): int
    {
        return $this->is_permanent ? 301 : 302;
    }

    public function status(): RedirectStatus
    {
        $now = CarbonImmutable::now();

        return match (true) {
            ! $this->is_enabled => RedirectStatus::Disabled,
            $this->published_at?->isAfter($now) ?? false => RedirectStatus::Scheduled,
            $this->expired_at?->isBefore($now) ?? false => RedirectStatus::Expired,
            default => RedirectStatus::Active,
        };
    }

    /**
     * Redirects with the given status, matching status()
     *
     * @param  Builder<Redirect>  $query
     * @return Builder<Redirect>
     */
    public function scopeWithStatus(Builder $query, RedirectStatus $status): Builder
    {
        $now = now();

        return match ($status) {
            RedirectStatus::Disabled => $query->where('is_enabled', false),
            RedirectStatus::Scheduled => $query->where('is_enabled', true)->where('published_at', '>', $now),
            RedirectStatus::Expired => $query->where('is_enabled', true)
                ->where(fn (Builder $query) => $query->whereNull('published_at')->orWhere('published_at', '<=', $now))
                ->where('expired_at', '<', $now),
            RedirectStatus::Active => $query->where('is_enabled', true)
                ->where(fn (Builder $query) => $query->whereNull('published_at')->orWhere('published_at', '<=', $now))
                ->where(fn (Builder $query) => $query->whereNull('expired_at')->orWhere('expired_at', '>=', $now)),
        };
    }

    /**
     * Redirects that currently apply
     *
     * @param  Builder<Redirect>  $query
     * @return Builder<Redirect>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $this->scopeWithStatus($query, RedirectStatus::Active);
    }
}
