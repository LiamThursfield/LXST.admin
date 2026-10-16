<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Models\Cms\Content;
use App\Models\Cms\Layout;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Authorisation\Enums\CmsPermission;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A layout template with a required text field (max 20), a text area and a number (1–10)
 */
function layoutTemplate(): Template
{
    $template = Template::factory()->ofType(TemplateType::Layout)->create(['name' => 'Default layout']);

    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Text, 'name' => 'Site title', 'slug' => 'site_title', 'is_required' => true, 'order' => 0, 'settings' => ['max_length' => 20]]);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Textarea, 'name' => 'Footer', 'slug' => 'footer', 'order' => 1]);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Number, 'name' => 'Columns', 'slug' => 'columns', 'order' => 2, 'settings' => ['min' => 1, 'max' => 10]]);

    return $template;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function layoutData(Template $template, array $overrides = []): array
{
    return [
        'template_id' => $template->id,
        'name' => 'Main',
        'slug' => '',
        'content' => ['site_title' => 'Acme', 'footer' => "Line one\nLine two", 'columns' => '3'],
        ...$overrides,
    ];
}

describe('managing', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('layouts are listed with their templates, and can be filtered by template', function () {
        $template = layoutTemplate();
        Layout::factory()->create(['name' => 'Main', 'template_id' => $template->id]);
        Layout::factory()->create(['name' => 'Landing']);

        $this->get(route('admin.cms.layouts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/layout/Index')
                ->has('layouts.data', 2)
                ->where('layouts.data.1.template.name', 'Default layout')
            );

        $this->get(route('admin.cms.layouts.index', ['filter' => ['template_id' => $template->id]]))
            ->assertInertia(fn (Assert $page) => $page->has('layouts.data', 1)->where('layouts.data.0.name', 'Main'));
    });

    test('only layout templates can be chosen, and they come with their fields', function () {
        layoutTemplate();
        Template::factory()->create(['name' => 'A page template']);

        $this->get(route('admin.cms.layouts.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/layout/Create')
                ->has('templates.data', 1)
                ->has('templates.data.0.fields', 3)
            );
    });

    test('a layout is created with its content, keyed by field slug, and stored as JSON', function () {
        $template = layoutTemplate();

        $this->post(route('admin.cms.layouts.store'), layoutData($template))
            ->assertRedirect(route('admin.cms.layouts.index'))
            ->assertSessionHas('success', __('cms.layouts.flash.created'));

        $layout = Layout::query()->sole();

        expect($layout->slug)->toBe('main')
            ->and($layout->contentValues())->toBe(['site_title' => 'Acme', 'footer' => "Line one\nLine two", 'columns' => 3])
            // Stored as real JSON: the number as a number, the text as a string
            ->and(DB::connection('tenant')->table('cms_content')->pluck('data')->sort()->values()->all())->toBe(['"Acme"', '"Line one\\nLine two"', '3'])
            ->and($layout->content()->whereHas('templateField', fn ($query) => $query->where('slug', 'site_title'))->sole()->template_field_type)->toBe(TemplateFieldType::Text);

        $this->get(route('admin.cms.layouts.edit', $layout))
            ->assertInertia(fn (Assert $page) => $page->where('layout.data.content.columns', 3));
    });

    test('updating replaces content, and empty optional fields remove their content', function () {
        $template = layoutTemplate();
        $this->post(route('admin.cms.layouts.store'), layoutData($template));
        $layout = Layout::query()->sole();

        $this->put(route('admin.cms.layouts.update', $layout), layoutData($template, [
            'slug' => 'main',
            'content' => ['site_title' => 'Acme Ltd', 'footer' => '', 'columns' => null],
        ]))->assertSessionHasNoErrors();

        expect($layout->refresh()->contentValues())->toBe(['site_title' => 'Acme Ltd', 'footer' => null, 'columns' => null])
            ->and($layout->content()->count())->toBe(1);
    });

    test('changing the template keeps content for fields with the same slug and removes the rest', function () {
        $template = layoutTemplate();
        $this->post(route('admin.cms.layouts.store'), layoutData($template));
        $layout = Layout::query()->sole();

        $other = Template::factory()->ofType(TemplateType::Layout)->create();
        TemplateField::factory()->for($other)->create(['type' => TemplateFieldType::Text, 'slug' => 'site_title']);

        $this->put(route('admin.cms.layouts.update', $layout), layoutData($other, ['slug' => 'main']))
            ->assertSessionHasNoErrors();

        expect($layout->refresh()->contentValues())->toBe(['site_title' => 'Acme'])
            ->and($layout->content()->count())->toBe(1);
    });

    test('a layout can be deleted, with its content', function () {
        $template = layoutTemplate();
        $this->post(route('admin.cms.layouts.store'), layoutData($template));
        $layout = Layout::query()->sole();

        $this->delete(route('admin.cms.layouts.destroy', $layout))
            ->assertSessionHas('success', __('cms.layouts.flash.deleted'));

        expect(Layout::query()->count())->toBe(0)
            ->and(Content::query()->count())->toBe(0);
    });

    test('removing a field from the template removes its content', function () {
        $template = layoutTemplate();
        $this->post(route('admin.cms.layouts.store'), layoutData($template));

        $template->fields()->where('slug', 'footer')->delete();

        expect(Layout::query()->sole()->contentValues())->toBe(['site_title' => 'Acme', 'columns' => 3]);
    });
});

