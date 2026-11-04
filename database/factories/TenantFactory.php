<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creating a tenant also creates its database (the tenancy pipeline), so use `make()` unless a
 * test needs one.
 *
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'modules' => [],
            'industry' => config('industries.fallback'),
        ];
    }

    /**
     * A tenant in the given industry, a key of config('industries.profiles')
     */
    public function industry(string $industry): static
    {
        return $this->state(fn () => ['industry' => $industry]);
    }

    /**
     * A tenant using its own words, e.g. ['staff_member' => ['singular' => 'barber', 'plural' => 'barbers']]
     *
     * @param  array<string, array{singular: string, plural: string}>  $terms
     */
    public function withTerms(array $terms): static
    {
        return $this->state(fn () => ['industry_overrides' => ['terms' => $terms]]);
    }
}
