<?php

namespace App\Services\Messaging;

/**
 * Phone numbers as messaging providers need them: international format, e.g. +447700900123
 */
class PhoneNumber
{
    /**
     * The number without spaces or punctuation, or null if it isn't in international format
     */
    public static function international(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $digits = (string) preg_replace('/[\s().-]/', '', $number);

        return preg_match('/^\+[1-9]\d{6,14}$/', $digits) === 1 ? $digits : null;
    }
}
