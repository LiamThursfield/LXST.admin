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
     * A company, optionally within a parent company (e.g. a group)
     */
    public function company(?OrganisationUnit $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrganisationUnitType::Company,
            'parent_id' => $parent?->id,
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
     * A department within the given location or department (or a new location)
     */
    public function department(?OrganisationUnit $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrganisationUnitType::Department,
            'name' => fake()->unique()->jobTitle().' Team',
            'parent_id' => $parent->id ?? OrganisationUnit::factory()->location(),
        ]);
    }
}
