<?php

namespace App\Services\Captcha;

/**
 * Checks a captcha token with its provider
 */
interface CaptchaVerifier
{
    /**
     * @param  string  $secretKey  The tenant's secret key for the provider
     * @param  string|null  $ip  The visitor's IP address, which the provider may check the token against
     */
    public function verify(string $token, string $secretKey, ?string $ip = null): CaptchaResult;
}
