<?php

use App\Enums\TenantModule;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\Section;
use App\Services\Authorisation\Enums\CataloguePermission;
use Inertia\Testing\AssertableInertia as Assert;

test('the public page shows a published catalogue to anyone, and can open to print', function () {
    $catalogue = Catalogue::factory()->create(['slug' => 'drinks', 'name' => 'Drinks']);
    $section = Section::factory()->for($catalogue)->create(['name' => 'Beer']);
    Item::factory()->for($section)->create(['name' => 'Pale ale', 'price' => 550]);

    $this->get(route('catalogue.public.show', ['catalogue' => 'drinks', 'print' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/catalogue/Show')
            ->where('catalogue.name', 'Drinks')
            ->where('catalogue.sections.0.items.0.price.formatted', '£5.50')
            ->where('isDraft', false)
            ->where('print', true)
        );
});

test('a draft is only shown to staff who can view catalogues', function () {
    $draft = Catalogue::factory()->unpublished()->create();

    $this->get(route('catalogue.public.show', $draft->slug))->assertNotFound();

    $this->actingAs($this->createUserWithPermissions([CataloguePermission::ViewCatalogues]))
        ->get(route('catalogue.public.show', $draft->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('isDraft', true));
});

test('the list shows published catalogues, those that apply now first', function () {
    $this->travelTo('2026-11-02 13:00:00');

    Catalogue::factory()->availableAt(null, '18:00:00', '22:00:00')->create(['name' => 'Dinner', 'order' => 0]);
    Catalogue::factory()->availableAt(null, '12:00:00', '15:00:00')->create(['name' => 'Lunch', 'order' => 1]);
    Catalogue::factory()->unpublished()->create(['name' => 'Festive']);

    $this->get(route('catalogue.public.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/catalogue/Index')
            ->has('catalogues', 2)
            ->where('catalogues.0.name', 'Lunch')
            ->where('catalogues.0.applies_now', true)
            ->where('catalogues.1.name', 'Dinner')
        );
});

test('the public pages need the Catalogue module', function () {
    $catalogue = Catalogue::factory()->create();

    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('catalogue.public.index'))->assertNotFound();
    $this->get(route('catalogue.public.show', $catalogue->slug))->assertNotFound();
});

describe('QR codes', function () {
    test('staff who can view catalogues get a QR code for each catalogue and for the list, to show or download', function () {
        $catalogue = Catalogue::factory()->create(['slug' => 'drinks']);

        $this->actingAs($this->createUserWithPermissions([CataloguePermission::ViewCatalogues]));

        $this->get(route('admin.catalogue.catalogues.qr-code', $catalogue))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeaderMissing('Content-Disposition');

        $this->get(route('admin.catalogue.catalogues.qr-code', ['catalogue' => $catalogue, 'download' => 1]))
            ->assertHeader('Content-Disposition', 'attachment; filename="drinks-qr-code.svg"');

        $this->get(route('admin.catalogue.catalogues.qr-code.index'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    });

    test('they need the view permission', function () {
        $catalogue = Catalogue::factory()->create();

        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.catalogue.catalogues.qr-code', $catalogue))->assertForbidden();
        $this->get(route('admin.catalogue.catalogues.qr-code.index'))->assertForbidden();
    });
});
