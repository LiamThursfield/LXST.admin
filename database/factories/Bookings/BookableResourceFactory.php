<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\ResourceType;
use App\Models\Bookings\BookableResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookableResource>
 */
class BookableResourceFactory extends Factory
{
    protected $model = BookableResource::class;

    /**
     * Define the model's default state (a person).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->name();

        return [
            'type' => ResourceType::Person,
            'name' => $name,
            'slug' => Str::slug($name),
            'job_title' => fake()->jobTitle(),
            'is_active' => true,
            'is_public' => false,
        ];
    }

    /**
     * A person who signs in as the given user
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function space(int $capacity = 4): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Space,
            'job_title' => null,
            'capacity' => $capacity,
        ]);
    }

    public function equipment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Equipment,
            'job_title' => null,
        ]);
    }

    public function vehicle(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Vehicle,
            'job_title' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function shownOnSite(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }
}
