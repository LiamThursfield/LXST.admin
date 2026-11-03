<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Models\Cms\Content;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Cms\Api\ContentVersion;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A component template with a required title (max 20) and a list of items with a required name
 */
function priceCardTemplate(): Template
{
    $item = Template::factory()->ofType(TemplateType::Repeater)->create(['name' => 'Price item']);
    TemplateField::factory()->for($item)->create(['type' => TemplateFieldType::Text, 'name' => 'Name', 'slug' => 'name', 'is_required' => true]);

    $template = Template::factory()->ofType(TemplateType::Component)->create(['name' => 'Price card']);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Text, 'name' => 'Title', 'slug' => 'title', 'is_required' => true, 'order' => 0, 'settings' => ['max_length' => 20]]);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Repeater, 'name' => 'Items', 'slug' => 'items', 'order' => 1, 'settings' => ['template_id' => $item->id]]);

    return $template;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function savedComponentData(Template $template, array $overrides = []): array
{
    return [
        'template_id' => $template->id,
        'name' => 'Standard pricing',
        'slug' => '',
        'content' => ['title' => 'Weddings', 'items' => [['name' => 'Evening reception']]],
        ...$overrides,
    ];
}

describe('managing', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('saved components are listed with their templates, and can be filtered by template', function () {
        $template = priceCardTemplate();
        SavedComponent::factory()->create(['name' => 'Standard pricing', 'template_id' => $template->id]);
        SavedComponent::factory()->create(['name' => 'Another']);

        $this->get(route('admin.cms.saved-components.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/saved-component/Index')
                ->has('savedComponents.data', 2)
                ->where('savedComponents.data.1.template.name', 'Price card')
            );

        $this->get(route('admin.cms.saved-components.index', ['filter' => ['template_id' => $template->id]]))
            ->assertInertia(fn (Assert $page) => $page->has('savedComponents.data', 1)->where('savedComponents.data.0.name', 'Standard pricing'));
    });

    test('only component templates can be chosen, and they come with their fields', function () {
        priceCardTemplate();
        Template::factory()->create(['name' => 'A page template']);

        $this->get(route('admin.cms.saved-components.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/saved-component/Create')
                ->has('templates.data', 1)
                ->where('templates.data.0.name', 'Price card')
                ->has('templates.data.0.fields', 2)
                ->has('nestedTemplates.data', 2)
            );
    });

    test('a saved component is created with its content, and a slug made from its name', function () {
        $template = priceCardTemplate();

        $this->post(route('admin.cms.saved-components.store'), savedComponentData($template))
            ->assertRedirect(route('admin.cms.saved-components.index'))
            ->assertSessionHas('success', __('cms.saved_components.flash.created'));

        $savedComponent = SavedComponent::query()->sole();

        expect($savedComponent->slug)->toBe('standard-pricing')
            ->and($savedComponent->contentValues())->toBe(['title' => 'Weddings', 'items' => [['name' => 'Evening reception']]]);

        $this->get(route('admin.cms.saved-components.edit', $savedComponent))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/saved-component/Edit')
                ->where('savedComponent.data.content.title', 'Weddings')
            );
    });

    test('updating replaces content, and empty optional fields remove their content', function () {
        $template = priceCardTemplate();
        $this->post(route('admin.cms.saved-components.store'), savedComponentData($template));
        $savedComponent = SavedComponent::query()->sole();

        $this->put(route('admin.cms.saved-components.update', $savedComponent), savedComponentData($template, [
            'slug' => 'standard-pricing',
            'content' => ['title' => 'Parties', 'items' => null],
        ]))->assertSessionHasNoErrors();

        expect($savedComponent->refresh()->contentValues())->toBe(['title' => 'Parties', 'items' => null])
            ->and($savedComponent->content()->count())->toBe(1);
    });

    test('changing the template keeps content for fields with the same slug and removes the rest', function () {
        $template = priceCardTemplate();
        $this->post(route('admin.cms.saved-components.store'), savedComponentData($template));
        $savedComponent = SavedComponent::query()->sole();

        $other = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->for($other)->create(['type' => TemplateFieldType::Text, 'slug' => 'title']);

        $this->put(route('admin.cms.saved-components.update', $savedComponent), savedComponentData($other, ['slug' => 'standard-pricing']))
            ->assertSessionHasNoErrors();

        expect($savedComponent->refresh()->contentValues())->toBe(['title' => 'Weddings'])
            ->and($savedComponent->content()->count())->toBe(1);
    });

    test('a saved component can be deleted, with its content', function () {
        $this->post(route('admin.cms.saved-components.store'), savedComponentData(priceCardTemplate()));

        $this->delete(route('admin.cms.saved-components.destroy', SavedComponent::query()->sole()))
            ->assertSessionHas('success', __('cms.saved_components.flash.deleted'));

        expect(SavedComponent::query()->count())->toBe(0)
            ->and(Content::query()->count())->toBe(0);
    });

    test('saving a saved component refreshes cached pages', function () {
        $version = app(ContentVersion::class);
        $before = $version->current();

        $this->post(route('admin.cms.saved-components.store'), savedComponentData(priceCardTemplate()))
            ->assertSessionHasNoErrors();

        expect($version->current())->not->toBe($before);
    });
});

