<?php

namespace Database\Factories\Tracker;

use App\Enums\Tracker\TaskDefinitionStatus;
use App\Enums\Tracker\TrackerAggregation;
use App\Enums\Tracker\TrackerComparator;
use App\Enums\Tracker\TrackerMeasure;
use App\Enums\Tracker\TrackerPeriod;
use App\Enums\Tracker\TrackerUnit;
use App\Models\Tracker\TaskDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskDefinition>
 */
class TaskDefinitionFactory extends Factory
{
    protected $model = TaskDefinition::class;

    /**
     * Define the model's default state: a simple daily "drink water" style goal.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'period' => TrackerPeriod::Daily,
            'schedule' => null,
            'measure' => TrackerMeasure::Volume,
            'unit' => TrackerUnit::Litres,
            'comparator' => TrackerComparator::Min,
            'aggregation' => TrackerAggregation::Sum,
            'target_min' => 2000,
            'target_max' => null,
            'stepped' => true,
            'counts_during_holiday' => false,
            'active_from' => now()->toDateString(),
            'active_to' => null,
            'status' => TaskDefinitionStatus::Active,
            'sort_order' => 0,
        ];
    }

    /**
     * A boolean "did you do it" style task.
     */
    public function boolean(): static
    {
        return $this->state(fn (array $attributes) => [
            'measure' => TrackerMeasure::Boolean,
            'unit' => TrackerUnit::Boolean,
            'comparator' => TrackerComparator::Boolean,
            'aggregation' => TrackerAggregation::Any,
            'target_min' => null,
            'target_max' => null,
            'stepped' => false,
        ]);
    }

    /**
     * A task owned by the given user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
