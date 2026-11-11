<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // No tax until the business says so, and the usual terms for quotes and invoices
        $this->migrator->add('bookings.tax_rate', null);
        $this->migrator->add('bookings.tax_label', 'VAT');
        $this->migrator->add('bookings.tax_number', null);
        $this->migrator->add('bookings.business_details', null);
        $this->migrator->add('bookings.quote_valid_days', 30);
        $this->migrator->add('bookings.invoice_due_days', 14);
    }

    public function down(): void
    {
        foreach (['tax_rate', 'tax_label', 'tax_number', 'business_details', 'quote_valid_days', 'invoice_due_days'] as $name) {
            $this->migrator->delete("bookings.{$name}");
        }
    }
};
