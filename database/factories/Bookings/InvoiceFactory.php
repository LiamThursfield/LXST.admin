<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\InvoiceStatus;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Invoice;
use App\Models\Bookings\LineItem;
use App\Models\Bookings\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state (a £100 invoice issued today, due in 14 days, with no tax).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INV-'.fake()->unique()->numerify('9####'),
            'booking_id' => Booking::factory(),
            'status' => InvoiceStatus::Issued,
            'subtotal' => 10000,
            'tax_rate' => null,
            'tax' => 0,
            'total' => 10000,
            'currency' => Service::CURRENCY,
            'issued_on' => today(),
            'due_on' => today()->addDays(14),
        ];
    }

    /**
     * With lines adding up to its subtotal
     */
    public function withLines(): static
    {
        return $this->afterCreating(function (Invoice $invoice) {
            LineItem::factory()->for($invoice, 'document')->create([
                'description' => 'Work as agreed',
                'unit_price' => $invoice->subtotal,
                'total' => $invoice->subtotal,
            ]);
        });
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Sent, 'sent_at' => now()]);
    }

    /**
     * Past its due date
     */
    public function overdue(): static
    {
        return $this->state(fn () => ['issued_on' => today()->subDays(30), 'due_on' => today()->subDays(16)]);
    }

    public function void(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Void, 'voided_at' => now()]);
    }
}
