<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Cms\Api\ContentPresenter;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A card component with a required title and an optional nested button, and a page template with an
 * optional card and a list of rows that each hold an optional card
 *
 * @return array{card: Template, page: Template, field: TemplateField}
 */
function savedCardTemplates(): array
{
    $button = Template::factory()->ofType(TemplateType::Component)->create(['name' => 'Button']);
    TemplateField::factory()->for($button)->create(['type' => TemplateFieldType::Text, 'name' => 'Label', 'slug' => 'label']);

    $card = Template::factory()->ofType(TemplateType::Component)->create(['name' => 'Card']);
    TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Text, 'name' => 'Title', 'slug' => 'title', 'is_required' => true, 'order' => 0]);
    TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Component, 'name' => 'Button', 'slug' => 'button', 'order' => 1, 'settings' => ['template_id' => $button->id]]);

    $row = Template::factory()->ofType(TemplateType::Repeater)->create(['name' => 'Card row']);
    TemplateField::factory()->for($row)->create(['type' => TemplateFieldType::Component, 'name' => 'Card', 'slug' => 'card', 'settings' => ['template_id' => $card->id]]);

    $page = Template::factory()->create(['name' => 'Cards page']);
    $field = TemplateField::factory()->for($page)->create(['type' => TemplateFieldType::Component, 'name' => 'Main card', 'slug' => 'main', 'order' => 0, 'settings' => ['template_id' => $card->id]]);
    TemplateField::factory()->for($page)->create(['type' => TemplateFieldType::Repeater, 'name' => 'Cards', 'slug' => 'cards', 'order' => 1, 'settings' => ['template_id' => $row->id]]);

    return ['card' => $card, 'page' => $page, 'field' => $field];
}

/**
 * @param  array<string, mixed>  $content
 * @return array<string, mixed>
 */
function pageUsingSavedData(Template $template, array $content, ?Page $page = null): array
{
    return [
        'template_id' => $template->id,
        'layout_id' => $page->layout_id ?? Layout::factory()->create()->id,
        'name' => $page->name ?? 'Pricing',
        'slug' => $page->slug ?? 'pricing',
        'is_enabled' => true,
        'content' => $content,
    ];
}

/**
 * @param  array<string, mixed>  $values
 */
function pageWithContent(Template $template, array $values): Page
{
    $page = Page::factory()->create(['template_id' => $template->id]);
    $page->syncContent($values);

    return $page;
}

