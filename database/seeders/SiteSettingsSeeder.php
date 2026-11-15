<?php

namespace Database\Seeders;

use App\Enums\CaptchaProvider;
use App\Models\FileManager\File;
use App\Models\Settings\GeneralSettings;
use App\Models\Settings\IntegrationSettings;
use App\Models\Settings\MailSettings;
use App\Models\Settings\ThemeSettings;
use Illuminate\Database\Seeder;

/**
 * Site settings with harmless demo values (#168): the site name, a theme using the File Manager
 * seeder's logo and favicon, a demo sender on the app's own mailer, a placeholder GTM ID, and
 * Cloudflare's published Turnstile test keys, whose widget always passes (#74).
 *
 * Run within a tenant, after the File Manager seeder, e.g. `php artisan tenants:seed --class=SiteSettingsSeeder`.
 * Safe to re-run: settings are updated in place.
 */
class SiteSettingsSeeder extends Seeder
{
    public const string SITE_NAME = 'Northside Sound';

    /** Cloudflare's test site key, whose widget always passes: not a real credential */
    public const string TURNSTILE_TEST_SITE_KEY = '1x00000000000000000000AA';

    /** Cloudflare's test secret key, which accepts any token: not a real credential */
    public const string TURNSTILE_TEST_SECRET_KEY = '1x0000000000000000000000000000000AA';

    public function run(): void
    {
        app(GeneralSettings::class)->fill(['site_name' => self::SITE_NAME])->save();

        app(ThemeSettings::class)->fill([
            'logo_file_id' => File::query()->where('path', 'branding/logo.png')->value('id'),
            'favicon_file_id' => File::query()->where('path', 'branding/favicon.png')->value('id'),
            'primary_color' => 'violet',
            'neutral_color' => 'zinc',
            'site_colors' => [
                ...ThemeSettings::DEFAULT_SITE_COLORS,
                'primary' => '#7C3AED',
                'primary_hover' => '#5B21B6',
                'secondary' => '#18181B',
                'secondary_hover' => '#27272A',
            ],
        ])->save();

        // No driver, so mail goes through the app's own mailer and can't fail on made-up credentials
        app(MailSettings::class)->fill([
            'driver' => null,
            'from_address' => 'hello@example.com',
            'from_name' => self::SITE_NAME,
        ])->save();

        app(IntegrationSettings::class)->fill([
            'gtm_id' => 'GTM-DEMO000',
            'captcha_provider' => CaptchaProvider::Turnstile,
            'captcha_site_key' => self::TURNSTILE_TEST_SITE_KEY,
            'captcha_secret_key' => self::TURNSTILE_TEST_SECRET_KEY,
        ])->save();
    }
}
