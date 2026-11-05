<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\ServiceOptionType;
use App\Models\Bookings\Booking;
use App\Models\Bookings\BookingOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingOption>
 */
class BookingOptionFactory extends Factory
{
    protected $model = BookingOption::class;

    /**
     * Define the model's default state (a variation that costs more and takes longer).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'type' => ServiceOptionType::Variation,
            'name' => ucfirst(fake()->words(2, true)),
            'price_adjustment' => 500,
            'duration_adjustment' => 15,
        ];
    }
}