describe('saving', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
        ['card' => $this->card, 'page' => $this->template, 'field' => $this->field] = savedCardTemplates();
        $this->saved = SavedComponent::factory()->create(['template_id' => $this->card->id])->fresh();
        $this->saved->syncContent(['title' => 'Standard']);
    });

    test('a page stores only the reference, and the row keys the editor sends are dropped', function () {
        $this->post(route('admin.cms.pages.store'), pageUsingSavedData($this->template, [
            'main' => [SavedComponent::REFERENCE_KEY => (string) $this->saved->id, 'title' => 'Ignored'],
            'cards' => [['_key' => 0, 'card' => $this->saved->reference()]],
        ]))->assertSessionHasNoErrors();

        expect(Page::query()->sole()->contentValues())->toBe([
            'main' => $this->saved->reference(),
            'cards' => [['card' => $this->saved->reference()]],
        ]);
    });

    test('whether a component is saved, custom or empty is decided for each row', function () {
        $this->post(route('admin.cms.pages.store'), pageUsingSavedData($this->template, [
            'cards' => [
                ['card' => $this->saved->reference()],
                ['card' => ['title' => 'Custom']],
                ['card' => ['title' => '', 'button' => ['label' => '']]],
            ],
        ]))->assertSessionHasNoErrors();

        expect(Page::query()->sole()->contentValues()['cards'])->toBe([
            ['card' => $this->saved->reference()],
            ['card' => ['title' => 'Custom', 'button' => null]],
            ['card' => null],
        ]);
    });

    test('a custom row\'s required fields still count beside a saved one', function () {
        $this->post(route('admin.cms.pages.store'), pageUsingSavedData($this->template, [
            'cards' => [
                ['card' => $this->saved->reference()],
                ['card' => ['title' => '', 'button' => ['label' => 'Go']]],
            ],
        ]))->assertSessionHasErrors('content.cards.1.card.title')
            ->assertSessionDoesntHaveErrors('content.cards.0.card.title');
    });

    test('a reference must be to a saved component of the field\'s template', function (Closure $reference) {
        $key = 'content.main.'.SavedComponent::REFERENCE_KEY;

        $this->post(route('admin.cms.pages.store'), pageUsingSavedData($this->template, ['main' => [SavedComponent::REFERENCE_KEY => $reference()]]))
            ->assertSessionHasErrors([$key => __('cms.saved_components.validation.reference_invalid')]);
    })->with([
        'another template' => fn () => fn () => SavedComponent::factory()->create()->id,
        'gone' => fn () => fn () => 999999,
        'not an id' => fn () => fn () => 'standard',
    ]);

    test('a reference with nothing chosen asks for a choice', function () {
        $this->post(route('admin.cms.pages.store'), pageUsingSavedData($this->template, ['main' => [SavedComponent::REFERENCE_KEY => null]]))
            ->assertSessionHasErrors(['content.main.'.SavedComponent::REFERENCE_KEY => __('validation.required', ['attribute' => 'Main card'])]);
    });

    test('a layout can use a saved component too', function () {
        $layoutTemplate = Template::factory()->ofType(TemplateType::Layout)->create();
        TemplateField::factory()->for($layoutTemplate)->create(['type' => TemplateFieldType::Component, 'slug' => 'promo', 'settings' => ['template_id' => $this->card->id]]);

        $this->post(route('admin.cms.layouts.store'), [
            'template_id' => $layoutTemplate->id,
            'name' => 'Main',
            'slug' => '',
            'content' => ['promo' => $this->saved->reference()],
        ])->assertSessionHasNoErrors();

        expect($this->saved->usedBy()->sole())->toBeInstanceOf(Layout::class);
    });

    test('a saved component\'s own content can\'t contain references', function () {
        $other = SavedComponent::factory()->create();

        $this->post(route('admin.cms.saved-components.store'), [
            'template_id' => $this->card->id,
            'name' => 'Chained',
            'slug' => '',
            'content' => ['title' => 'Chained', 'button' => [SavedComponent::REFERENCE_KEY => $other->id]],
        ])->assertSessionHasErrors(['content.button.'.SavedComponent::REFERENCE_KEY => __('cms.saved_components.validation.no_references')]);

        expect(SavedComponent::query()->where('name', 'Chained')->exists())->toBeFalse();
    });

    test('field slugs can\'t start with an underscore', function () {
        $this->post(route('admin.cms.templates.store'), [
            'name' => 'Reserved',
            'slug' => 'reserved',
            'type' => TemplateType::Page->value,
            'fields' => [['name' => 'Sneaky', 'slug' => '_saved_component_id', 'type' => TemplateFieldType::Text->value]],
        ])->assertSessionHasErrors(['fields.0.slug' => __('cms.templates.validation.field_slug_reserved')]);
    });

    test('the page editor gets the saved components with their content', function () {
        $this->get(route('admin.cms.pages.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('savedComponentOptions.canManage', true)
                ->where('savedComponentOptions.items.0.id', $this->saved->id)
                ->where('savedComponentOptions.items.0.template_id', $this->card->id)
                ->where('savedComponentOptions.items.0.content.title', 'Standard')
            );
    });

    test('save as reusable creates a saved component and returns it for the field to use', function () {
        $this->postJson(route('admin.cms.saved-components.reusable.store'), [
            'template_id' => $this->card->id,
            'name' => 'From a page',
            'slug' => '',
            'content' => ['title' => 'Mine', 'button' => ['label' => 'Go']],
        ])->assertCreated()->assertJsonPath('name', 'From a page')->assertJsonPath('content.button.label', 'Go');

        expect(SavedComponent::query()->where('slug', 'from-a-page')->sole()->contentValues())
            ->toBe(['title' => 'Mine', 'button' => ['label' => 'Go']]);
    });

    test('save as reusable reports problems with the values', function () {
        $this->postJson(route('admin.cms.saved-components.reusable.store'), [
            'template_id' => $this->card->id,
            'name' => 'From a page',
            'content' => ['title' => ''],
        ])->assertUnprocessable()->assertJsonValidationErrors('content.title');
    });
});

describe('the API', function () {
    beforeEach(function () {
        ['card' => $this->card, 'page' => $this->template, 'field' => $this->field] = savedCardTemplates();
        $this->saved = SavedComponent::factory()->withContent(['title' => 'Standard', 'button' => ['label' => 'Go']])->create(['template_id' => $this->card->id]);
    });

    test('a saved component comes back in exactly the shape of a custom one, at the top and in rows', function () {
        $presenter = app(ContentPresenter::class);
        $custom = ['title' => 'Standard', 'button' => ['label' => 'Go']];

        $presented = $presenter->present($this->template->load('fields'), [
            'main' => $this->saved->reference(),
            'cards' => [['card' => $this->saved->reference()], ['card' => $custom], ['card' => null]],
        ]);

        expect($presented['main'])->toBe($custom)
            ->and($presented['cards'])->toBe([['card' => $custom], ['card' => $custom], ['card' => null]]);
    });

    test('a reference that\'s gone or has a different template comes back as null', function () {
        $other = SavedComponent::factory()->create();

        $presented = app(ContentPresenter::class)->present($this->template->load('fields'), [
            'main' => $other->reference(),
            'cards' => [['card' => [SavedComponent::REFERENCE_KEY => 999999]]],
        ]);

        expect($presented['main'])->toBeNull()
            ->and($presented['cards'])->toBe([['card' => null]]);
    });

    test('many cards don\'t mean a query for each', function () {
        $rows = array_fill(0, 2, ['card' => $this->saved->reference()]);
        $this->template->load('fields');

        $countQueries = function (array $rows) {
            $presenter = app(ContentPresenter::class);
            DB::connection('tenant')->enableQueryLog();
            DB::connection('tenant')->flushQueryLog();
            $presenter->present($this->template, ['cards' => $rows]);

            return count(DB::connection('tenant')->getQueryLog());
        };

        expect($countQueries(array_fill(0, 12, ['card' => $this->saved->reference()])))->toBe($countQueries($rows));
    });
});

