<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * @var list<string>
     */
    protected array $plain = ['provider', 'twilio_account_sid', 'sms_from', 'whatsapp_from'];

    /**
     * @var list<string>
     */
    protected array $encrypted = ['twilio_auth_token'];

    public function up(): void
    {
        // No provider: texts and WhatsApp messages fall back to email until one is set up
        foreach ($this->plain as $name) {
            $this->migrator->add("messaging.{$name}", null);
        }

        foreach ($this->encrypted as $name) {
            $this->migrator->addEncrypted("messaging.{$name}", null);
        }
    }

    public function down(): void
    {
        foreach ([...$this->plain, ...$this->encrypted] as $name) {
            $this->migrator->delete("messaging.{$name}");
        }
    }
};
