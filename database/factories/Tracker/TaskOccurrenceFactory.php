<?php

namespace Database\Factories\Tracker;

use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskOccurrence>
 */
class TaskOccurrenceFactory extends Factory
{
    protected $model = TaskOccurrence::class;

    /**
     * Define the model's default state (a single daily occurrence).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 week', 'now')->format('Y-m-d');

        return [
            'task_definition_id' => TaskDefinition::factory(),
            'user_id' => fn (array $attributes) => TaskDefinition::findOrFail($attributes['task_definition_id'])->user_id,
            'period_key' => $date,
            'period_start' => $date,
            'period_end' => $date,
            'computed_value' => null,
            'status' => TaskOccurrenceStatus::Pending,
            'is_holiday' => false,
            'completed_at' => null,
        ];
    }

    /**
     * For a specific task definition (keeping the owner in sync).
     */
    public function forDefinition(TaskDefinition $definition): static
    {
        return $this->state(fn (array $attributes) => [
            'task_definition_id' => $definition->id,
            'user_id' => $definition->user_id,
        ]);
    }
}
