<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Address;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * Define the model's default state (a UK address on a new company).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'addressable_type' => (new OrganisationUnit)->getMorphClass(),
            'addressable_id' => OrganisationUnit::factory(),
            'postcode' => fake()->regexify('[A-Z]{2}[0-9] [0-9][A-Z]{2}'),
            'line_1' => fake()->buildingNumber().' '.fake()->streetName(),
            'line_2' => null,
            'line_3' => null,
            'town_city' => fake()->city(),
            'county' => null,
            'country' => 'United Kingdom',
        ];
    }
}
