<?php

namespace App\Services\Cms\Api;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A per-tenant marker that changes whenever the site's content might have (any CMS, CRM or File
 * Manager change in the admin). Cached API responses are keyed by it, so a change refreshes every
 * page at once: layouts, menus and forms appear on many pages, so working out exactly which pages
 * changed isn't worth it. The cache is already per tenant (CacheTenancyBootstrapper).
 */
class ContentVersion
{
    protected const string KEY = 'cms-api:content-version';

    public function current(): string
    {
        return Cache::rememberForever(self::KEY, fn () => (string) Str::ulid());
    }

    public function bump(): void
    {
        Cache::forever(self::KEY, (string) Str::ulid());
    }
}
