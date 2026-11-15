<?php

namespace App\Http\Controllers\Tenant\Api\V1\Dj;

use App\Enums\Dj\RequestOutcome;
use App\Enums\Dj\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Dj\SongRequestStoreRequest;
use App\Models\Dj\Gig;
use App\Models\Dj\SongListItem;
use App\Services\Captcha\Captcha;
use App\Services\Captcha\CaptchaResult;
use App\Services\Cms\Api\VisitorPrivacy;
use App\Services\Crm\FormSpamCheck;
use App\Services\Dj\Requests\RequestAccess;
use App\Services\Dj\Requests\RequestCaptcha;
use App\Services\Dj\Requests\SongRequester;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visitors requesting songs for a gig. When the gig's requests have guest passes, the visitor
 * sends the token for one (#147) in the `X-Guest-Token` header.
 */
class SongRequestController extends Controller
{
    /**
     * Spam gets the same response as a new request, but isn't saved, so bots learn nothing.
     *
     * When the gig asks for a captcha (#74), a visitor's first request needs one; a missing or
     * failed captcha is refused, so a real visitor can try again.
     *
     * @throws ValidationException when the visitor's captcha is needed and fails
     */
    public function store(
        SongRequestStoreRequest $request,
        Gig $gig,
        SongRequester $requester,
        RequestAccess $access,
        VisitorPrivacy $privacy,
        RequestCaptcha $captcha,
    ): JsonResponse {
        if (FormSpamCheck::for('dj.requests')->looksAutomated($request) || $privacy->isBot($request->visitorUserAgent())) {
            $unsaved = new SongListItem([
                ...$request->safe()->only(['canonical_track_id', 'custom_artist', 'custom_title']),
                'status' => RequestStatus::Pending,
            ]);
            $unsaved->setAttribute('votes_count', 1);

            return $this->respond(RequestOutcome::Accepted, $this->requestData($unsaved->load('song.artist')));
        }

        if ($captcha->check($request, $gig) === CaptchaResult::Failed) {
            throw ValidationException::withMessages([
                Captcha::tokenField() => __('dj.api.captcha_failed'),
            ]);
        }

        $result = $requester->submit($gig, $request->songRequest($gig, $access->pass($request, $gig)));
        $item = $result['item']->load('song.artist')->loadCount('votes');

        return $this->respond($result['outcome'], $this->requestData($item));
    }

    /**
     * @return array{title: string, artist: string|null, status: string|null, votes: int}
     */
    protected function requestData(SongListItem $item): array
    {
        return [
            'title' => $item->title(),
            'artist' => $item->artist(),
            'status' => $item->status?->value,
            'votes' => (int) $item->getAttribute('votes_count'),
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     */
    protected function respond(RequestOutcome $outcome, array $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'outcome' => $outcome->value,
                'message' => $outcome->message(),
                'request' => $request,
            ],
        ], $outcome === RequestOutcome::Accepted ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
