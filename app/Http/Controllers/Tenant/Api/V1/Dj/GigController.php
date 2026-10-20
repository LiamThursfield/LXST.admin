<?php

namespace App\Http\Controllers\Tenant\Api\V1\Dj;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Dj\PublicGigResource;
use App\Models\Dj\Gig;
use App\Services\Crm\FormSpamCheck;
use App\Services\Dj\Api\DjApiCache;
use App\Services\Dj\CurrentGig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a site needs to show a request form: the gig and its request settings
 */
class GigController extends Controller
{
    public function __construct(protected DjApiCache $cache) {}

    /**
     * The current gig (pinned as live, on now, or up next), or null when there isn't one
     */
    public function current(Request $request, CurrentGig $currentGig): JsonResponse
    {
        $gig = $currentGig->resolve();

        return response()->json($this->withRequestForm($gig === null ? ['data' => null] : $this->details($request, $gig)));
    }

    /**
     * A gig by its public ID, for shared links and QR codes. Past gigs still show, with requests closed.
     */
    public function show(Request $request, Gig $gig): JsonResponse
    {
        return response()->json($this->withRequestForm($this->details($request, $gig)));
    }

    /**
     * Cached per gig, as what's playing now (#125) changes with each play
     *
     * @return array{data: array<string, mixed>}
     */
    protected function details(Request $request, Gig $gig): array
    {
        return $this->cache->rememberForGig($gig, 'details', fn (): array => [
            'data' => (new PublicGigResource($gig))->resolve($request),
        ]);
    }

    /**
     * Adds the request form's spam protection, outside the cache, as the token records when the
     * form was loaded: render the honeypot as a hidden field, and send the token back unchanged
     *
     * @param  array{data: array<string, mixed>|null}  $response
     * @return array{data: array<string, mixed>|null}
     */
    protected function withRequestForm(array $response): array
    {
        if ($response['data'] !== null) {
            $response['data']['requests']['spam_protection'] = [
                'honeypot_field' => config('dj.requests.honeypot_field'),
                'token_field' => config('dj.requests.token_field'),
                'token' => FormSpamCheck::for('dj.requests')->issueToken(),
            ];
        }

        return $response;
    }
}
