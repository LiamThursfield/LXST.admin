<?php

use App\Enums\Catalogue\TagKind;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\Tag;
use App\Services\Authorisation\Enums\CataloguePermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions(CataloguePermission::cases()));
});

test('the tags page lists tags and the built-in groups, with those the industry suggests', function () {
    tenant()->update(['industry' => 'hospitality']);
    Tag::factory()->create(['name' => 'Bestseller']);

    $this->get(route('admin.catalogue.tags.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/catalogue/tag/Index')
            ->has('tags.data', 1)
            ->where('presetGroups.0.key', 'allergens')
            ->where('presetGroups.0.suggested', true)
            ->where('presetGroups.0.missing', 14)
            ->where('canCreate', true)
        );
});

test('adding a built-in group adds its tags once, leaving renamed ones alone', function () {
    $this->post(route('admin.catalogue.tags.presets.store'), ['group' => 'allergens'])
        ->assertSessionHas('success', '14 tags added from Allergens.');

    Tag::query()->firstWhere('preset', 'milk')->update(['name' => 'Dairy']);

    $this->post(route('admin.catalogue.tags.presets.store'), ['group' => 'allergens'])
        ->assertSessionHas('success', 'Every tag in Allergens was already added.');

    expect(Tag::query()->where('kind', TagKind::Allergen)->count())->toBe(14)
        ->and(Tag::query()->firstWhere('preset', 'milk')->name)->toBe('Dairy')
        ->and(Tag::query()->firstWhere('preset', 'gluten_free'))->toBeNull();

    $this->post(route('admin.catalogue.tags.presets.store'), ['group' => 'unknown'])
        ->assertSessionHasErrors('group');
});

test('a tag can be created, changed and deleted, which takes it off items', function () {
    $this->post(route('admin.catalogue.tags.store'), ['name' => 'New', 'kind' => 'other', 'icon' => 'i-lucide-sparkles'])
        ->assertRedirect(route('admin.catalogue.tags.index'));

    $tag = Tag::query()->sole();
    $item = Item::factory()->create();
    $item->tags()->attach($tag);

    $this->put(route('admin.catalogue.tags.update', $tag), ['name' => 'Just in', 'kind' => 'other', 'icon' => null])
        ->assertRedirect(route('admin.catalogue.tags.index'));

    expect($tag->fresh()->name)->toBe('Just in')
        ->and($tag->fresh()->icon)->toBeNull();

    $this->post(route('admin.catalogue.tags.store'), ['name' => 'Odd', 'kind' => 'other', 'icon' => 'i-lucide-skull'])
        ->assertSessionHasErrors('icon');

    $this->delete(route('admin.catalogue.tags.destroy', $tag));

    expect(Tag::query()->count())->toBe(0)
        ->and($item->tags()->count())->toBe(0);
});

test('only users who can manage catalogues can change tags', function () {
    $this->actingAs($this->createUserWithPermissions([CataloguePermission::ViewCatalogues]));
    $tag = Tag::factory()->create();

    $this->get(route('admin.catalogue.tags.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', false)->where('presetGroups', []));
    $this->post(route('admin.catalogue.tags.store'), ['name' => 'New', 'kind' => 'other'])->assertForbidden();
    $this->post(route('admin.catalogue.tags.presets.store'), ['group' => 'dietary'])->assertForbidden();
    $this->put(route('admin.catalogue.tags.update', $tag), ['name' => 'New', 'kind' => 'other'])->assertForbidden();
    $this->delete(route('admin.catalogue.tags.destroy', $tag))->assertForbidden();
});
