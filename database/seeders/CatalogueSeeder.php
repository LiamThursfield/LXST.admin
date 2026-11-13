<?php

namespace Database\Seeders;

use App\Enums\Catalogue\TagKind;
use App\Enums\TenantModule;
use App\Models\Bookings\Service;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Tag;
use App\Models\FileManager\File;
use App\Services\Catalogue\CatalogueContents;
use App\Services\Catalogue\CatalogueTags;
use Illuminate\Database\Seeder;

/**
 * Seeds the built-in allergen and dietary tags and two of the tenant's own, then catalogues that show
 * every feature: a weekday lunch menu with times, sections, tags, variations, an image and a sold-out
 * dish; drinks that always apply; a seasonal menu that's still a draft and starts in a few weeks; a
 * shop that counts stock, with some out; and, with the Bookings module, a price list of services.
 * Saved through CatalogueContents, as the editor does.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=CatalogueSeeder`.
 * Safe to re-run: a tag or catalogue that already exists is left alone.
 */
class CatalogueSeeder extends Seeder
{
    /** @var array<string, int> Tag ids by preset key or name */
    protected array $tags = [];

    public function __construct(protected CatalogueTags $presets, protected CatalogueContents $contents) {}

    public function run(): void
    {
        $this->seedTags();

        $image = File::query()->where('mime_type', 'like', 'image/%')->orderBy('id')->first();

        $this->seedCatalogue('lunch-menu', [
            'name' => 'Lunch menu',
            'description' => 'Served on weekdays from noon until 3pm.',
            'is_published' => true,
            'available_days' => [1, 2, 3, 4, 5],
            'available_from' => '12:00:00',
            'available_until' => '15:00:00',
            'order' => 10,
        ], [
            $this->section('Starters', null, [
                $this->item('Soup of the day', 650, ['celery', 'vegan'], ['description' => 'With crusty bread. Ask us what\'s on today.']),
                $this->item('Garlic bread', 450, ['gluten', 'milk', 'vegetarian']),
                $this->item('Salt and pepper squid', 750, ['molluscs', 'gluten', 'eggs'], ['is_available' => false]),
            ]),
            $this->section('Mains', 'All served with a side salad.', [
                $this->item('Fish and chips', null, ['fish', 'gluten', 'Bestseller'], [
                    'image_file_id' => $image?->id,
                    'variations' => [
                        $this->variation('Regular', 1400),
                        $this->variation('Large', 1700),
                    ],
                ]),
                $this->item('Mushroom risotto', 1350, ['milk', 'vegetarian', 'gluten_free', 'New']),
                $this->item('Chicken burger', 1450, ['gluten', 'eggs', 'sesame', 'mustard']),
            ]),
            $this->section('Desserts', null, [
                $this->item('Sticky toffee pudding', 700, ['milk', 'eggs', 'gluten']),
                $this->item('Lemon sorbet', 550, ['vegan', 'gluten_free', 'dairy_free']),
            ]),
        ]);

        $this->seedCatalogue('drinks', [
            'name' => 'Drinks',
            'description' => null,
            'is_published' => true,
            'order' => 20,
        ], [
            $this->section('Soft drinks', null, [
                $this->item('Homemade lemonade', 350, ['vegan']),
                $this->item('Coffee', null, ['milk'], [
                    'variations' => [
                        $this->variation('Regular', 300),
                        $this->variation('Large', 360),
                    ],
                ]),
            ]),
            $this->section('Wine', null, [
                $this->item('House red', null, ['sulphites', 'vegan'], [
                    'variations' => [
                        $this->variation('175ml', 650),
                        $this->variation('250ml', 850),
                        $this->variation('Bottle', 2400),
                    ],
                ]),
            ]),
        ]);

        // A draft, for a season a few weeks away
        $this->seedCatalogue('festive-menu', [
            'name' => 'Festive menu',
            'description' => 'Our set menu for the season. Bookings for groups of eight or more.',
            'is_published' => false,
            'starts_on' => now()->addWeeks(3)->toDateString(),
            'ends_on' => now()->addWeeks(7)->toDateString(),
            'order' => 30,
        ], [
            $this->section('Set menu', null, [
                $this->item('Two courses', 3200, []),
                $this->item('Three courses', 3800, ['Bestseller']),
            ]),
        ]);

        // Stock counted on the item, and on each variation, with some out
        $this->seedCatalogue('shop', [
            'name' => 'Shop',
            'description' => 'Take a little of us home.',
            'is_published' => true,
            'order' => 40,
        ], [
            $this->section('Merchandise', null, [
                $this->item('T-shirt', null, ['New'], [
                    'tracks_stock' => true,
                    'variations' => [
                        $this->variation('Small', 2000, 5),
                        $this->variation('Medium', 2000, 0),
                        $this->variation('Large', 2000, 3),
                    ],
                ]),
                $this->item('Tote bag', 1000, [], ['tracks_stock' => true, 'stock' => 12]),
                $this->item('Gift box', 2500, [], ['tracks_stock' => true, 'stock' => 0]),
            ]),
        ]);

        if (tenant()?->hasModule(TenantModule::Bookings)) {
            $this->seedPriceList();
        }
    }

