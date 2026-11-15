<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\Section;
use App\Models\Cms\Layout;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Cms\Api\ContentPresenter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));

    $this->template = Template::factory()->ofType(TemplateType::Layout)->create();
    TemplateField::factory()->for($this->template)->create(['type' => TemplateFieldType::Catalogue, 'name' => 'Menu', 'slug' => 'menu']);
});

test('a catalogue is stored by id, and must exist', function () {
    $catalogue = Catalogue::factory()->create();

    $this->post(route('admin.cms.layouts.store'), ['template_id' => $this->template->id, 'name' => 'Main', 'content' => ['menu' => (string) $catalogue->id]])
        ->assertSessionHasNoErrors();

    expect(Layout::query()->sole()->contentValues())->toBe(['menu' => $catalogue->id]);

    $this->post(route('admin.cms.layouts.store'), ['template_id' => $this->template->id, 'name' => 'Other', 'content' => ['menu' => 999]])
        ->assertSessionHasErrors(['content.menu' => __('validation.exists', ['attribute' => 'Menu'])]);
});

test('the editor gets catalogues to choose from, drafts marked, but not without the module', function () {
    Catalogue::factory()->unpublished()->create(['name' => 'Festive']);
    $layout = Layout::factory()->create(['template_id' => $this->template->id]);

    $this->get(route('admin.cms.layouts.edit', $layout))
        ->assertInertia(fn (Assert $page) => $page
            ->where('catalogueOptions.0.label', 'Festive')
            ->where('catalogueOptions.0.published', false)
        );

    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('admin.cms.layouts.edit', $layout))
        ->assertInertia(fn (Assert $page) => $page->where('catalogueOptions', null));
});

test('a page gets the catalogue and where to ask what applies now, but not a draft or without the module', function () {
    $catalogue = Catalogue::factory()->create(['slug' => 'drinks', 'name' => 'Drinks']);
    Item::factory()->for(Section::factory()->for($catalogue))->create(['name' => 'Lemonade', 'price' => 300]);
    $draft = Catalogue::factory()->unpublished()->create();
    $template = $this->template->load('fields');
    $presenter = app(ContentPresenter::class);

    $menu = $presenter->present($template, ['menu' => $catalogue->id])['menu'];

    expect($menu['name'])->toBe('Drinks')
        ->and($menu['sections'][0]['items'][0]['price']['formatted'])->toBe('£3.00')
        ->and($menu['api_url'])->toBe(route('api.v1.catalogue.catalogues.show', 'drinks'))
        ->and($menu)->not->toHaveKey('applies_now')
        ->and($presenter->present($template, ['menu' => $draft->id])['menu'])->toBeNull();

    tenant()->update(['modules' => [TenantModule::Cms]]);

    expect($presenter->present($template, ['menu' => $catalogue->id])['menu'])->toBeNull();
});

test('the field type needs the Catalogue module', function () {
    expect(TemplateFieldType::Catalogue->requiredModule())->toBe(TenantModule::Catalogue);

    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->post(route('admin.cms.templates.store'), [
        'type' => 'page',
        'name' => 'Menu page',
        'fields' => [['type' => 'catalogue', 'name' => 'Menu', 'slug' => 'menu']],
    ])->assertSessionHasErrors('fields.0.type');
});
