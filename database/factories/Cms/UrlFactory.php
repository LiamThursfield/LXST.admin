<?php

namespace Database\Factories\Cms;

use App\Models\Cms\Page;
use App\Models\Cms\Url;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Url>
 */
class UrlFactory extends Factory
{
    protected $model = Url::class;

    /**
     * Define the model's default state (an enabled, published URL for a new page).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug(fake()->unique()->words(2, true));

        return [
            'urlable_type' => (new Page)->getMorphClass(),
            // Pages are made with their own URL, so it's removed for this one to take its place
            'urlable_id' => fn () => tap(Page::factory()->create(), fn (Page $page) => $page->url()->delete())->id,
            'url_main' => $slug,
            'url_full' => '/'.$slug,
            'is_enabled' => true,
            'published_at' => now()->subDay(),
            'expired_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now()->addWeek()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expired_at' => now()->subDay()]);
    }
}
