<?php

namespace App\Http\Middleware;

use App\Models\Dj\SongList;
use App\Services\Dj\GuestPasses\GuestTokens;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a guest in with the token they got for a guest pass, sent in the `X-Guest-Token` header,
 * for the route's song list. The pass is put on the request as the `guest_pass` attribute.
 *
 * Usage: Route::middleware('dj.guest-pass')
 */
class AuthenticateGuestPass
{
    public const string HEADER = 'X-Guest-Token';

    public function __construct(protected GuestTokens $tokens) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $list = $request->route('songList');
        $pass = $list instanceof SongList ? $this->tokens->resolve($request->header(self::HEADER), $list->id) : null;

        abort_if($pass === null, Response::HTTP_UNAUTHORIZED, __('dj.guest_passes.api.unauthenticated'));

        $this->tokens->touch($pass);
        $request->attributes->set('guest_pass', $pass);

        return $next($request);
    }
}
