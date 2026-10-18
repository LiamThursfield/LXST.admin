<?php

namespace App\Services\Dj\Requests;

use App\Enums\Dj\RequestRefusal;
use App\Http\Middleware\AuthenticateGuestPass;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Services\Dj\GuestPasses\GuestTokens;
use App\Services\Dj\Requests\Exceptions\RequestRefused;
use Illuminate\Http\Request;

/**
 * Who may use a gig's requests: anyone, unless its requests list has active guest passes (#147),
 * when visitors send the token for one in the `X-Guest-Token` header
 */
class RequestAccess
{
    public function __construct(protected GuestTokens $tokens) {}

    /**
     * The visitor's guest pass for the gig's requests, if they sent a valid token
     */
    public function pass(Request $request, Gig $gig): ?GuestPass
    {
        $list = $gig->requestsList;
        $pass = $list === null ? null : $this->tokens->resolve($request->header(AuthenticateGuestPass::HEADER), $list->id);

        if ($pass !== null) {
            $this->tokens->touch($pass);
        }

        return $pass;
    }

    /**
     * @throws RequestRefused when the gig's requests need a pass and there isn't one
     */
    public function ensureAllowed(Gig $gig, ?GuestPass $pass): void
    {
        if ($pass === null && $gig->requestsList?->guestPasses()->active()->exists()) {
            throw new RequestRefused(RequestRefusal::PassRequired);
        }
    }
}
