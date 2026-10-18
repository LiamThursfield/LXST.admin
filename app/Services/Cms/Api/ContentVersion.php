<?php

namespace App\Services\Cms\Api;

use App\Enums\Api\ContentScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A per-tenant marker that changes whenever an API's content might have, e.g. any CMS, CRM, File
 * Manager or site settings change in the admin for the CMS API. Cached API responses are keyed by
 * it, so a change refreshes every response at once: layouts, menus and forms appear on many pages,
 * so working out exactly which changed isn't worth it. Each scope has its own version, so a DJ
 * change doesn't refresh the CMS API. The cache is already per tenant (CacheTenancyBootstrapper).
 */
class ContentVersion
{
    public function __construct(protected ContentScope $scope = ContentScope::Cms) {}

    public static function for(ContentScope $scope): self
    {
        return new self($scope);
    }

    public function current(): string
    {
        return Cache::rememberForever($this->key(), fn () => (string) Str::ulid());
    }

    public function bump(): void
    {
        Cache::forever($this->key(), (string) Str::ulid());
    }

    protected function key(): string
    {
        return "{$this->scope->value}-api:content-version";
    }
}
