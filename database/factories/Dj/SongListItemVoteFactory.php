<?php

namespace Database\Factories\Dj;

use App\Models\Dj\SongListItem;
use App\Models\Dj\SongListItemVote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SongListItemVote>
 */
class SongListItemVoteFactory extends Factory
{
    protected $model = SongListItemVote::class;

    /**
     * Define the model's default state (a visitor's upvote of a request).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'song_list_item_id' => SongListItem::factory(),
            'visitor_hash' => hash('sha256', fake()->unique()->uuid()),
            'guest_pass_id' => null,
        ];
    }
}
