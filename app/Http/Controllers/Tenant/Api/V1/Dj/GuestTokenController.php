<?php

namespace App\Http\Controllers\Tenant\Api\V1\Dj;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Dj\GuestTokenStoreRequest;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use App\Services\Dj\GuestPasses\GuestTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exchanges a song list's guest pass password for a short-lived guest token
 */
class GuestTokenController extends Controller
{
    /**
     * Repeated wrong passwords for a list lock the IP address out of it for a while
     */
    public function store(GuestTokenStoreRequest $request, SongList $songList, GuestTokens $tokens): JsonResponse
    {
        $lockKey = 'dj-guest-pass:'.tenant()?->getTenantKey().':'.$songList->id.':'.$request->ip();
        $maxAttempts = (int) config('dj.guest_passes.max_attempts');

        if (RateLimiter::tooManyAttempts($lockKey, $maxAttempts)) {
            abort(Response::HTTP_TOO_MANY_REQUESTS, __('dj.guest_passes.api.locked', [
                'minutes' => (int) ceil(RateLimiter::availableIn($lockKey) / 60),
            ]));
        }

        $password = (string) $request->validated('password');
        $pass = $songList->guestPasses()->active()->get()->first(fn (GuestPass $pass) => $pass->matches($password));

        if ($pass === null) {
            RateLimiter::hit($lockKey, (int) config('dj.guest_passes.lockout_minutes') * 60);

            throw ValidationException::withMessages(['password' => __('dj.guest_passes.api.wrong_password')]);
        }

        RateLimiter::clear($lockKey);
        $tokens->touch($pass);
        $issued = $tokens->issue($pass);

        return response()->json([
            'data' => [
                'token' => $issued['token'],
                'expires_at' => $issued['expires_at']->toIso8601String(),
                'list' => ['id' => $songList->public_id, 'kind' => $songList->kind->value, 'name' => $songList->name],
                'pass' => ['label' => $pass->label, 'can_add' => $pass->can_add],
            ],
        ], Response::HTTP_CREATED);
    }
}
