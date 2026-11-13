<?php

use App\Models\Bookings\Service;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\ItemVariation;
use App\Models\Catalogue\Section;
use App\Models\Catalogue\Tag;
use Database\Seeders\CatalogueSeeder;

it('seeds catalogues showing every feature, and is safe to re-run', function () {
    Service::factory()->count(2)->create();

    $this->seed(CatalogueSeeder::class);
    $this->seed(CatalogueSeeder::class);

    $items = Item::query()->with('variations')->get()->keyBy('name');

    expect(Catalogue::query()->count())->toBe(5)
        ->and(Section::query()->count())->toBe(8)
        ->and(Item::query()->count())->toBe(18)
        ->and(ItemVariation::query()->count())->toBe(10)
        ->and(Tag::query()->count())->toBe(24)
        ->and(Catalogue::query()->where('is_published', false)->count())->toBe(1)
        ->and(Catalogue::query()->firstWhere('slug', 'drinks')->appliesAt(now()))->toBeTrue()
        ->and(Catalogue::query()->firstWhere('slug', 'lunch-menu')->hasSchedule())->toBeTrue()
        ->and($items['Salt and pepper squid']->is_available)->toBeFalse()
        ->and($items['Gift box']->isInStock())->toBeFalse()
        ->and($items['T-shirt']->variations->pluck('stock')->all())->toBe([5, 0, 3])
        ->and(Item::query()->whereNotNull('service_id')->count())->toBe(2)
        ->and($items['Fish and chips']->tags()->count())->toBe(3);
});