describe('validation', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('content is validated against the template\'s fields', function () {
        $template = layoutTemplate();

        $this->post(route('admin.cms.layouts.store'), layoutData($template, [
            'content' => ['site_title' => '', 'columns' => 11],
        ]))->assertSessionHasErrors([
            'content.site_title' => __('validation.required', ['attribute' => 'Site title']),
            'content.columns' => __('validation.max.numeric', ['attribute' => 'Columns', 'max' => 10]),
        ]);

        $this->post(route('admin.cms.layouts.store'), layoutData($template, [
            'content' => ['site_title' => str_repeat('a', 21), 'columns' => 'many'],
        ]))->assertSessionHasErrors([
            'content.site_title' => __('validation.max.string', ['attribute' => 'Site title', 'max' => 20]),
            'content.columns' => __('validation.numeric', ['attribute' => 'Columns']),
        ]);
    });

    test('the template must be a layout template, and slugs are unique', function () {
        $page = Template::factory()->create();
        Layout::factory()->create(['slug' => 'main']);

        $this->post(route('admin.cms.layouts.store'), layoutData($page, ['slug' => 'main']))
            ->assertSessionHasErrors([
                'template_id' => __('cms.layouts.validation.template_type'),
                'slug',
            ]);
    });

    test('a template used by a layout can\'t be deleted or change type', function () {
        $layout = Layout::factory()->create();

        expect($layout->template->isInUse())->toBeTrue();

        $this->delete(route('admin.cms.templates.destroy', $layout->template))
            ->assertSessionHasErrors(['template' => __('cms.templates.validation.in_use')]);
    });
});

describe('access', function () {
    test('users who can only view the site structure can view layouts but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewStructure]));
        $layout = Layout::factory()->create();

        $this->get(route('admin.cms.layouts.show', $layout))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/cms/layout/Show'));

        $this->get(route('admin.cms.layouts.create'))->assertForbidden();
        $this->get(route('admin.cms.layouts.edit', $layout))->assertForbidden();
        $this->post(route('admin.cms.layouts.store'), layoutData($layout->template))->assertForbidden();
        $this->put(route('admin.cms.layouts.update', $layout), layoutData($layout->template))->assertForbidden();
        $this->delete(route('admin.cms.layouts.destroy', $layout))->assertForbidden();
    });

    test('layouts are hidden when the tenant doesn\'t have the CMS module', function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->get(route('admin.cms.layouts.index'))->assertNotFound();
    });
});
