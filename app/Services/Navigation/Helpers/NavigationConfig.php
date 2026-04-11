<?php

namespace App\Services\Navigation\Helpers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

class NavigationConfig
{
    /**
     * Returns the sortOrder config value for the given key
     * with the necessary prefix and suffix added, allowing e.g. main.account.settings.profile
     */
    public static function getSortOrderKey(string $key, int $defaultSortOrder = -10): int
    {
        return (int) Config::get(
            "navigation.sorting.$key.sortOrder",
            $defaultSortOrder
        );
    }

    /**
     * Combines the key 'parts' into a full key, and then returns the sortOrder config value for that
     *
     * @param  array<string>  $parts
     */
    public static function getSortOrderForKeyParts(array $parts, int $defaultSortOrder = -10): int
    {
        return NavigationConfig::getSortOrderKey(
            Arr::join($parts, '.'),
            $defaultSortOrder
        );
    }
}
