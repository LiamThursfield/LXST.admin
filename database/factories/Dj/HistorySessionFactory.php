<?php

namespace Database\Factories\Dj;

use App\Models\Dj\HistorySession;
use App\Models\Dj\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistorySession>
 */
class HistorySessionFactory extends Factory
{
    protected $model = HistorySession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 year');

        return [
            'library_id' => Library::factory(),
            'source_history_id' => (string) fake()->unique()->numberBetween(1, 999_999),
            'name' => 'HISTORY '.$startedAt->format('Y-m-d'),
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify('+2 hours'),
        ];
    }
}
