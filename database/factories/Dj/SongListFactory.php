<?php

namespace Database\Factories\Dj;

use App\Enums\Dj\SongListKind;
use App\Enums\Dj\SongListVisibility;
use App\Models\Dj\Gig;
use App\Models\Dj\SongList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SongList>
 */
class SongListFactory extends Factory
{
    protected $model = SongList::class;

    /**
     * Define the model's default state (a private setlist with no gig).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gig_id' => null,
            'kind' => SongListKind::Setlist,
            'name' => ucwords(fake()->words(2, true)),
            'description' => null,
            'visibility' => SongListVisibility::Private,
        ];
    }

    public function forGig(?Gig $gig = null): static
    {
        return $this->state(fn () => ['gig_id' => $gig?->id ?? Gig::factory()]);
    }

    public function requests(): static
    {
        return $this->state(fn () => ['kind' => SongListKind::Requests, 'name' => 'Requests']);
    }
}
