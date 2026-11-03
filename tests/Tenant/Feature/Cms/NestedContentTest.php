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

/**
 * A component or repeater field on the parent, using the nested template
 *
 * @param  array<string, mixed>  $settings
 */
function nestIn(Template $parent, Template $nested, string $slug, bool $isRequired = false, array $settings = []): TemplateField
{
    return TemplateField::factory()->for($parent)->usingTemplate($nested)->create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'is_required' => $isRequired,
        'settings' => ['template_id' => $nested->id, ...$settings],
    ]);
}

/**
 * Saves the template with just this field
 *
 * @param  array<string, mixed>  $field
 */
function saveTemplateWithField(Template $template, array $field): TestResponse
{
    return test()->put(route('admin.cms.templates.update', $template), [
        'type' => $template->type->value,
        'name' => $template->name,
        'slug' => $template->slug,
        'fields' => [$field],
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

describe('nested content', function () {
    beforeEach(function () {
        $this->layout = Template::factory()->ofType(TemplateType::Layout)->create();
    });

    test('a list of cards that each have their own list is stored in order, without the editor\'s keys', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->for($card)->create(['name' => 'Name', 'slug' => 'name', 'is_required' => true]);
        nestIn($card, $this->repeater, 'prices');
        $row = Template::factory()->ofType(TemplateType::Repeater)->create();
        nestIn($row, $card, 'card', isRequired: true);
        nestIn($this->layout, $row, 'cards');

        saveNested($this->layout, ['cards' => [
            ['_key' => 4, 'card' => ['name' => 'Weddings', 'prices' => [['_key' => 1, 'title' => 'Evening'], ['_key' => 0, 'title' => 'All day']]]],
            ['_key' => 2, 'card' => ['name' => 'Parties']],
        ]])->assertSessionHasNoErrors();

        expect(Layout::query()->sole()->contentValues()['cards'])->toEqual([
            ['card' => ['name' => 'Weddings', 'prices' => [['title' => 'Evening'], ['title' => 'All day']]]],
            ['card' => ['name' => 'Parties', 'prices' => null]],
        ]);
    });

    test('fields in the deepest list are validated row by row and named after their parents', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create();
        nestIn($card, $this->repeater, 'prices');
        $row = Template::factory()->ofType(TemplateType::Repeater)->create();
        nestIn($row, $card, 'card');
        nestIn($this->layout, $row, 'cards');

        saveNested($this->layout, ['cards' => [
            ['card' => ['prices' => [['title' => 'Evening']]]],
            ['card' => ['prices' => [['title' => 'All day'], ['title' => '']]]],
        ]])->assertSessionHasErrors([
            'content.cards.1.card.prices.1.title' => __('validation.required', ['attribute' => 'Cards Card Prices Title']),
        ])->assertSessionDoesntHaveErrors(['content.cards.0.card.prices.0.title']);
    });

    test('rows that aren\'t a list are rejected', function () {
        nestIn($this->layout, $this->repeater, 'rows');

        saveNested($this->layout, ['rows' => ['first' => ['title' => 'First']]])
            ->assertSessionHasErrors('content.rows');
    });

    test('an optional component left empty in one row isn\'t affected by another row filling theirs in', function (array $rows) {
        $row = Template::factory()->ofType(TemplateType::Repeater)->create();
        TemplateField::factory()->for($row)->create(['name' => 'Name', 'slug' => 'name']);
        nestIn($row, $this->component, 'button');
        nestIn($this->layout, $row, 'rows');

        saveNested($this->layout, ['rows' => $rows])->assertSessionHasNoErrors();

        expect(collect(Layout::query()->sole()->contentValues()['rows'])->pluck('button.title')->all())
            ->toEqual(array_map(fn (array $row) => $row['button']['title'] ?: null, $rows));
    })->with([
        'the filled row first' => [[
            ['name' => 'A', 'button' => ['title' => 'Go', 'count' => null]],
            ['name' => 'B', 'button' => ['title' => '', 'count' => null]],
        ]],
        'the filled row last' => [[
            ['name' => 'A', 'button' => ['title' => '', 'count' => null]],
            ['name' => 'B', 'button' => ['title' => 'Go', 'count' => null]],
        ]],
    ]);

    test('an optional component partly filled in only fails in its own row', function () {
        $row = Template::factory()->ofType(TemplateType::Repeater)->create();
        nestIn($row, $this->component, 'button');
        nestIn($this->layout, $row, 'rows');

        saveNested($this->layout, ['rows' => [
            ['button' => ['title' => '', 'count' => null]],
            ['button' => ['title' => '', 'count' => '2']],
        ]])->assertSessionHasErrors([
            'content.rows.1.button.title' => __('validation.required', ['attribute' => 'Rows Button Title']),
        ])->assertSessionDoesntHaveErrors(['content.rows.0.button.title']);
    });

    test('a required component inside an optional one is only needed once the optional one is filled in', function () {
        $outer = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->for($outer)->create(['name' => 'Note', 'slug' => 'note']);
        nestIn($outer, $this->component, 'inner', isRequired: true);
        nestIn($this->layout, $outer, 'outer');

        saveNested($this->layout, ['outer' => ['note' => 'Filled in', 'inner' => ['title' => '', 'count' => null]]])
            ->assertSessionHasErrors(['content.outer.inner.title' => __('validation.required', ['attribute' => 'Outer Inner Title'])]);

        saveNested($this->layout, ['outer' => ['note' => '', 'inner' => ['title' => '', 'count' => null]]])
            ->assertSessionHasNoErrors();

        expect(Layout::query()->sole()->contentValues()['outer'])->toBeNull();
    });

    test('a list\'s fewest rows only apply once the optional component it\'s in is filled in', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->for($card)->create(['name' => 'Name', 'slug' => 'name']);
        nestIn($card, $this->repeater, 'prices', settings: ['min_items' => 1]);
        nestIn($this->layout, $card, 'card');

        saveNested($this->layout, ['card' => ['name' => 'Weddings', 'prices' => []]])
            ->assertSessionHasErrors('content.card.prices');

        saveNested($this->layout, ['card' => ['name' => '', 'prices' => []]])
            ->assertSessionHasNoErrors();
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

    test('components and repeater rows can hold components, but a repeater row can\'t directly hold a repeater', function () {
        foreach (['component', 'repeater'] as $index => $type) {
            $this->post(route('admin.cms.templates.store'), [
                'type' => $type,
                'name' => "Holder {$index}",
                'fields' => [['type' => 'component', 'name' => 'Button', 'slug' => 'button', 'settings' => ['template_id' => $this->component->id]]],
            ])->assertSessionHasNoErrors();
        }

        $this->post(route('admin.cms.templates.store'), [
            'type' => 'repeater',
            'name' => 'List of lists',
            'fields' => [['type' => 'repeater', 'name' => 'Rows', 'slug' => 'rows', 'settings' => ['template_id' => $this->repeater->id]]],
        ])->assertSessionHasErrors('fields.0.type');

        expect(TemplateType::Repeater->allowsFieldType(TemplateFieldType::CmsMenu))->toBeFalse()
            ->and(TemplateType::Component->allowsFieldType(TemplateFieldType::CmsMenu))->toBeFalse();
    });

    describe('how many lists can nest', function () {
        beforeEach(function () {
            // A card with a list of slides, and a list of those cards: list > card > list
            $this->card = Template::factory()->ofType(TemplateType::Component)->create();
            nestIn($this->card, $this->repeater, 'slides');
            $this->cardRow = Template::factory()->ofType(TemplateType::Repeater)->create();
            nestIn($this->cardRow, $this->card, 'card');
        });

        test('two levels are allowed', function () {
            $this->post(route('admin.cms.templates.store'), [
                'type' => 'page',
                'name' => 'Pricing',
                'fields' => [['type' => 'repeater', 'name' => 'Cards', 'slug' => 'cards', 'settings' => ['template_id' => $this->cardRow->id]]],
            ])->assertSessionHasNoErrors();
        });

        test('a third level can\'t be added from above', function () {
            $listOfCards = Template::factory()->ofType(TemplateType::Component)->create();
            nestIn($listOfCards, $this->cardRow, 'cards');
            $outerRow = Template::factory()->ofType(TemplateType::Repeater)->create();
            nestIn($outerRow, $listOfCards, 'cards');

            $this->post(route('admin.cms.templates.store'), [
                'type' => 'page',
                'name' => 'Too deep',
                'fields' => [['type' => 'repeater', 'name' => 'Outer', 'slug' => 'outer', 'settings' => ['template_id' => $outerRow->id]]],
            ])->assertSessionHasErrors(['fields.0.settings.template_id' => __('cms.templates.validation.too_many_repeater_levels', ['max' => 2])]);
        });

        test('a third level can\'t be added from below, through a template already in use', function () {
            $listInside = Template::factory()->ofType(TemplateType::Component)->create();
            nestIn($listInside, Template::factory()->ofType(TemplateType::Repeater)->create(), 'rows');

            $page = Template::factory()->create();
            nestIn($page, $this->cardRow, 'cards');

            saveTemplateWithField($this->repeater, ['type' => 'component', 'name' => 'More', 'slug' => 'more', 'settings' => ['template_id' => $listInside->id]])
                ->assertSessionHasErrors(['fields.0.settings.template_id' => __('cms.templates.validation.too_many_repeater_levels', ['max' => 2])]);
        });

        test('the same field is fine while the chain stays short enough, and components don\'t count', function () {
            $listInside = Template::factory()->ofType(TemplateType::Component)->create();
            nestIn($listInside, Template::factory()->ofType(TemplateType::Repeater)->create(), 'rows');

            // Nothing uses the repeater through a list of cards here, so this is only two levels
            $this->cardRow->fields()->delete();

            saveTemplateWithField($this->repeater, ['type' => 'component', 'name' => 'More', 'slug' => 'more', 'settings' => ['template_id' => $listInside->id]])
                ->assertSessionHasNoErrors();

            expect($this->repeater->repeaterLevelsAbove())->toBe(1)
                ->and($this->repeater->repeaterLevelsBelow())->toBe(1);
        });
    });

    test('the builder lists the templates component and repeater fields can use', function () {
        $this->get(route('admin.cms.templates.create'))
            ->assertInertia(fn ($page) => $page
                ->where('nestedTemplates', fn ($templates) => collect($templates)->pluck('name')->sort()->values()->all() === ['Call to action', 'Slide'])
            );
    });
});
