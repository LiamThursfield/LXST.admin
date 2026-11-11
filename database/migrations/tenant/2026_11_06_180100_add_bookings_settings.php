<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Gift cards last a year unless the business changes it, and aren't sold online until it chooses to
        $this->migrator->add('bookings.gift_card_months', 12);
        $this->migrator->add('bookings.gift_cards_online', false);
    }

    public function down(): void
    {
        $this->migrator->delete('bookings.gift_card_months');
        $this->migrator->delete('bookings.gift_cards_online');
    }
};