    protected function seedTags(): void
    {
        $this->presets->add('allergens');
        $this->presets->add('dietary');

        Tag::query()->firstOrCreate(['preset' => null, 'name' => 'Bestseller'], ['kind' => TagKind::Other, 'icon' => 'i-lucide-star', 'order' => 100]);
        Tag::query()->firstOrCreate(['preset' => null, 'name' => 'New'], ['kind' => TagKind::Other, 'icon' => 'i-lucide-sparkles', 'order' => 101]);

        foreach (Tag::query()->get() as $tag) {
            $this->tags[$tag->preset ?? $tag->name] = $tag->id;
        }
    }

    /**
     * Items that show the first few bookable services, using their prices
     */
    protected function seedPriceList(): void
    {
        $services = Service::query()->orderBy('id')->limit(3)->get();

        if ($services->isEmpty()) {
            return;
        }

        $this->seedCatalogue('price-list', [
            'name' => 'Price list',
            'description' => 'Book any of these online or give us a call.',
            'is_published' => true,
            'order' => 50,
        ], [
            $this->section('Most popular', null, $services->map(fn (Service $service) => $this->item($service->name, null, [], [
                'service_id' => $service->id,
                'description' => $service->description,
            ]))->all()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $sections
     */
    protected function seedCatalogue(string $slug, array $attributes, array $sections): void
    {
        $catalogue = Catalogue::query()->firstOrCreate(['slug' => $slug], $attributes);

        if ($catalogue->wasRecentlyCreated) {
            /** @phpstan-ignore argument.type (built to the shape CatalogueContents expects) */
            $this->contents->sync($catalogue, $sections);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function section(string $name, ?string $description, array $items): array
    {
        return ['id' => null, 'name' => $name, 'description' => $description, 'items' => $items];
    }

    /**
     * @param  list<string>  $tags  Preset keys or tag names
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function item(string $name, ?int $price, array $tags, array $overrides = []): array
    {
        return [
            'id' => null,
            'name' => $name,
            'description' => null,
            'service_id' => null,
            'image_file_id' => null,
            'price' => $price,
            'is_available' => true,
            'tracks_stock' => false,
            'stock' => null,
            'tag_ids' => array_values(array_map(fn (string $tag) => $this->tags[$tag], $tags)),
            'variations' => [],
            ...$overrides,
        ];
    }

    /**
     * @return array{id: null, name: string, price: int, stock: int|null}
     */
    protected function variation(string $name, int $price, ?int $stock = null): array
    {
        return ['id' => null, 'name' => $name, 'price' => $price, 'stock' => $stock];
    }
}
