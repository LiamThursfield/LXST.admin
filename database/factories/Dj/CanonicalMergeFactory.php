<?php

namespace Database\Factories\Dj;

use App\Enums\Dj\CanonicalRecord;
use App\Enums\Dj\MergeAction;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CanonicalMerge>
 */
class CanonicalMergeFactory extends Factory
{
    protected $model = CanonicalMerge::class;

    /**
     * Define the model's default state (a song merged into another by the re-match).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucwords(fake()->words(2, true));

        return [
            'record' => CanonicalRecord::Track,
            'action' => MergeAction::Merge,
            'target_id' => CanonicalTrack::factory(),
            'target_name' => $title,
            'source_id' => null,
            'source_name' => $title.' (Original Mix)',
            'moved_count' => fake()->numberBetween(1, 3),
            'user_id' => null,
        ];
    }

    public function split(): static
    {
        return $this->state(fn () => ['action' => MergeAction::Split]);
    }

    public function artist(): static
    {
        return $this->state(fn () => ['record' => CanonicalRecord::Artist, 'target_id' => null]);
    }
}
