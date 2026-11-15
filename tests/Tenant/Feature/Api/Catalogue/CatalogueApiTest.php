<?php

use App\Enums\Catalogue\TagKind;
use App\Enums\TenantModule;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\Section;
use App\Models\Catalogue\Tag;

test('a catalogue shows its sections, items, prices, variations, tags and what can be had now', function () {
    $this->travelTo('2026-11-02 13:00:00'); // A Monday

    $catalogue = Catalogue::factory()->availableAt([1, 2, 3, 4, 5], '12:00:00', '15:00:00')->create(['slug' => 'lunch', 'name' => 'Lunch']);
    $section = Section::factory()->for($catalogue)->create(['name' => 'Mains']);
    $gluten = Tag::factory()->preset('gluten')->create(['name' => 'Gluten', 'icon' => 'i-lucide-wheat']);
    Item::factory()->for($section)->create(['name' => 'Pie', 'price' => 1250, 'order' => 0])->tags()->attach($gluten);
    Item::factory()->for($section)->withVariations(['Small' => 450, 'Large' => 600])->create(['name' => 'Soup', 'order' => 1]);
    Item::factory()->for($section)->soldOut()->create(['name' => 'Fish', 'order' => 2]);
    Item::factory()->for($section)->withStock(0)->create(['name' => 'Tart', 'order' => 3]);

    $this->getJson(route('api.v1.catalogue.catalogues.show', 'lunch'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Lunch')
        ->assertJsonPath('data.currency', 'GBP')
        ->assertJsonPath('data.schedule.days', [1, 2, 3, 4, 5])
        ->assertJsonPath('data.schedule.from', '12:00')
        ->assertJsonPath('data.schedule.until', '15:00')
        ->assertJsonPath('data.applies_now', true)
        ->assertJsonPath('data.url', route('catalogue.public.show', 'lunch'))
        ->assertJsonPath('data.sections.0.name', 'Mains')
        ->assertJsonPath('data.sections.0.items.0.name', 'Pie')
        ->assertJsonPath('data.sections.0.items.0.price', ['amount' => 1250, 'formatted' => '£12.50', 'from' => false])
        ->assertJsonPath('data.sections.0.items.0.tags.0', ['name' => 'Gluten', 'kind' => TagKind::Allergen->value, 'icon' => 'i-lucide-wheat', 'preset' => 'gluten'])
        ->assertJsonPath('data.sections.0.items.1.price', ['amount' => 450, 'formatted' => '£4.50', 'from' => true])
        ->assertJsonPath('data.sections.0.items.1.variations.1', ['name' => 'Large', 'price' => ['amount' => 600, 'formatted' => '£6.00'], 'in_stock' => true])
        ->assertJsonPath('data.sections.0.items.2.available', false)
        ->assertJsonPath('data.sections.0.items.2.sold_out', true)
        ->assertJsonPath('data.sections.0.items.3.available', false)
        ->assertJsonPath('data.sections.0.items.3.in_stock', false);
});

test('whether it applies now follows the clock, so it is not cached', function () {
    $catalogue = Catalogue::factory()->availableAt(null, '12:00:00', '15:00:00')->create();

    $this->travelTo('2026-11-02 13:00:00');
    $this->getJson(route('api.v1.catalogue.catalogues.show', $catalogue->slug))->assertJsonPath('data.applies_now', true);

    $this->travelTo('2026-11-02 18:00:00');
    $this->getJson(route('api.v1.catalogue.catalogues.show', $catalogue->slug))->assertJsonPath('data.applies_now', false);
});

test('the list has every published catalogue, or only those that apply now', function () {
    $this->travelTo('2026-11-02 13:00:00');

    Catalogue::factory()->create(['name' => 'Drinks', 'order' => 1]);
    Catalogue::factory()->availableAt(null, '18:00:00', '22:00:00')->create(['name' => 'Dinner', 'order' => 0]);
    Catalogue::factory()->unpublished()->create(['name' => 'Festive']);

    $this->getJson(route('api.v1.catalogue.catalogues.index'))
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Dinner', 'Drinks']);

    $this->getJson(route('api.v1.catalogue.catalogues.index', ['applies_now' => 1]))
        ->assertJsonPath('data.*.name', ['Drinks']);
});

test('a draft is not found, nor is anything without the Catalogue module', function () {
    $draft = Catalogue::factory()->unpublished()->create();
    $published = Catalogue::factory()->create();

    $this->getJson(route('api.v1.catalogue.catalogues.show', $draft->slug))->assertNotFound();

    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->getJson(route('api.v1.catalogue.catalogues.show', $published->slug))->assertNotFound();
    $this->getJson(route('api.v1.catalogue.catalogues.index'))->assertNotFound();
});
