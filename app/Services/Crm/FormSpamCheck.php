<?php

namespace App\Services\Crm;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * A basic, captcha-free spam check for public form submissions.
 *
 * Websites get a token with the form definition and send it back with the submission,
 * along with a hidden honeypot field that people leave empty. Bots tend to fill in every
 * field and submit instantly. See config/api.php (form_submissions).
 */
class FormSpamCheck
{
    /**
     * The fields the website must render (and the check reads), which form fields can't use as keys
     *
     * @return list<string>
     */
    public static function reservedFields(): array
    {
        return [
            (string) config('api.form_submissions.honeypot_field'),
            (string) config('api.form_submissions.token_field'),
        ];
    }

    /**
     * A token recording when the form was loaded, for the website to send back with the submission
     */
    public function issueToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * Whether the submission looks automated: the honeypot was filled in, the form was
     * submitted too quickly after loading, or the token was tampered with. A missing token
     * isn't treated as spam, so websites that don't send one still work.
     */
    public function looksAutomated(Request $request): bool
    {
        if (filled($request->input((string) config('api.form_submissions.honeypot_field')))) {
            return true;
        }

        $token = $request->input((string) config('api.form_submissions.token_field'));

        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            $loadedAt = (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            return true;
        }

        return now()->getTimestamp() - $loadedAt < (int) config('api.form_submissions.min_seconds');
    }
}
