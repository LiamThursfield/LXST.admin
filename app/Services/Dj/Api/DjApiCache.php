<?php

namespace App\Services\Dj\Api;

use App\Enums\Api\ContentScope;
use App\Models\Dj\Gig;
use App\Services\Cms\Api\ContentVersion;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches DJ API responses by the DJ content version, for a short time, as what's current changes
 * with the clock (a gig starting, or its requests opening) as well as with admin changes. A gig's
 * request list also has its own version, as visitors' requests and votes change it.
 */
class DjApiCache
{
    /**
     * @template TValue
     *
     * @param  string  $name  What's being cached, unique to the endpoint and its parameters
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    public function remember(string $name, Closure $build): mixed
    {
        return Cache::remember(
            'dj-api:'.ContentVersion::for(ContentScope::Dj)->current().':'.sha1($name),
            (int) config('dj.api.cache_seconds'),
            $build,
        );
    }

    /**
     * Like remember(), and also refreshed by refreshRequests() for the gig
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    public function rememberRequests(Gig $gig, string $name, Closure $build): mixed
    {
        return $this->remember("requests|{$gig->id}|{$this->requestsVersion($gig)}|{$name}", $build);
    }

    /**
     * Marks the gig's cached request list as stale, after a new request or vote
     */
    public function refreshRequests(Gig $gig): void
    {
        Cache::forever($this->requestsVersionKey($gig), (string) Str::ulid());
    }

    protected function requestsVersion(Gig $gig): string
    {
        return Cache::rememberForever($this->requestsVersionKey($gig), fn () => (string) Str::ulid());
    }

    protected function requestsVersionKey(Gig $gig): string
    {
        return "dj-api:requests-version:{$gig->id}";
    }
}
