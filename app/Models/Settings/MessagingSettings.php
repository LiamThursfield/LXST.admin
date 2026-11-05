<?php

namespace App\Models\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * How a tenant sends texts and WhatsApp messages: their own Twilio account
 */
class MessagingSettings extends Settings
{
    public const string PROVIDER_TWILIO = 'twilio';

    /**
     * @var list<string>
     */
    public const array PROVIDERS = [self::PROVIDER_TWILIO];

    /**
     * Credentials, encrypted at rest and never sent back to the browser
     *
     * @var list<string>
     */
    public const array SECRETS = ['twilio_auth_token'];

    /** One of PROVIDERS, or null for none */
    public ?string $provider;

    public ?string $twilio_account_sid;

    public ?string $twilio_auth_token;

    /** The number or sender ID texts come from */
    public ?string $sms_from;

    /** The WhatsApp number messages come from, e.g. +447700900123 */
    public ?string $whatsapp_from;

    public static function group(): string
    {
        return 'messaging';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return self::SECRETS;
    }

    public function canSendSms(): bool
    {
        return $this->provider === self::PROVIDER_TWILIO
            && filled($this->twilio_account_sid)
            && filled($this->twilio_auth_token)
            && filled($this->sms_from);
    }

    public function canSendWhatsApp(): bool
    {
        return $this->provider === self::PROVIDER_TWILIO
            && filled($this->twilio_account_sid)
            && filled($this->twilio_auth_token)
            && filled($this->whatsapp_from);
    }
}
