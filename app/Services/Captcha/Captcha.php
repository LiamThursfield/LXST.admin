<?php

namespace App\Services\Captcha;

use App\Enums\CaptchaProvider;
use App\Models\Settings\IntegrationSettings;
use Illuminate\Http\Request;

/**
 * The tenant's captcha (#74), set up in the integration settings. Sites render the provider's widget
 * with what widget() gives them, and send its token back in the token field.
 */
class Captcha
{
    public function __construct(protected IntegrationSettings $settings) {}

    /**
     * The field sites send the widget's token in, which form fields can't use as keys
     */
    public static function tokenField(): string
    {
        return (string) config('api.captcha.token_field');
    }

    /**
     * Whether a provider and both keys are saved
     */
    public function isConfigured(): bool
    {
        return $this->settings->captcha_provider !== null
            && filled($this->settings->captcha_site_key)
            && filled($this->settings->captcha_secret_key);
    }

    /**
     * What a site needs to render the widget, or null when captcha isn't set up
     *
     * @return array{provider: string, site_key: string, token_field: string}|null
     */
    public function widget(): ?array
    {
        $provider = $this->settings->captcha_provider;

        if ($provider === null || ! $this->isConfigured()) {
            return null;
        }

        return [
            'provider' => $provider->value,
            'site_key' => (string) $this->settings->captcha_site_key,
            'token_field' => self::tokenField(),
        ];
    }

    /**
     * Checks the token sent with the request. A missing token fails without asking the provider.
     *
     * @param  string|null  $ip  The visitor's IP address, when a site's server calls the API for them
     */
    public function check(Request $request, ?string $ip = null): CaptchaResult
    {
        $token = $request->input(self::tokenField());

        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            return CaptchaResult::Failed;
        }

        /** @var CaptchaProvider $provider */
        $provider = $this->settings->captcha_provider;

        return $provider->verifier()->verify($token, (string) $this->settings->captcha_secret_key, $ip ?? $request->ip());
    }
}
