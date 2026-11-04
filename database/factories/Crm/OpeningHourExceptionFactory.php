<?php

namespace Database\Factories\Crm;

use App\Models\Crm\OpeningHourException;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningHourException>
 */
class OpeningHourExceptionFactory extends Factory
{
    protected $model = OpeningHourException::class;

    /**
     * Define the model's default state (closed for one day next week).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now()->addWeek()->toDateString();

        return [
            'organisation_unit_id' => OrganisationUnit::factory()->location(),
            'name' => 'Closed for the day',
            'starts_on' => $date,
            'ends_on' => $date,
            'periods' => null,
        ];
    }

    /**
     * Open at different times on these dates, rather than closed
     *
     * @param  list<array{opens_at: string, closes_at: string, label?: string|null}>  $periods
     */
    public function specialHours(array $periods = [['opens_at' => '10:00', 'closes_at' => '14:00', 'label' => null]]): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Special hours',
            'periods' => $periods,
        ]);
    }
}
