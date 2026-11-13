<?php

use App\Enums\Bookings\ServicePriceType;
use App\Enums\TenantModule;
use App\Models\Bookings\Service;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\ItemVariation;
use App\Models\Catalogue\Section;
use App\Models\Catalogue\Tag;
use App\Services\Authorisation\Enums\CataloguePermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\NavigationService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function catalogueItemData(array $overrides = []): array
{
    return [
        'id' => null,
        'name' => 'Garlic bread',
        'description' => null,
        'service_id' => null,
        'image_file_id' => null,
        'price' => '4.50',
        'is_available' => true,
        'tracks_stock' => false,
        'stock' => null,
        'tag_ids' => [],
        'variations' => [],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validCatalogueData(array $overrides = []): array
{
    return [
        'name' => 'Lunch menu',
        'slug' => '',
        'description' => 'Served from noon.',
        'is_published' => true,
        'available_days' => [],
        'available_from' => null,
        'available_until' => null,
        'starts_on' => null,
        'ends_on' => null,
        'sections' => [
            ['id' => null, 'name' => 'Starters', 'description' => null, 'items' => [catalogueItemData()]],
        ],
        ...$overrides,
    ];
}

describe('access', function () {
    test('users without the view permission cannot see catalogues', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.catalogue.catalogues.index'))->assertForbidden();
        $this->get(route('admin.catalogue.tags.index'))->assertForbidden();
    });

    test('users with only the view permission can see catalogues but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([CataloguePermission::ViewCatalogues]));
        $item = Item::factory()->create();
        $catalogue = $item->section->catalogue;

        $this->get(route('admin.catalogue.catalogues.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/catalogue/catalogue/Index')
                ->has('catalogues.data', 1)
                ->where('catalogues.data.0.items_count', 1)
                ->where('catalogues.data.0.contents', '1 section · 1 item')
                ->where('canCreate', false)
            );

        $this->get(route('admin.catalogue.catalogues.show', $catalogue))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/catalogue/catalogue/Show')
                ->where('canEdit', false)
                ->where('canUpdateStock', false)
            );
        $this->get(route('admin.catalogue.catalogues.create'))->assertForbidden();
        $this->get(route('admin.catalogue.catalogues.edit', $catalogue))->assertForbidden();
        $this->post(route('admin.catalogue.catalogues.store'), validCatalogueData())->assertForbidden();
        $this->put(route('admin.catalogue.catalogues.update', $catalogue), validCatalogueData())->assertForbidden();
        $this->delete(route('admin.catalogue.catalogues.destroy', $catalogue))->assertForbidden();
        $this->patch(route('admin.catalogue.catalogues.items.stock', [$catalogue, $item]), ['is_available' => false])->assertForbidden();
    });

    test('the pages return not found without the Catalogue module, and the menu hides them', function () {
        $user = $this->createUserWithPermissions(CataloguePermission::cases());
        $this->actingAs($user);

        $menuHasCatalogues = fn () => str_contains(
            json_encode(app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)->toArray(), JSON_UNESCAPED_SLASHES),
            route('admin.catalogue.catalogues.index', [], false)
        );

        expect($menuHasCatalogues())->toBeTrue();

        tenant()->update(['modules' => [TenantModule::Crm]]);

        expect($menuHasCatalogues())->toBeFalse();
        $this->get(route('admin.catalogue.catalogues.index'))->assertNotFound();
        $this->get(route('admin.catalogue.tags.index'))->assertNotFound();
    });
});

