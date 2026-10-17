<?php

namespace Database\Factories\Dj;

use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoryPlay>
 */
class HistoryPlayFactory extends Factory
{
    protected $model = HistoryPlay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'history_session_id' => HistorySession::factory(),
            'library_track_id' => null,
            'source_track_id' => (string) fake()->numberBetween(1, 999_999),
            'position' => fake()->numberBetween(1, 100),
            'played_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }
}
