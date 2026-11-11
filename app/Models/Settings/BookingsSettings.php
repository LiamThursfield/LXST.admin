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

    public static function group(): string
    {
        return 'bookings';
    }
}
