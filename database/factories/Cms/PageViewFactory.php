<?php

namespace Database\Factories\Cms;

use App\Models\Cms\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    protected $model = PageView::class;

    /**
     * Define the model's default state (an unlinked view of a random path).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url' => '/'.fake()->slug(2),
            'user_agent' => fake()->userAgent(),
            'ip_address' => '203.0.113.0',
            'visitor_hash' => hash('sha256', fake()->uuid()),
            'viewed_at' => now(),
        ];
    }
}
