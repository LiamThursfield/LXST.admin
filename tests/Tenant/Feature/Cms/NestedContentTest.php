<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Layout;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Authorisation\Enums\CmsPermission;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));

    // A call to action component with a required title and an optional count
    $this->component = Template::factory()->ofType(TemplateType::Component)->create(['name' => 'Call to action']);
    TemplateField::factory()->for($this->component)->create(['type' => TemplateFieldType::Text, 'name' => 'Title', 'slug' => 'title', 'is_required' => true, 'order' => 0]);
    TemplateField::factory()->for($this->component)->create(['type' => TemplateFieldType::Number, 'name' => 'Count', 'slug' => 'count', 'order' => 1]);

    // A slide repeater, each row with a required title
    $this->repeater = Template::factory()->ofType(TemplateType::Repeater)->create(['name' => 'Slide']);
    TemplateField::factory()->for($this->repeater)->create(['type' => TemplateFieldType::Text, 'name' => 'Title', 'slug' => 'title', 'is_required' => true, 'order' => 0]);

    $this->layoutTemplate = Template::factory()->ofType(TemplateType::Layout)->create();
    TemplateField::factory()->for($this->layoutTemplate)->create(['type' => TemplateFieldType::Component, 'name' => 'Call to action', 'slug' => 'cta', 'order' => 0, 'settings' => ['template_id' => $this->component->id]]);
    TemplateField::factory()->for($this->layoutTemplate)->create(['type' => TemplateFieldType::Repeater, 'name' => 'Slides', 'slug' => 'slides', 'order' => 1, 'settings' => ['template_id' => $this->repeater->id, 'min_items' => 1, 'max_items' => 3]]);
});

/**
 * @param  array<string, mixed>  $content
 */
function saveNested(Template $template, array $content): TestResponse
{
    return test()->post(route('admin.cms.layouts.store'), [
        'template_id' => $template->id,
        'name' => 'Main',
        'content' => $content,
    ]);
}

describe('content', function () {
    test('a component is stored as an object of its fields, and repeater rows in order without the editor\'s keys', function () {
        saveNested($this->layoutTemplate, [
            'cta' => ['title' => 'Join us', 'count' => '5', 'extra' => 'ignored'],
            'slides' => [['_key' => 7, 'title' => 'First'], ['_key' => 3, 'title' => 'Second']],
        ])->assertSessionHasNoErrors();

        expect(Layout::query()->sole()->contentValues())->toEqual([
            'cta' => ['title' => 'Join us', 'count' => 5],
            'slides' => [['title' => 'First'], ['title' => 'Second']],
        ]);
    });

    test('an optional component left empty isn\'t validated or saved, but one partly filled in is', function () {
        saveNested($this->layoutTemplate, [
            'cta' => ['title' => '', 'count' => null],
            'slides' => [['title' => 'First']],
        ])->assertSessionHasNoErrors();

        expect(Layout::query()->sole()->contentValues()['cta'])->toBeNull();

        saveNested($this->layoutTemplate, [
            'cta' => ['title' => '', 'count' => '2'],
            'slides' => [['title' => 'First']],
        ])->assertSessionHasErrors(['content.cta.title' => __('validation.required', ['attribute' => 'Call to action Title'])]);
    });

    test('each repeater row is validated, and the number of rows is limited', function () {
        saveNested($this->layoutTemplate, ['slides' => [['title' => 'First'], ['title' => '']]])
            ->assertSessionHasErrors(['content.slides.1.title' => __('validation.required', ['attribute' => 'Slides Title'])]);

        saveNested($this->layoutTemplate, ['slides' => []])
            ->assertSessionHasErrors('content.slides');

        saveNested($this->layoutTemplate, ['slides' => array_fill(0, 4, ['title' => 'Slide'])])
            ->assertSessionHasErrors(['content.slides' => __('validation.max.array', ['attribute' => 'Slides', 'max' => 3])]);
    });
});

describe('templates', function () {
    test('component and repeater fields can be added, using a template of the matching type', function () {
        $this->post(route('admin.cms.templates.store'), [
            'type' => 'page',
            'name' => 'Home',
            'fields' => [
                ['type' => 'component', 'name' => 'CTA', 'slug' => 'cta', 'settings' => ['template_id' => $this->repeater->id]],
                ['type' => 'repeater', 'name' => 'Slides', 'slug' => 'slides', 'settings' => ['template_id' => $this->repeater->id, 'min_items' => 3, 'max_items' => 2]],
                ['type' => 'repeater', 'name' => 'More', 'slug' => 'more'],
            ],
        ])->assertSessionHasErrors([
            'fields.0.settings.template_id' => __('cms.templates.validation.nested_template', ['type' => 'Component']),
            'fields.1.settings.max_items' => __('cms.templates.validation.max_below_min'),
            'fields.2.settings.template_id',
        ]);
    });

    test('a template can\'t use a template that already contains it', function () {
        // Build a loop the builder wouldn't allow, then check the rule still catches it
        TemplateField::factory()->for($this->repeater)->create([
            'type' => TemplateFieldType::Component,
            'slug' => 'loop',
            'settings' => ['template_id' => $this->component->id],
        ]);

        expect($this->repeater->nestsTemplate($this->component))->toBeTrue()
            ->and($this->component->nestsTemplate($this->repeater))->toBeFalse();

        $this->put(route('admin.cms.templates.update', $this->component), [
            'type' => 'component',
            'name' => $this->component->name,
            'slug' => $this->component->slug,
            'fields' => [['type' => 'repeater', 'name' => 'Rows', 'slug' => 'rows', 'settings' => ['template_id' => $this->repeater->id]]],
        ])->assertSessionHasErrors(['fields.0.settings.template_id' => __('cms.templates.validation.nests_itself')]);
    });

    test('the builder lists the templates component and repeater fields can use', function () {
        $this->get(route('admin.cms.templates.create'))
            ->assertInertia(fn ($page) => $page
                ->where('nestedTemplates', fn ($templates) => collect($templates)->pluck('name')->sort()->values()->all() === ['Call to action', 'Slide'])
            );
    });
});
