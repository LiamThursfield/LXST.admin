<?php

namespace Database\Factories;

use App\Enums\Api\ApiTokenAbility;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Tokens made here have no known plain text. Use `$user->createToken()` when a test needs to send one.
 *
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    /**
     * Define the model's default state (a never-expiring, unused token with every ability).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tokenable_type' => (new User)->getMorphClass(),
            'tokenable_id' => User::factory(),
            'name' => fake()->words(2, true),
            'token' => hash('sha256', Str::random(40)),
            'abilities' => array_map(fn (ApiTokenAbility $ability) => $ability->value, ApiTokenAbility::cases()),
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
