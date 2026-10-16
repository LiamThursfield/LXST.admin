<?php

namespace App\Models\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * A tenant's own mailer. With no driver, the app's mailer (config/mail.php) is used.
 * Applied per tenant by App\Services\Mail\TenantMailBootstrapper.
 */
class MailSettings extends Settings
{
    public const string DRIVER_SMTP = 'smtp';

    public const string DRIVER_MAILGUN = 'mailgun';

    public const string DRIVER_SES = 'ses';

    /** @var list<string> */
    public const array DRIVERS = [self::DRIVER_SMTP, self::DRIVER_MAILGUN, self::DRIVER_SES];

    /**
     * Mailgun's US and EU API hosts
     *
     * @var list<string>
     */
    public const array MAILGUN_ENDPOINTS = ['api.mailgun.net', 'api.eu.mailgun.net'];

    /**
     * Credentials, encrypted at rest and never sent back to the browser
     *
     * @var list<string>
     */
    public const array SECRETS = ['smtp_password', 'mailgun_secret', 'ses_secret'];

    /** One of DRIVERS, or null to use the app's mailer */
    public ?string $driver;

    public ?string $from_address;

    public ?string $from_name;

    public ?string $smtp_host;

    public ?int $smtp_port;

    public ?string $smtp_username;

    public ?string $smtp_password;

    public ?string $mailgun_domain;

    public ?string $mailgun_secret;

    /** One of MAILGUN_ENDPOINTS */
    public ?string $mailgun_endpoint;

    public ?string $ses_key;

    public ?string $ses_secret;

    public ?string $ses_region;

    public static function group(): string
    {
        return 'mail';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return self::SECRETS;
    }

    /**
     * The fields each driver uses, so a driver's fields can be cleared when another is chosen
     *
     * @return array<string, list<string>>
     */
    public static function driverFields(): array
    {
        return [
            self::DRIVER_SMTP => ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'],
            self::DRIVER_MAILGUN => ['mailgun_domain', 'mailgun_secret', 'mailgun_endpoint'],
            self::DRIVER_SES => ['ses_key', 'ses_secret', 'ses_region'],
        ];
    }

    /**
     * The Laravel mailer config (config/mail.php `mailers.*`) for the chosen driver, or null for none
     *
     * @return array<string, mixed>|null
     */
    public function mailerConfig(): ?array
    {
        return match ($this->driver) {
            self::DRIVER_SMTP => [
                'transport' => 'smtp',
                'host' => $this->smtp_host,
                'port' => $this->smtp_port,
                'username' => $this->smtp_username,
                'password' => $this->smtp_password,
                'timeout' => 30,
            ],
            self::DRIVER_MAILGUN => [
                'transport' => 'mailgun',
                'domain' => $this->mailgun_domain,
                'secret' => $this->mailgun_secret,
                'endpoint' => $this->mailgun_endpoint,
            ],
            self::DRIVER_SES => [
                'transport' => 'ses',
                'key' => $this->ses_key,
                'secret' => $this->ses_secret,
                'region' => $this->ses_region,
            ],
            default => null,
        };
    }
}
