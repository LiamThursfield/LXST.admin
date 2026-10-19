<?php

namespace App\Http\Controllers\Tenant\Public\Dj;

use App\Http\Controllers\Controller;
use App\Models\Dj\Gig;
use App\Models\Dj\SongList;
use App\Models\Settings\DjSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The hosted request page on the tenant's domain, for DJs without their own site (#123). The pages
 * call the same public API as headless sites, so they only need to know which gig or setlist.
 * They're kept out of search engines.
 */
class HostedRequestController extends Controller
{
    public function __construct(protected DjSettings $settings) {}

    /**
     * Always the current gig
     */
    public function current(Request $request): Response
    {
        return $this->render($request, 'public/dj/Request', ['gigId' => null]);
    }

    /**
     * One gig, by its public ID, for QR codes and shared links
     */
    public function gig(Request $request, Gig $gig): Response
    {
        return $this->render($request, 'public/dj/Request', ['gigId' => $gig->public_id]);
    }

    /**
     * A setlist for guests with its pass (#147)
     */
    public function setlist(Request $request, SongList $songList): Response
    {
        abort_unless($songList->isSetlist(), Response::HTTP_NOT_FOUND);

        return $this->render($request, 'public/dj/Setlist', ['setlistId' => $songList->public_id]);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected function render(Request $request, string $component, array $props): Response
    {
        abort_unless($this->settings->hosted_page_enabled, Response::HTTP_NOT_FOUND);

        $response = Inertia::render($component, $props)->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
