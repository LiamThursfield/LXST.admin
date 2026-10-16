<?php

namespace App\Models\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * A tenant's branding. The admin uses the logo, favicon and Nuxt UI palettes. The site (through
 * the API) can also use them, plus its own optional hex palette in `site_colors`.
 */
class ThemeSettings extends Settings
{
    /**
     * Tailwind palettes Nuxt UI can use as the primary colour
     *
     * @var list<string>
     */
    public const array PRIMARY_PALETTES = [
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan',
        'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose',
    ];

    /**
     * Tailwind grey palettes Nuxt UI can use as the neutral colour
     *
     * @var list<string>
     */
    public const array NEUTRAL_PALETTES = ['slate', 'gray', 'zinc', 'neutral', 'stone'];

    /**
     * The site's colours (legacy's palette), with the defaults legacy used
     *
     * @var array<string, string>
     */
    public const array DEFAULT_SITE_COLORS = [
        'base' => '#FFFFFF',
        'base_contrast' => '#262C43',
        'base_subtle' => '#E2E8F0',
        'base_subtle_contrast' => '#262C43',
        'primary' => '#2563EB',
        'primary_contrast' => '#FFFFFF',
        'primary_hover' => '#1E40AF',
        'primary_hover_contrast' => '#FFFFFF',
        'secondary' => '#323954',
        'secondary_contrast' => '#FFFFFF',
        'secondary_hover' => '#262C43',
        'secondary_hover_contrast' => '#FFFFFF',
        'accent' => '#EA580C',
        'accent_contrast' => '#FFFFFF',
        'accent_hover' => '#9A3412',
        'accent_hover_contrast' => '#FFFFFF',
    ];

    /** A File Manager image (see FileManagerStorage::urlFor()) */
    public ?int $logo_file_id;

    /** A File Manager image (see FileManagerStorage::urlFor()) */
    public ?int $favicon_file_id;

    /** One of PRIMARY_PALETTES */
    public string $primary_color;

    /** One of NEUTRAL_PALETTES */
    public string $neutral_color;

    /**
     * Hex colours keyed by DEFAULT_SITE_COLORS' keys. A null colour leaves it to the site.
     * (A phpstan-only type, as laravel-settings tries to cast each item of a typed `@var` array.)
     *
     * @phpstan-var array<string, string|null>
     */
    public array $site_colors;

    public static function group(): string
    {
        return 'theme';
    }
}
