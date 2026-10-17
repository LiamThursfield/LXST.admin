<?php

namespace App\Services\Cms\Api;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches headless API responses. Keys include the content version, so any admin change refreshes
 * every response at once, and the next scheduled publish or expiry, so once that passes the key
 * changes and the response is built again, whatever the cache store's own expiry does.
 */
class ApiResponseCache
{
    /** How long a response is cached at most */
    protected const int CACHE_SECONDS = 3600;

    public function __construct(
        protected ContentVersion $version,
        protected PageResolver $resolver,
    ) {}

    /**
     * @param  string  $name  What's being cached, unique to the endpoint and its parameters
     * @param  Closure(): array{status: int, body: array<string, mixed>}  $build
     * @return array{status: int, body: array<string, mixed>}
     */
    public function remember(string $name, Closure $build): array
    {
        $nextChange = $this->resolver->nextScheduledChange();
        $key = 'cms-api:'.$this->version->current().':'.($nextChange?->getTimestamp() ?? 'none').':'.sha1($name);
        $seconds = $nextChange === null
            ? self::CACHE_SECONDS
            : (int) max(1, min(self::CACHE_SECONDS, now()->diffInSeconds($nextChange)));

        /** @var array{status: int, body: array<string, mixed>} */
        return Cache::remember($key, $seconds, $build);
    }
}
