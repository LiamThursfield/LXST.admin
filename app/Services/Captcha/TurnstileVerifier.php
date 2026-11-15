<?php

namespace App\Services\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Checks Cloudflare Turnstile tokens with Laravel's HTTP client rather than an SDK.
 * Tokens last five minutes and can only be checked once.
 */
class TurnstileVerifier implements CaptchaVerifier
{
    public const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(string $token, string $secretKey, ?string $ip = null): CaptchaResult
    {
        try {
            $response = Http::asForm()
                ->timeout((int) config('api.captcha.timeout_seconds'))
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException $exception) {
            Log::warning('Turnstile could not be reached to check a captcha token.', ['error' => $exception->getMessage()]);

            return CaptchaResult::Unavailable;
        }

        if ($response->failed()) {
            Log::warning('Turnstile returned an error checking a captcha token.', ['status' => $response->status()]);

            return CaptchaResult::Unavailable;
        }

        if ($response->json('success') === true) {
            return CaptchaResult::Passed;
        }

        /** @var list<string> $errors */
        $errors = (array) $response->json('error-codes', []);

        // Our own setup is wrong (e.g. a mistyped secret key), not the visitor's token
        if (array_intersect($errors, ['missing-input-secret', 'invalid-input-secret', 'internal-error']) !== []) {
            Log::warning('Turnstile could not check a captcha token.', ['errors' => $errors]);

            return CaptchaResult::Unavailable;
        }

        return CaptchaResult::Failed;
    }
}
