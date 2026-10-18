<?php

namespace App\Services\Dj\Api;

use App\Enums\Api\ContentScope;
use App\Services\Cms\Api\ContentVersion;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches DJ API responses by the DJ content version, for a short time, as what's current changes
 * with the clock (a gig starting, or its requests opening) as well as with admin changes
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
}
