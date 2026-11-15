<?php

namespace App\Models\Settings;

use App\Enums\CaptchaProvider;
use Spatie\LaravelSettings\Settings;
use Spatie\LaravelSettings\SettingsCasts\EnumCast;

/**
 * A tenant's third-party integrations, stored in the tenant database's `settings` table
 */
class IntegrationSettings extends Settings
{
    /**
     * Credentials, encrypted at rest and never sent back to the browser
     *
     * @var list<string>
     */
    public const array SECRETS = ['captcha_secret_key'];

    /** The Google Tag Manager container ID, e.g. GTM-ABC1234 */
    public ?string $gtm_id;

    /** The captcha service protecting forms and song requests (#74), or null for none */
    public ?CaptchaProvider $captcha_provider;

    /** The public key sites render the captcha widget with */
    public ?string $captcha_site_key;

    /** The private key the API checks tokens with */
    public ?string $captcha_secret_key;

    public static function group(): string
    {
        return 'integrations';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return self::SECRETS;
    }

    /**
     * @return array<string, string>
     */
    public static function casts(): array
    {
        return [
            'captcha_provider' => EnumCast::class.':'.CaptchaProvider::class,
        ];
    }
}
