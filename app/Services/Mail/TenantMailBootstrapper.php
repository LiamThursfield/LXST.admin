<?php

namespace App\Services\Mail;

use App\Models\Settings\MailSettings;
use Illuminate\Config\Repository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Sends a tenant's email through their own mailer (site settings), when they have one.
 *
 * Tenancy's MailConfigBootstrapper reads credentials from the central tenant record, but mail
 * settings live in the tenant's database, so this must run after DatabaseTenancyBootstrapper.
 */
class TenantMailBootstrapper implements TenancyBootstrapper
{
    /** The mailer name the tenant's settings are registered under */
    public const string MAILER = 'tenant';

    /**
     * @var array<string, mixed>
     */
    protected array $originalConfig = [];

    public function __construct(
        protected Repository $config,
        protected Application $app,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        try {
            $settings = $this->app->make(MailSettings::class);
            $mailer = $settings->mailerConfig();
        } catch (MissingSettings|QueryException) {
            // The tenant's settings don't exist yet (e.g. while a new tenant's database is being
            // migrated), so use the app's mailer
            return;
        }

        if ($mailer === null) {
            return;
        }

        $this->setConfig([
            'mail.mailers.'.self::MAILER => $mailer,
            'mail.default' => self::MAILER,
            'mail.from.address' => $settings->from_address ?? $this->config->get('mail.from.address'),
            'mail.from.name' => $settings->from_name ?? $this->config->get('mail.from.name'),
        ]);

        $this->forgetMailers();
    }

    public function revert(): void
    {
        if ($this->originalConfig === []) {
            return;
        }

        foreach ($this->originalConfig as $key => $value) {
            $this->config->set($key, $value);
        }

        $this->originalConfig = [];

        $this->forgetMailers();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function setConfig(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->originalConfig[$key] ??= $this->config->get($key);
            $this->config->set($key, $value);
        }
    }

    /**
     * Mailers are built once and kept, so forget them to pick up the new config. Only done when
     * the config changes, so tenants without their own mailer keep e.g. a test's Mail::fake().
     */
    protected function forgetMailers(): void
    {
        $this->app->forgetInstance('mail.manager');
        $this->app->forgetInstance('mailer');
        Mail::clearResolvedInstances();
    }
}
