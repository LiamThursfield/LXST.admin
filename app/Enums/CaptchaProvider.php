<?php

namespace App\Enums;

use App\Enums\Traits\HasLabels;
use App\Services\Captcha\CaptchaVerifier;
use App\Services\Captcha\TurnstileVerifier;

/**
 * The captcha services a tenant can protect public forms and song requests with (#74). The site
 * renders the provider's widget with the public site key and sends its token back; the API checks
 * the token with the provider using the secret key.
 */
enum CaptchaProvider: string
{
    use HasLabels;

    case Turnstile = 'turnstile';

    /**
     * Checks this provider's tokens. No default case, so a new provider can't be forgotten.
     */
    public function verifier(): CaptchaVerifier
    {
        return match ($this) {
            self::Turnstile => app(TurnstileVerifier::class),
        };
    }
}
