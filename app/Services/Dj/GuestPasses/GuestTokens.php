<?php

namespace App\Services\Dj\GuestPasses;

use App\Models\Dj\GuestPass;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Short-lived guest tokens, exchanged for a guest pass's password. A token is the pass and an
 * expiry, encrypted, so nothing is stored; revoking or expiring the pass stops its tokens at
 * once, as every request checks the pass again.
 */
class GuestTokens
{
    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     */
    public function issue(GuestPass $pass): array
    {
        $expiresAt = CarbonImmutable::now()->addMinutes((int) config('dj.guest_passes.token_minutes'));

        $token = Crypt::encryptString((string) json_encode([
            'pass' => $pass->id,
            'list' => $pass->song_list_id,
            'expires' => $expiresAt->getTimestamp(),
        ]));

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * The active pass for a token, or null when it's invalid, expired, or for another list
     */
    public function resolve(?string $token, int $songListId): ?GuestPass
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            /** @var array{pass?: int, list?: int, expires?: int}|null $payload */
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload) || ($payload['list'] ?? null) !== $songListId || ($payload['expires'] ?? 0) < CarbonImmutable::now()->getTimestamp()) {
            return null;
        }

        $pass = GuestPass::query()->whereKey($payload['pass'] ?? 0)->where('song_list_id', $songListId)->first();

        return $pass?->isActive() ? $pass : null;
    }

    /**
     * Notes the pass was used, at most every few minutes so busy guests don't write every request
     */
    public function touch(GuestPass $pass): void
    {
        if ($pass->last_used_at === null || $pass->last_used_at->lt(now()->subMinutes(5))) {
            $pass->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }
}
