<?php

namespace App\Http\Controllers\Tenant\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Cms\PageViewStoreRequest;
use App\Jobs\Cms\RecordPageView;
use App\Services\Cms\Api\VisitorPrivacy;
use Illuminate\Http\Response;

/**
 * Lets a site record a visit to one of its pages. Saving is queued, so the site isn't kept waiting.
 */
class PageViewController extends Controller
{
    /**
     * Bots and views of unpublished pages get the same response as real ones, but aren't saved
     */
    public function store(PageViewStoreRequest $request, VisitorPrivacy $privacy): Response
    {
        $userAgent = $request->visitorUserAgent();

        if (! $privacy->isBot($userAgent)) {
            $ip = $request->visitorIp();
            $viewedAt = now();

            // The full IP is dropped here, so it never reaches the queue or the database
            RecordPageView::dispatch([
                'url' => $request->sitePath(),
                'referrer' => $request->validated('referrer'),
                'user_agent' => $userAgent,
                'ip_address' => $privacy->truncateIp($ip),
                'visitor_hash' => $privacy->visitorHash($ip, $userAgent, $viewedAt),
                'session_id' => $request->validated('session_id'),
                'source' => $request->validated('source'),
                'additional_data' => $request->validated('additional_data'),
                'viewed_at' => $viewedAt,
            ]);
        }

        return response()->noContent(202);
    }
}
