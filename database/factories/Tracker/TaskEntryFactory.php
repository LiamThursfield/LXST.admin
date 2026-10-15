<?php

namespace Database\Factories\Tracker;

use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskEntry>
 */
class TaskEntryFactory extends Factory
{
    protected $model = TaskEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_occurrence_id' => TaskOccurrence::factory(),
            'user_id' => fn (array $attributes) => TaskOccurrence::findOrFail($attributes['task_occurrence_id'])->user_id,
            'value' => fake()->numberBetween(1, 1000),
            'label' => null,
            'occurred_at' => now(),
        ];
    }

    /**
     * For a specific occurrence, logged at the start of its window.
     */
    public function forOccurrence(TaskOccurrence $occurrence): static
    {
        return $this->state(fn (array $attributes) => [
            'task_occurrence_id' => $occurrence->id,
            'user_id' => $occurrence->user_id,
            'occurred_at' => $occurrence->period_start->setTime(12, 0),
        ]);
    }
}
