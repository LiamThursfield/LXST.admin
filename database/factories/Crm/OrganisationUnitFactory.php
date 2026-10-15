<?php

namespace Database\Factories\Crm;

use App\Enums\Crm\OrganisationUnitType;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationUnit>
 */
class OrganisationUnitFactory extends Factory
{
    protected $model = OrganisationUnit::class;

    /**
     * Define the model's default state (a company with no parent).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'type' => OrganisationUnitType::Company,
            'name' => fake()->unique()->company(),
            'email' => fake()->unique()->companyEmail(),
            'telephone' => fake()->numerify('01### ######'),
            'mobile_phone' => null,
            'socials' => null,
            'notification_emails' => null,
        ];
    }

    /**
     * A company, which is always at the top of the hierarchy
     */
    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrganisationUnitType::Company,
            'parent_id' => null,
        ]);
    }

    /**
     * A location within the given company (or a new one)
     */
    public function location(?OrganisationUnit $company = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrganisationUnitType::Location,
            'name' => fake()->unique()->city().' Office',
            'parent_id' => $company->id ?? OrganisationUnit::factory()->company(),
        ]);
    }

    /**
     * A department within the given location (or a new one)
     */
    public function department(?OrganisationUnit $location = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrganisationUnitType::Department,
            'name' => fake()->unique()->jobTitle().' Team',
            'parent_id' => $location->id ?? OrganisationUnit::factory()->location(),
        ]);
    }
}
