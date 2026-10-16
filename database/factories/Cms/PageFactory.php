<?php

namespace Database\Factories\Cms;

use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * Define the model's default state (a top-level page, with a published URL).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'template_id' => Template::factory(),
            'layout_id' => Layout::factory(),
            'parent_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
        ];
    }

    public function childOf(Page $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }

    /**
     * Give the page its URL (built from its parent's), enabled unless told otherwise
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Page $page) {
            $page->url()->create([
                'url_main' => $page->urlMain(),
                'url_full' => $page->urlFull(),
                'is_enabled' => true,
            ]);
        });
    }
}
