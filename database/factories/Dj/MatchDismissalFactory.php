<?php

namespace Database\Factories\Dj;

use App\Enums\Dj\CanonicalRecord;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\MatchDismissal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchDismissal>
 */
class MatchDismissalFactory extends Factory
{
    protected $model = MatchDismissal::class;

    /**
     * Define the model's default state (a pair of songs marked as not the same).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record' => CanonicalRecord::Track,
            'first_id' => CanonicalTrack::factory(),
            'second_id' => CanonicalTrack::factory(),
            'user_id' => null,
        ];
    }

    /**
     * The given pair, smaller ID first, as dismissals are stored
     */
    public function pair(int $first, int $second, CanonicalRecord $record = CanonicalRecord::Track): static
    {
        return $this->state(fn () => [
            'record' => $record,
            'first_id' => min($first, $second),
            'second_id' => max($first, $second),
        ]);
    }
}
