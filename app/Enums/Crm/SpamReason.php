<?php

namespace App\Enums\Crm;

use App\Enums\Traits\HasLabels;

/**
 * Why a form submission was marked as spam
 */
enum SpamReason: string
{
    use HasLabels;

    /** The hidden field people leave empty was filled in */
    case Honeypot = 'honeypot';

    /** Submitted too soon after the form was loaded */
    case TooFast = 'too_fast';

    /** The form token had been tampered with */
    case InvalidToken = 'invalid_token';

    /** The captcha provider couldn't be reached, so the submission was kept but not trusted */
    case CaptchaUnavailable = 'captcha_unavailable';

    /** The contact who sent it is marked as spam */
    case SpamContact = 'spam_contact';

    /** Someone marked it as spam */
    case MarkedByHand = 'marked_by_hand';
}
