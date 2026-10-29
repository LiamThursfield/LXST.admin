<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateFieldWidth;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\TemplateSection;
use App\Services\Authorisation\Enums\CmsPermission;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function templateData(array $overrides = []): array
{
    return [
        'type' => 'page',
        'name' => 'Landing page',
        'slug' => '',
        'description' => null,
        'fields' => [],
        ...$overrides,
    ];
}

/**
 * The fields as the builder sends them back, keeping each one's id
 *
 * @return list<array<string, mixed>>
 */
function builderFields(Template $template): array
{
    return $template->fields()->get()->map(fn (TemplateField $field) => [
        'id' => $field->id,
        'type' => $field->type->value,
        'name' => $field->name,
        'slug' => $field->slug,
        'description' => $field->description,
        'is_required' => $field->is_required,
        'settings' => $field->settings?->toArray() ?? [],
    ])->all();
}

describe('managing', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('templates are listed with their field counts, and can be filtered by type', function () {
        Template::factory()->has(TemplateField::factory()->count(2), 'fields')->create(['name' => 'Home']);
        Template::factory()->ofType(TemplateType::Layout)->create(['name' => 'Default']);

        $this->get(route('admin.cms.templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/template/Index')
                ->has('templates.data', 2)
                ->where('templates.data.0.name', 'Default')
                ->where('templates.data.1.fields_count', 2)
            );

        $this->get(route('admin.cms.templates.index', ['filter' => ['type' => 'layout']]))
            ->assertInertia(fn (Assert $page) => $page->has('templates.data', 1)->where('templates.data.0.name', 'Default'));
    });

    test('the builder offers every field type, now each has an editor', function () {
        $this->get(route('admin.cms.templates.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/template/Create')
                ->where('fieldTypes', fn ($types) => collect($types)->where('isAvailable', true)->count() === count(TemplateFieldType::cases()))
            );
    });

    test('a template is created with its fields in order, slugs made from names, and only each type\'s settings', function () {
        $this->post(route('admin.cms.templates.store'), templateData([
            'fields' => [
                ['type' => 'text', 'name' => 'Hero Title', 'slug' => '', 'is_required' => true, 'settings' => ['max_length' => 80, 'rows' => 4]],
                ['type' => 'number', 'name' => 'Count', 'slug' => 'count', 'description' => 'How many', 'settings' => ['min' => 1, 'max' => 10]],
            ],
        ]))
            ->assertRedirect(route('admin.cms.templates.index'))
            ->assertSessionHas('success', __('cms.templates.flash.created'));

        $template = Template::query()->sole();
        [$title, $count] = $template->fields->all();

        expect($template->slug)->toBe('landing-page')
            ->and($title->slug)->toBe('hero_title')
            ->and($title->is_required)->toBeTrue()
            ->and($title->settings?->max_length)->toBe(80)
            // Rows only apply to text areas
            ->and($title->settings?->rows)->toBeNull()
            ->and($count->order)->toBe(1)
            ->and($count->description)->toBe('How many')
            ->and($count->settings?->max)->toBe(10);
    });

    test('updating syncs the fields: edited, reordered, slugs swapped, removed and added', function () {
        $template = Template::factory()->create();
        $first = TemplateField::factory()->for($template)->create(['slug' => 'first', 'order' => 0]);
        $second = TemplateField::factory()->for($template)->create(['slug' => 'second', 'order' => 1]);
        $removed = TemplateField::factory()->for($template)->create(['slug' => 'removed', 'order' => 2]);

        $this->put(route('admin.cms.templates.update', $template), templateData([
            'name' => $template->name,
            'slug' => $template->slug,
            'fields' => [
                ['id' => $second->id, 'type' => 'text', 'name' => 'Second', 'slug' => 'first'],
                ['id' => $first->id, 'type' => 'textarea', 'name' => 'First', 'slug' => 'second', 'settings' => ['rows' => 3]],
                ['type' => 'number', 'name' => 'New', 'slug' => 'new'],
            ],
        ]))->assertSessionHasNoErrors();

        $fields = $template->fields()->get();

        expect($fields->pluck('slug')->all())->toBe(['first', 'second', 'new'])
            ->and($fields->take(2)->pluck('id')->all())->toBe([$second->id, $first->id])
            ->and($first->refresh()->type)->toBe(TemplateFieldType::Textarea)
            ->and(TemplateField::query()->find($removed->id))->toBeNull();
    });

    test('a template is created with sections, its fields placed in them in one order, and half widths kept', function () {
        $this->post(route('admin.cms.templates.store'), templateData([
            'fields' => [
                ['type' => 'text', 'name' => 'Intro', 'slug' => 'intro'],
            ],
            'sections' => [
                ['name' => 'Hero', 'description' => 'The banner', 'is_collapsed' => true, 'fields' => [
                    ['type' => 'text', 'name' => 'Hero title', 'slug' => '', 'settings' => ['width' => 'half', 'max_length' => 80]],
                    ['type' => 'image', 'name' => 'Hero image', 'slug' => 'hero_image', 'settings' => ['width' => 'full']],
                ]],
                ['name' => 'Body', 'fields' => [
                    ['type' => 'wysiwyg', 'name' => 'Body', 'slug' => 'body'],
                ]],
            ],
        ]))->assertSessionHasNoErrors();

        $template = Template::query()->sole();
        [$hero, $body] = $template->sections->all();
        $fields = $template->fields->keyBy('slug');

        expect($template->sections->pluck('name')->all())->toBe(['Hero', 'Body'])
            ->and($hero->description)->toBe('The banner')
            ->and($hero->is_collapsed)->toBeTrue()
            ->and($body->is_collapsed)->toBeFalse()
            ->and($template->fields->pluck('slug')->all())->toBe(['intro', 'hero_title', 'hero_image', 'body'])
            ->and($template->fields->pluck('order')->all())->toBe([0, 1, 2, 3])
            ->and($fields['intro']->section_id)->toBeNull()
            ->and($fields['hero_title']->section_id)->toBe($hero->id)
            ->and($fields['body']->section_id)->toBe($body->id)
            ->and($fields['hero_title']->settings?->width)->toBe(TemplateFieldWidth::Half)
            ->and($fields['hero_title']->settings?->max_length)->toBe(80)
            // Full width is the default, so it isn't stored
            ->and($fields['hero_image']->settings)->toBeNull();
    });

    test('updating syncs the sections: renamed, reordered, removed and added, with fields moved between them', function () {
        $template = Template::factory()->create();
        $hero = TemplateSection::factory()->for($template)->create(['name' => 'Hero', 'order' => 0]);
        $body = TemplateSection::factory()->for($template)->create(['name' => 'Body', 'order' => 1]);
        $removed = TemplateSection::factory()->for($template)->create(['name' => 'Old', 'order' => 2]);
        $title = TemplateField::factory()->inSection($hero)->create(['slug' => 'title', 'order' => 0]);
        $text = TemplateField::factory()->inSection($body)->create(['slug' => 'text', 'order' => 1]);
        $kept = TemplateField::factory()->inSection($removed)->create(['slug' => 'kept', 'order' => 2]);

        $field = fn (TemplateField $field) => ['id' => $field->id, 'type' => 'text', 'name' => $field->name, 'slug' => $field->slug];

        $this->put(route('admin.cms.templates.update', $template), templateData([
            'name' => $template->name,
            'slug' => $template->slug,
            // The removed section's field moves above the sections
            'fields' => [$field($kept)],
            'sections' => [
                ['id' => $body->id, 'name' => 'Main', 'fields' => [$field($title)]],
                ['id' => $hero->id, 'name' => 'Hero', 'fields' => []],
                ['name' => 'Extra', 'fields' => [['type' => 'number', 'name' => 'Count', 'slug' => 'count']]],
            ],
        ]))->assertSessionHasNoErrors();

        $sections = $template->sections()->get();
        $fields = $template->fields()->get();

        expect($sections->pluck('name')->all())->toBe(['Main', 'Hero', 'Extra'])
            ->and($sections->take(2)->pluck('id')->all())->toBe([$body->id, $hero->id])
            ->and(TemplateSection::query()->find($removed->id))->toBeNull()
            ->and($fields->pluck('slug')->all())->toBe(['kept', 'title', 'count'])
            ->and($kept->refresh()->section_id)->toBeNull()
            ->and($title->refresh()->section_id)->toBe($body->id)
            ->and($fields->last()->section_id)->toBe($sections->last()->id)
            // Left out of the list, so deleted
            ->and(TemplateField::query()->find($text->id))->toBeNull();
    });

    test('the builder gets the sections, and each field\'s section and width', function () {
        $template = Template::factory()->create();
        $section = TemplateSection::factory()->for($template)->collapsed()->create(['name' => 'Hero']);
        TemplateField::factory()->inSection($section)->halfWidth()->create();

        $this->get(route('admin.cms.templates.edit', $template))
            ->assertInertia(fn (Assert $page) => $page
                ->where('template.data.sections.0.name', 'Hero')
                ->where('template.data.sections.0.is_collapsed', true)
                ->where('template.data.fields.0.section_id', $section->id)
                ->where('template.data.fields.0.settings.width', 'half')
            );
    });
});

