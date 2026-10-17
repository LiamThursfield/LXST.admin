<?php

namespace App\Http\Middleware;

use App\Services\Cms\Api\ContentVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an admin change that could affect the website (CMS, CRM, File Manager or site settings),
 * marks the headless API's cached responses as stale. Done per request rather than per model event, as some
 * changes (e.g. syncing content) use queries that don't fire model events.
 */
class BumpsContentVersion
{
    public function __construct(protected ContentVersion $version) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodSafe() && $response->getStatusCode() < 400 && ! $this->failedValidation($request)) {
            $this->version->bump();
        }

        return $response;
    }

    /**
     * Inertia validation failures redirect back with errors (a 302), so they look successful.
     * Only errors flashed by this request count: the previous request's are still readable.
     */
    protected function failedValidation(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        /** @var array<int, string> $flashed */
        $flashed = $request->session()->get('_flash.new', []);

        return in_array('errors', $flashed, true);
    }
}
