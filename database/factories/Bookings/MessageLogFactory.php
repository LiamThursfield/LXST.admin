<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\MessageChannel;
use App\Enums\Bookings\MessageEvent;
use App\Enums\Bookings\MessageStatus;
use App\Models\Bookings\Booking;
use App\Models\Bookings\MessageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageLog>
 */
class MessageLogFactory extends Factory
{
    protected $model = MessageLog::class;

    /**
     * Define the model's default state (a booking confirmation sent by email).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'event' => MessageEvent::Booked,
            'channel' => MessageChannel::Email,
            'recipient' => fake()->safeEmail(),
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ];
    }

    public function failed(string $note = 'The number is not reachable.'): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => MessageChannel::Sms,
            'recipient' => '+447700900123',
            'status' => MessageStatus::Failed,
            'note' => $note,
            'sent_at' => null,
        ]);
    }
}
