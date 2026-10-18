<?php

namespace Database\Factories\Dj;

use App\Enums\Dj\SetlistSongType;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SongListItem>
 */
class SongListItemFactory extends Factory
{
    protected $model = SongListItem::class;

    /**
     * Define the model's default state (a must play from the library).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'song_list_id' => SongList::factory(),
            'canonical_track_id' => CanonicalTrack::factory(),
            'custom_artist' => null,
            'custom_title' => null,
            'type' => SetlistSongType::MustPlay,
            'position' => 0,
            'note' => null,
        ];
    }

    public function custom(string $artist, ?string $title = null): static
    {
        return $this->state(fn () => ['canonical_track_id' => null, 'custom_artist' => $artist, 'custom_title' => $title]);
    }
}
