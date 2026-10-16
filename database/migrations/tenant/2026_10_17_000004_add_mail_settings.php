<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * @var list<string>
     */
    protected array $plain = [
        'driver', 'from_address', 'from_name',
        'smtp_host', 'smtp_port', 'smtp_username',
        'mailgun_domain', 'mailgun_endpoint',
        'ses_key', 'ses_region',
    ];

    /**
     * @var list<string>
     */
    protected array $encrypted = ['smtp_password', 'mailgun_secret', 'ses_secret'];

    public function up(): void
    {
        // No driver: the tenant uses the app's mailer until they set up their own
        foreach ($this->plain as $name) {
            $this->migrator->add("mail.{$name}", null);
        }

        foreach ($this->encrypted as $name) {
            $this->migrator->addEncrypted("mail.{$name}", null);
        }
    }

    public function down(): void
    {
        foreach ([...$this->plain, ...$this->encrypted] as $name) {
            $this->migrator->delete("mail.{$name}");
        }
    }
};
