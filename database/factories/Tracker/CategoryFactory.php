<?php

namespace Database\Factories\Tracker;

use App\Models\Tracker\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state (a private, user-owned category).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'colour' => fake()->hexColor(),
            'icon' => 'i-lucide-tag',
            'is_public' => false,
        ];
    }

    /**
     * A public (shared) category, still attributed to an owner.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * A system/global category: public and unowned.
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'is_public' => true,
        ]);
    }

    /**
     * A private category owned by the given user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'is_public' => false,
        ]);
    }
}
