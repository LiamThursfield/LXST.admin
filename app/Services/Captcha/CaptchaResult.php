<?php

namespace App\Services\Captcha;

/**
 * What the captcha provider made of a visitor's token
 */
enum CaptchaResult
{
    case Passed;

    /** The token was missing, invalid, expired or already used */
    case Failed;

    /** The provider couldn't be reached or gave an error, so the token couldn't be checked */
    case Unavailable;
}