describe('validation', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('slugs are unique per template type, and field slugs are unique per template', function () {
        Template::factory()->create(['type' => TemplateType::Page, 'slug' => 'default']);

        $this->post(route('admin.cms.templates.store'), templateData(['slug' => 'default']))
            ->assertSessionHasErrors(['slug' => __('cms.templates.validation.slug_taken')]);

        $this->post(route('admin.cms.templates.store'), templateData(['type' => 'layout', 'slug' => 'default']))
            ->assertSessionHasNoErrors();

        $this->post(route('admin.cms.templates.store'), templateData([
            'slug' => 'another',
            'fields' => [
                ['type' => 'text', 'name' => 'Title', 'slug' => ''],
                ['type' => 'text', 'name' => 'title', 'slug' => ''],
            ],
        ]))->assertSessionHasErrors(['fields.1.slug' => __('cms.templates.validation.duplicate_field_slug')]);
    });

    test('field slugs are unique across sections, sections need a name, and widths must be full or half', function () {
        $this->post(route('admin.cms.templates.store'), templateData([
            'fields' => [['type' => 'text', 'name' => 'Title', 'slug' => '']],
            'sections' => [
                ['name' => '', 'fields' => [
                    ['type' => 'text', 'name' => 'Title', 'slug' => '', 'settings' => ['width' => 'third']],
                ]],
            ],
        ]))->assertSessionHasErrors([
            'sections.0.name',
            'sections.0.fields.0.slug' => __('cms.templates.validation.duplicate_field_slug'),
            'sections.0.fields.0.settings.width',
        ]);

        expect(Template::query()->count())->toBe(0);
    });

    test('field and section errors name them as the builder does', function () {
        $this->post(route('admin.cms.templates.store'), templateData([
            'fields' => [['type' => 'text', 'name' => '', 'slug' => 'intro']],
            'sections' => [['name' => '', 'fields' => [['type' => 'repeater', 'name' => 'Rows', 'slug' => 'rows']]]],
        ]))->assertSessionHasErrors([
            'fields.0.name' => __('validation.required', ['attribute' => __('cms.templates.builder.name')]),
            'sections.0.name' => __('validation.required', ['attribute' => __('cms.templates.builder.section_name')]),
            'sections.0.fields.0.settings.template_id' => __('validation.required_if', [
                'attribute' => __('cms.templates.builder.template_id'),
                'other' => __('cms.templates.builder.type'),
                'value' => 'repeater',
            ]),
        ]);
    });

    test('field types must be allowed in the template type', function () {
        $this->post(route('admin.cms.templates.store'), templateData([
            'type' => 'repeater',
            'fields' => [
                ['type' => 'repeater', 'name' => 'Rows', 'slug' => 'rows', 'settings' => ['template_id' => 1]],
                ['type' => 'text', 'name' => 'Body', 'slug' => 'body'],
            ],
        ]))->assertSessionHasErrors([
            'fields.0.type' => __('cms.templates.validation.field_type_not_allowed', ['field' => 'Repeater', 'template' => 'Repeater']),
        ]);
    });

    test('an existing field of a type that\'s not offered (e.g. CRM switched off) can be kept', function () {
        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->ofType(TemplateFieldType::CrmForm)->create();
        tenant()->update(['modules' => [TenantModule::Cms]]);

        $this->put(route('admin.cms.templates.update', $template), templateData([
            'name' => $template->name,
            'slug' => $template->slug,
            'fields' => builderFields($template),
        ]))->assertSessionHasNoErrors();
    });

    test('component and repeater fields must use a template of the matching type, not their own', function () {
        $template = Template::factory()->create();
        $repeater = Template::factory()->ofType(TemplateType::Repeater)->create();
        TemplateField::factory()->for($template)->usingTemplate($repeater)->create();

        $fields = builderFields($template);
        $fields[0]['settings']['template_id'] = $template->id;

        $this->put(route('admin.cms.templates.update', $template), templateData([
            'name' => $template->name,
            'slug' => $template->slug,
            'fields' => $fields,
        ]))->assertSessionHasErrors(['fields.0.settings.template_id' => __('cms.templates.validation.nested_template', ['type' => 'Repeater'])]);
    });

    test('a number field\'s maximum can\'t be below its minimum', function () {
        $this->post(route('admin.cms.templates.store'), templateData([
            'fields' => [['type' => 'number', 'name' => 'Count', 'slug' => 'count', 'settings' => ['min' => 5, 'max' => 1]]],
        ]))->assertSessionHasErrors(['fields.0.settings.max' => __('cms.templates.validation.max_below_min')]);
    });

    test('a template used by another template can\'t change type or be deleted', function () {
        $component = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->usingTemplate($component)->create();

        $this->get(route('admin.cms.templates.edit', $component))
            ->assertInertia(fn (Assert $page) => $page->where('isInUse', true));

        $this->put(route('admin.cms.templates.update', $component), templateData([
            'type' => 'repeater',
            'name' => $component->name,
            'slug' => $component->slug,
        ]))->assertSessionHasErrors(['type' => __('cms.templates.validation.type_in_use')]);

        $this->delete(route('admin.cms.templates.destroy', $component))
            ->assertSessionHasErrors(['template' => __('cms.templates.validation.in_use')]);

        expect($component->refresh()->exists)->toBeTrue();
    });

    test('a template that isn\'t used can be deleted, with its fields', function () {
        $template = Template::factory()->has(TemplateField::factory(), 'fields')->create();

        $this->delete(route('admin.cms.templates.destroy', $template))
            ->assertSessionHas('success', __('cms.templates.flash.deleted'));

        expect(Template::query()->count())->toBe(0)
            ->and(TemplateField::query()->count())->toBe(0);
    });
});

