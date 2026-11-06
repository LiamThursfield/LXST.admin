<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\MessageEvent;
use App\Models\Bookings\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    /**
     * Define the model's default state (the booked message, in the tenant's own words).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => MessageEvent::Booked,
            'is_enabled' => true,
            'email_subject' => 'See you soon, {customer_name}',
            'email_body' => "Hi {customer_name},\n\nSee you on {date} at {time} for {service}.\n\n{business}",
            'sms_body' => 'See you {date} at {time}. {business}',
        ];
    }

    /**
     * A reminder sent the given number of hours before, in the default wording
     */
    public function reminder(int $hours = 24): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => MessageEvent::Reminder,
            'offset_hours' => $hours,
            'email_subject' => null,
            'email_body' => null,
            'sms_body' => null,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    /**
     * With an approved WhatsApp template
     */
    public function withWhatsApp(string $sid = 'HX0123456789abcdef0123456789abcdef'): static
    {
        return $this->state(fn (array $attributes) => [
            'whatsapp_template_sid' => $sid,
        ]);
    }
}
