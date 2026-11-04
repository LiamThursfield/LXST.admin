<?php

namespace Database\Factories\Crm;

use App\Models\Crm\OpeningHour;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningHour>
 */
class OpeningHourFactory extends Factory
{
    protected $model = OpeningHour::class;

    /**
     * Define the model's default state (a Monday, nine to five, at a new location).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_unit_id' => OrganisationUnit::factory()->location(),
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'closes_at' => '17:00',
            'label' => null,
        ];
    }

    /**
     * A period running past midnight, e.g. a bar's late night
     */
    public function overnight(): static
    {
        return $this->state(fn (array $attributes) => [
            'opens_at' => '18:00',
            'closes_at' => '02:00',
        ]);
    }

    /**
     * Open all day, e.g. a gym open 24/7
     */
    public function allDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'opens_at' => '00:00',
            'closes_at' => '00:00',
        ]);
    }
}
