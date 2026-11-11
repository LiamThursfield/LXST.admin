<?php

namespace App\Models\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * The bookings module's settings
 */
class BookingsSettings extends Settings
{
    /** How many months a gift card lasts from when it's issued or paid for. Null means they never expire. */
    public ?int $gift_card_months;

    /** Whether customers can buy gift cards on the public page */
    public bool $gift_cards_online;

    /** The tax added to quotes and invoices, as a percentage. Null means none. */
    public ?float $tax_rate;

    /** What the tax is called, e.g. VAT or GST */
    public string $tax_label;

    /** The business's tax registration number, shown on quotes and invoices */
    public ?string $tax_number;

    /** The address and other details shown at the top of quotes and invoices */
    public ?string $business_details;

    /** How many days a new quote can be accepted for. Null means it doesn't expire. */
    public ?int $quote_valid_days;

    /** How many days after it's issued a new invoice is due. Null means no due date. */
    public ?int $invoice_due_days;

    public static function group(): string
    {
        return 'bookings';
    }
}