test('CRM field types need the CRM module', function () {
    expect(TemplateFieldType::CrmForm->requiredModule())->toBe(TenantModule::Crm)
        ->and(TemplateFieldType::CrmOrganisationUnit->requiredModule())->toBe(TenantModule::Crm)
        ->and(TemplateFieldType::Text->requiredModule())->toBeNull();
});

describe('access', function () {
    test('users who can only view the site structure can view templates but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewStructure]));
        $template = Template::factory()->create();

        $this->get(route('admin.cms.templates.show', $template))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/cms/template/Show'));

        $this->get(route('admin.cms.templates.create'))->assertForbidden();
        $this->get(route('admin.cms.templates.edit', $template))->assertForbidden();
        $this->post(route('admin.cms.templates.store'), templateData())->assertForbidden();
        $this->put(route('admin.cms.templates.update', $template), templateData())->assertForbidden();
        $this->delete(route('admin.cms.templates.destroy', $template))->assertForbidden();
    });

    test('users with only content permissions cannot see templates', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent, CmsPermission::ManageContent]));

        $this->get(route('admin.cms.templates.index'))->assertForbidden();
    });

    test('templates are hidden when the tenant doesn\'t have the CMS module', function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->get(route('admin.cms.templates.index'))->assertNotFound();
    });
});
