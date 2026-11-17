<?php

namespace App\Mcp\Support;

use Illuminate\Support\Str;

/**
 * Shortens a value for the audit log: long strings are cut, and deep or long lists summarised,
 * so the log shows what was sent and changed without storing whole pages of content
 */
class Summary
{
    public const int MAX_STRING = 300;

    public const int MAX_ITEMS = 50;

    public const int MAX_DEPTH = 4;

    public static function of(mixed $value, int $depth = 0): mixed
    {
        if (is_string($value)) {
            return Str::limit($value, self::MAX_STRING);
        }

        if (! is_array($value)) {
            return $value;
        }

        if ($depth >= self::MAX_DEPTH) {
            return '['.count($value).' items]';
        }

        $summary = [];

        foreach (array_slice($value, 0, self::MAX_ITEMS, true) as $key => $item) {
            $summary[$key] = self::of($item, $depth + 1);
        }

        if (count($value) > self::MAX_ITEMS) {
            $summary['…'] = (count($value) - self::MAX_ITEMS).' more';
        }

        return $summary;
    }
}