describe('keeping it safe', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
        ['card' => $this->card, 'page' => $this->template, 'field' => $this->field] = savedCardTemplates();
        $this->saved = SavedComponent::factory()->withContent(['title' => 'Standard'])->create(['template_id' => $this->card->id]);
    });

    test('a reference inside a repeater row counts as in use, and stops counting once replaced', function () {
        $page = pageWithContent($this->template, ['cards' => [['card' => ['title' => 'Custom']], ['card' => $this->saved->reference()]]]);

        expect($this->saved->isInUse())->toBeTrue();

        $page->syncContent(['cards' => [['card' => ['title' => 'Custom']]]]);

        expect($this->saved->isInUse())->toBeFalse();
    });

    test('a saved component in use can\'t be deleted', function () {
        pageWithContent($this->template, ['main' => $this->saved->reference()]);

        $this->delete(route('admin.cms.saved-components.destroy', $this->saved))
            ->assertSessionHasErrors(['saved_component' => __('cms.saved_components.validation.in_use')]);

        expect(SavedComponent::query()->whereKey($this->saved->id)->exists())->toBeTrue();
    });

    test('a saved component in use can\'t change template, though its other details can change', function () {
        pageWithContent($this->template, ['main' => $this->saved->reference()]);
        $other = Template::factory()->ofType(TemplateType::Component)->create();
        $data = ['name' => 'Renamed', 'slug' => $this->saved->slug, 'content' => ['title' => 'Standard']];

        $this->put(route('admin.cms.saved-components.update', $this->saved), [...$data, 'template_id' => $other->id])
            ->assertSessionHasErrors(['template_id' => __('cms.saved_components.validation.template_in_use')]);

        $this->put(route('admin.cms.saved-components.update', $this->saved), [...$data, 'template_id' => $this->card->id])
            ->assertSessionHasNoErrors();

        expect($this->saved->refresh()->name)->toBe('Renamed');
    });

    test('deleting the only page using it frees it to be deleted', function () {
        $page = pageWithContent($this->template, ['main' => $this->saved->reference()]);

        $this->delete(route('admin.cms.pages.destroy', $page))->assertSessionHasNoErrors();

        $this->delete(route('admin.cms.saved-components.destroy', $this->saved))->assertSessionHasNoErrors();

        expect(SavedComponent::query()->whereKey($this->saved->id)->exists())->toBeFalse();
    });

    test('deleting the only template field using it frees it to be deleted', function () {
        pageWithContent($this->template, ['main' => $this->saved->reference()]);

        $this->field->delete();

        expect($this->saved->isInUse())->toBeFalse();
    });

    test('the edit screen lists the pages and layouts using it, each once, linked if the user can open them', function () {
        $page = pageWithContent($this->template, ['main' => $this->saved->reference(), 'cards' => [['card' => $this->saved->reference()]]]);
        $layoutTemplate = Template::factory()->ofType(TemplateType::Layout)->create();
        TemplateField::factory()->for($layoutTemplate)->create(['type' => TemplateFieldType::Component, 'slug' => 'promo', 'settings' => ['template_id' => $this->card->id]]);
        $layout = Layout::factory()->create(['template_id' => $layoutTemplate->id, 'name' => 'Zed layout']);
        $layout->syncContent(['promo' => $this->saved->reference()]);
        $page->update(['name' => 'A page']);

        $this->get(route('admin.cms.saved-components.edit', $this->saved))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->has('usage', 2)
                ->where('usage.0', ['type' => 'page', 'name' => 'A page', 'url' => route('admin.cms.pages.edit', $page)])
                ->where('usage.1', ['type' => 'layout', 'name' => 'Zed layout', 'url' => route('admin.cms.layouts.edit', $layout)])
            );

        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent, CmsPermission::ManageContent]));

        $this->get(route('admin.cms.saved-components.edit', $this->saved))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('usage.1.url', null));
    });

    test('someone who can\'t manage saved components can\'t save as reusable, and isn\'t offered it', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewStructure, CmsPermission::ManageStructure]));

        $this->get(route('admin.cms.layouts.create'))
            ->assertInertia(fn (Assert $page) => $page->where('savedComponentOptions.canManage', false));

        $this->postJson(route('admin.cms.saved-components.reusable.store'), [
            'template_id' => $this->card->id,
            'name' => 'Nope',
            'content' => ['title' => 'Nope'],
        ])->assertForbidden();
    });
});