describe('validation', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('content is validated against the template\'s fields, including nested ones', function () {
        $this->post(route('admin.cms.saved-components.store'), savedComponentData(priceCardTemplate(), [
            'content' => ['title' => str_repeat('a', 21), 'items' => [['name' => '']]],
        ]))->assertSessionHasErrors([
            'content.title' => __('validation.max.string', ['attribute' => 'Title', 'max' => 20]),
            'content.items.0.name' => __('validation.required', ['attribute' => 'Items Name']),
        ]);

        expect(SavedComponent::query()->count())->toBe(0);
    });

    test('the template must be a component template', function (TemplateType $type) {
        $template = Template::factory()->ofType($type)->create();

        $this->post(route('admin.cms.saved-components.store'), savedComponentData($template))
            ->assertSessionHasErrors(['template_id' => __('cms.saved_components.validation.template_type')]);
    })->with([TemplateType::Page, TemplateType::Layout, TemplateType::Repeater]);

    test('slugs are unique, but a saved component can keep its own', function () {
        $template = priceCardTemplate();
        SavedComponent::factory()->create(['slug' => 'taken']);
        $savedComponent = SavedComponent::factory()->create(['slug' => 'mine', 'template_id' => $template->id]);

        $this->post(route('admin.cms.saved-components.store'), savedComponentData($template, ['slug' => 'taken']))
            ->assertSessionHasErrors('slug');

        $this->put(route('admin.cms.saved-components.update', $savedComponent), savedComponentData($template, ['slug' => 'mine']))
            ->assertSessionHasNoErrors();
    });

    test('a template used by a saved component can\'t be deleted or change type', function () {
        $template = SavedComponent::factory()->create()->template;

        expect($template->isInUse())->toBeTrue();

        $this->delete(route('admin.cms.templates.destroy', $template))
            ->assertSessionHasErrors(['template' => __('cms.templates.validation.in_use')]);

        $this->put(route('admin.cms.templates.update', $template), [
            'name' => $template->name,
            'slug' => $template->slug,
            'type' => TemplateType::Page->value,
        ])->assertSessionHasErrors('type');

        expect($template->refresh()->type)->toBe(TemplateType::Component);
    });
});

describe('access', function () {
    test('editors can manage saved components with content permissions, and no structure ones', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent, CmsPermission::ManageContent]));
        $template = priceCardTemplate();

        $this->get(route('admin.cms.saved-components.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canCreate', true));
        $this->get(route('admin.cms.saved-components.create'))->assertOk();

        $this->post(route('admin.cms.saved-components.store'), savedComponentData($template))->assertSessionHasNoErrors();
        $savedComponent = SavedComponent::query()->sole();

        $this->get(route('admin.cms.saved-components.edit', $savedComponent))->assertOk();
        $this->put(route('admin.cms.saved-components.update', $savedComponent), savedComponentData($template, ['slug' => 'standard-pricing']))->assertSessionHasNoErrors();
        $this->delete(route('admin.cms.saved-components.destroy', $savedComponent))->assertSessionHasNoErrors();

        $this->get(route('admin.cms.templates.edit', $template))->assertForbidden();
    });

    test('users who can only view content can view saved components but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent]));
        $savedComponent = SavedComponent::factory()->create();

        $this->get(route('admin.cms.saved-components.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
        $this->get(route('admin.cms.saved-components.show', $savedComponent))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/cms/saved-component/Show'));

        $this->get(route('admin.cms.saved-components.create'))->assertForbidden();
        $this->get(route('admin.cms.saved-components.edit', $savedComponent))->assertForbidden();
        $this->post(route('admin.cms.saved-components.store'), savedComponentData($savedComponent->template))->assertForbidden();
        $this->put(route('admin.cms.saved-components.update', $savedComponent), savedComponentData($savedComponent->template))->assertForbidden();
        $this->delete(route('admin.cms.saved-components.destroy', $savedComponent))->assertForbidden();
    });

    test('structure permissions alone don\'t give access to saved components', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewStructure, CmsPermission::ManageStructure]));
        $savedComponent = SavedComponent::factory()->create();

        $this->get(route('admin.cms.saved-components.index'))->assertForbidden();
        $this->get(route('admin.cms.saved-components.show', $savedComponent))->assertForbidden();
        $this->delete(route('admin.cms.saved-components.destroy', $savedComponent))->assertForbidden();
    });

    test('saved components are hidden when the tenant doesn\'t have the CMS module', function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->get(route('admin.cms.saved-components.index'))->assertNotFound();
    });
});