describe('saving', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CataloguePermission::cases()));
    });

    test('it creates a catalogue with its sections, items, variations and tags, in order', function () {
        $vegan = Tag::factory()->dietary()->create(['name' => 'Vegan']);

        $this->post(route('admin.catalogue.catalogues.store'), validCatalogueData([
            'available_days' => [5, 1],
            'available_from' => '12:00',
            'available_until' => '15:00',
            'sections' => [
                ['id' => null, 'name' => 'Starters', 'description' => null, 'items' => [
                    // A repeated tag is dropped, and other items can have the same tag
                    catalogueItemData(['name' => 'Soup', 'price' => '6.50', 'tag_ids' => [$vegan->id, $vegan->id]]),
                    catalogueItemData(['name' => 'Bread', 'tag_ids' => [$vegan->id]]),
                ]],
                ['id' => null, 'name' => 'Drinks', 'description' => 'Ask for the wine list.', 'items' => [
                    catalogueItemData(['name' => 'Wine', 'price' => '99', 'variations' => [
                        ['id' => null, 'name' => 'Glass', 'price' => '6.50', 'stock' => null],
                        ['id' => null, 'name' => 'Bottle', 'price' => '24', 'stock' => null],
                    ]]),
                ]],
            ],
        ]))->assertRedirect(route('admin.catalogue.catalogues.show', Catalogue::query()->sole()));

        $catalogue = Catalogue::query()->sole();
        $sections = $catalogue->sections()->with('items.variations', 'items.tags')->get();
        $wine = $sections[1]->items->sole();

        expect($catalogue->slug)->toBe('lunch-menu')
            ->and($catalogue->available_days)->toBe([1, 5])
            ->and($catalogue->available_from)->toBe('12:00:00')
            ->and($sections->pluck('name')->all())->toBe(['Starters', 'Drinks'])
            ->and($sections[0]->items->pluck('name')->all())->toBe(['Soup', 'Bread'])
            ->and($sections[0]->items[0]->price)->toBe(650)
            ->and($sections[0]->items[0]->tags->pluck('id')->all())->toBe([$vegan->id])
            ->and($sections[0]->items[1]->tags->pluck('id')->all())->toBe([$vegan->id])
            // Variations set the price, so the item's own isn't kept
            ->and($wine->price)->toBeNull()
            ->and($wine->variations->pluck('price', 'name')->all())->toBe(['Glass' => 650, 'Bottle' => 2400])
            ->and($wine->displayPrice())->toBe(['amount' => 650, 'from' => true]);
    });

    test('saving keeps what stays, moves items between sections and deletes what was removed', function () {
        $catalogue = Catalogue::factory()->create();
        $starters = Section::factory()->for($catalogue)->create(['name' => 'Starters', 'order' => 0]);
        $mains = Section::factory()->for($catalogue)->create(['name' => 'Mains', 'order' => 1]);
        $soup = Item::factory()->for($starters)->create(['name' => 'Soup']);
        $bread = Item::factory()->for($starters)->create(['name' => 'Bread']);
        $pie = Item::factory()->for($mains)->withVariations(['Small' => 900, 'Large' => 1200])->create(['name' => 'Pie']);
        [$small, $large] = $pie->variations;

        $this->put(route('admin.catalogue.catalogues.update', $catalogue), validCatalogueData([
            'name' => $catalogue->name,
            'slug' => $catalogue->slug,
            'sections' => [
                // Mains now first; Starters removed, with the bread moved into Mains first
                ['id' => $mains->id, 'name' => 'Mains', 'description' => null, 'items' => [
                    catalogueItemData(['id' => $bread->id, 'name' => 'Bread', 'price' => '3']),
                    catalogueItemData(['id' => $pie->id, 'name' => 'Pie', 'price' => null, 'variations' => [
                        ['id' => $large->id, 'name' => 'Large', 'price' => '13', 'stock' => null],
                    ]]),
                ]],
                ['id' => null, 'name' => 'Desserts', 'description' => null, 'items' => []],
            ],
        ]))->assertRedirect(route('admin.catalogue.catalogues.show', $catalogue));

        expect($catalogue->sections()->pluck('name')->all())->toBe(['Mains', 'Desserts'])
            ->and(Section::query()->find($starters->id))->toBeNull()
            ->and(Item::query()->find($soup->id))->toBeNull()
            ->and($bread->fresh()->section_id)->toBe($mains->id)
            ->and($bread->fresh()->order)->toBe(0)
            ->and($bread->fresh()->price)->toBe(300)
            ->and($pie->variations()->pluck('price', 'name')->all())->toBe(['Large' => 1300])
            ->and(ItemVariation::query()->find($small->id))->toBeNull();
    });

    test('ids from another catalogue are refused', function () {
        $catalogue = Catalogue::factory()->create();
        $otherItem = Item::factory()->create();

        $this->put(route('admin.catalogue.catalogues.update', $catalogue), validCatalogueData([
            'sections' => [
                ['id' => $otherItem->section_id, 'name' => 'Theirs', 'description' => null, 'items' => [
                    catalogueItemData(['id' => $otherItem->id]),
                ]],
            ],
        ]))->assertSessionHasErrors(['sections.0.id', 'sections.0.items.0.id']);

        expect($otherItem->fresh()->section_id)->toBe($otherItem->section_id);
    });

    test('an item can show a bookable service and use its price', function () {
        $service = Service::factory()->create(['price_type' => ServicePriceType::From, 'price' => 3500]);

        $this->post(route('admin.catalogue.catalogues.store'), validCatalogueData([
            'sections' => [['id' => null, 'name' => 'Cuts', 'description' => null, 'items' => [
                catalogueItemData(['name' => 'Haircut', 'price' => '99', 'service_id' => $service->id]),
            ]]],
        ]))->assertSessionHasNoErrors();

        $item = Item::query()->sole();

        expect($item->service_id)->toBe($service->id)
            ->and($item->price)->toBeNull()
            ->and($item->displayPrice())->toBe(['amount' => 3500, 'from' => true]);
    });

    test('a deleted service cannot be chosen', function () {
        $service = Service::factory()->create();
        $service->delete();

        $this->post(route('admin.catalogue.catalogues.store'), validCatalogueData([
            'sections' => [['id' => null, 'name' => 'Cuts', 'description' => null, 'items' => [
                catalogueItemData(['service_id' => $service->id]),
            ]]],
        ]))->assertSessionHasErrors('sections.0.items.0.service_id');
    });

    test('stock is counted on the item, or on each variation when it has them', function () {
        $this->post(route('admin.catalogue.catalogues.store'), validCatalogueData([
            'sections' => [['id' => null, 'name' => 'Shop', 'description' => null, 'items' => [
                catalogueItemData(['name' => 'Bag', 'tracks_stock' => true, 'stock' => 4]),
                catalogueItemData(['name' => 'Shirt', 'tracks_stock' => true, 'stock' => 9, 'variations' => [
                    ['id' => null, 'name' => 'Small', 'price' => '20', 'stock' => 0],
                    ['id' => null, 'name' => 'Large', 'price' => '20', 'stock' => 2],
                ]]),
                catalogueItemData(['name' => 'Mug', 'tracks_stock' => false, 'stock' => 3]),
            ]]],
        ]))->assertSessionHasNoErrors();

        $items = Item::query()->with('variations')->get()->keyBy('name');

        expect($items['Bag']->stock)->toBe(4)
            ->and($items['Shirt']->stock)->toBeNull()
            ->and($items['Shirt']->variations->pluck('stock')->all())->toBe([0, 2])
            ->and($items['Shirt']->isInStock())->toBeTrue()
            ->and($items['Mug']->stock)->toBeNull();
    });

    test('it validates the schedule and the contents', function () {
        $this->post(route('admin.catalogue.catalogues.store'), validCatalogueData([
            'name' => '',
            'available_days' => [8],
            'available_from' => '12:00',
            'available_until' => '12:00',
            'starts_on' => '2026-12-31',
            'ends_on' => '2026-12-01',
            'sections' => [['id' => null, 'name' => '', 'description' => null, 'items' => [
                catalogueItemData(['name' => '', 'price' => '-1', 'variations' => [
                    ['id' => null, 'name' => 'Glass', 'price' => '', 'stock' => null],
                ]]),
            ]]],
        ]))->assertSessionHasErrors([
            'name',
            'available_days.0',
            'available_until',
            'ends_on',
            'sections.0.name',
            'sections.0.items.0.name',
            'sections.0.items.0.price',
            'sections.0.items.0.variations.0.price',
        ]);
    });

    test('deleting a catalogue deletes everything in it', function () {
        $item = Item::factory()->withVariations()->create();
        $catalogue = $item->section->catalogue;

        $this->delete(route('admin.catalogue.catalogues.destroy', $catalogue))
            ->assertRedirect(route('admin.catalogue.catalogues.index'));

        expect(Catalogue::query()->count())->toBe(0)
            ->and(Item::query()->count())->toBe(0)
            ->and(ItemVariation::query()->count())->toBe(0);
    });
});

