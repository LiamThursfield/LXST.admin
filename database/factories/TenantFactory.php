<?php

namespace Database\Factories;

use App\Enums\TenantSetupStatus;
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

    /**
     * A tenant made from the central admin, waiting for its database to be set up
     * (ProvisionTenant). Its database isn't created when it's saved.
     *
     * @param  array{first_name: string, last_name: string, email: string}|null  $admin  The first admin to invite once it's set up
     */
    public function settingUp(?array $admin = null): static
    {
        return $this->state(fn () => ['setup_status' => TenantSetupStatus::SettingUp, 'setup_admin' => $admin]);
    }

    /**
     * A tenant whose setup failed, so it has no database
     */
    public function setupFailed(string $error = 'The database server refused the connection.'): static
    {
        return $this->state(fn () => ['setup_status' => TenantSetupStatus::Failed, 'setup_error' => $error]);
    }
}
