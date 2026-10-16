<?php

namespace App\Services\Cms\Api;

use App\Enums\Cms\PublishStatus;
use App\Models\Cms\Page;
use App\Models\Cms\Redirect;
use App\Models\Cms\Url;
use Carbon\CarbonImmutable;

/**
 * Finds what a requested URL on the site is: an active redirect (checked first, as legacy did), a
 * published page, or nothing
 */
class PageResolver
{
    public function redirect(string $url): ?Redirect
    {
        return Redirect::query()->active()->where('url_from', $url)->first();
    }

    public function page(string $url): ?Page
    {
        $match = Url::query()
            ->withStatus(PublishStatus::Published)
            ->where('urlable_type', Page::class)
            ->where('url_full', $url)
            ->first();

        return $match === null ? null : Page::query()->with(['url', 'metadata', 'template.fields', 'layout.template.fields', 'layout.content', 'content'])->find($match->urlable_id);
    }

    /**
     * When the next scheduled publish or expiry (of a page or a redirect) changes what's live, if
     * any is scheduled
     */
    public function nextScheduledChange(): ?CarbonImmutable
    {
        $now = now();
        $next = collect([
            Url::query()->where('published_at', '>', $now)->min('published_at'),
            Url::query()->where('expired_at', '>', $now)->min('expired_at'),
            Redirect::query()->where('published_at', '>', $now)->min('published_at'),
            Redirect::query()->where('expired_at', '>', $now)->min('expired_at'),
        ])->filter()->map(fn (mixed $date) => CarbonImmutable::parse((string) $date))->min();

        return $next instanceof CarbonImmutable ? $next : null;
    }
}