describe('stock', function () {
    test('staff who can manage stock can mark an item sold out and count its stock, but not edit the catalogue', function () {
        $this->actingAs($this->createUserWithPermissions([CataloguePermission::ViewCatalogues, CataloguePermission::ManageStock]));
        $bag = Item::factory()->withStock(4)->create(['name' => 'Bag']);
        $catalogue = $bag->section->catalogue;

        $this->patch(route('admin.catalogue.catalogues.items.stock', [$catalogue, $bag]), ['is_available' => false, 'stock' => 0])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($bag->fresh()->is_available)->toBeFalse()
            ->and($bag->fresh()->stock)->toBe(0)
            ->and($bag->fresh()->isAvailableNow())->toBeFalse();

        $this->get(route('admin.catalogue.catalogues.edit', $catalogue))->assertForbidden();
    });

    test('a variation\'s stock is counted on its own', function () {
        $this->actingAs($this->createUserWithPermissions([CataloguePermission::ViewCatalogues, CataloguePermission::ManageStock]));
        $shirt = Item::factory()->withStock(5)->withVariations(['Small' => 2000, 'Large' => 2000])->create();
        [$small, $large] = $shirt->variations;
        $other = ItemVariation::factory()->create();

        $this->patch(route('admin.catalogue.catalogues.items.stock', [$shirt->section->catalogue, $shirt]), [
            'variations' => [['id' => $small->id, 'stock' => 0], ['id' => $large->id, 'stock' => 7]],
        ])->assertSessionHasNoErrors();

        expect($small->fresh()->stock)->toBe(0)
            ->and($large->fresh()->stock)->toBe(7);

        $this->patch(route('admin.catalogue.catalogues.items.stock', [$shirt->section->catalogue, $shirt]), [
            'variations' => [['id' => $other->id, 'stock' => 1]],
        ])->assertSessionHasErrors('variations.0.id');
    });

    test('an item of another catalogue is not found', function () {
        $this->actingAs($this->createUserWithPermissions(CataloguePermission::cases()));
        $catalogue = Catalogue::factory()->create();
        $item = Item::factory()->create();

        $this->patch(route('admin.catalogue.catalogues.items.stock', [$catalogue, $item]), ['is_available' => false])
            ->assertNotFound();

        expect($item->fresh()->is_available)->toBeTrue();
    });
});
