<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Content;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\TemplateSection;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Cms\Api\ContentVersion;
use App\Services\Cms\Portable\PortableContent;
use App\Services\Cms\Portable\TemplateExporter;
use Database\Seeders\CmsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  list<array<string, mixed>>  $fields
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function portableTemplate(string $slug, array $fields = [], string $type = 'page', array $overrides = []): array
{
    return ['kind' => 'template', 'type' => $type, 'slug' => $slug, 'name' => ucfirst($slug), 'description' => null, 'fields' => $fields, 'sections' => [], ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function portableField(string $slug, string $type = 'text', array $overrides = []): array
{
    return ['slug' => $slug, 'type' => $type, 'name' => ucfirst($slug), 'description' => null, 'is_required' => false, 'settings' => [], ...$overrides];
}

/**
 * @param  list<array<string, mixed>>  $items
 */
function portableJson(array $items): string
{
    return json_encode(app(PortableContent::class)->wrap($items), JSON_THROW_ON_ERROR);
}

/**
 * @param  list<array<string, mixed>>  $items
 * @param  list<string>  $overwrite
 */
function previewImport(array $items, array $overwrite = []): TestResponse
{
    return test()->postJson(route('admin.cms.import.preview'), ['json' => portableJson($items), 'overwrite' => $overwrite]);
}

/**
 * @param  list<array<string, mixed>>  $items
 * @param  list<string>  $overwrite
 */
function confirmImport(array $items, array $overwrite = []): TestResponse
{
    return test()->post(route('admin.cms.import.store'), ['json' => portableJson($items), 'overwrite' => $overwrite]);
}

/**
 * The items the export of these templates gives, without anything that could differ
 *
 * @return list<array<string, mixed>>
 */
function exportedItems(): array
{
    return app(TemplateExporter::class)->export(Template::query()->orderBy('slug')->get())['items'];
}

/**
 * A template with fields of every type, so a new type can't be forgotten
 *
 * @return list<array<string, mixed>>
 */
function everyFieldTypeItems(): array
{
    $component = portableTemplate('card', [portableField('title')], 'component');
    $row = portableTemplate('row', [portableField('title')], 'repeater');

    $fields = array_map(fn (TemplateFieldType $type) => portableField($type->value, $type->value, ['settings' => match ($type) {
        TemplateFieldType::Text => ['max_length' => 20, 'width' => 'half'],
        TemplateFieldType::Textarea => ['max_length' => 200, 'rows' => 4],
        TemplateFieldType::Number => ['min' => 1, 'max' => 5],
        TemplateFieldType::Select => ['options' => [['label' => 'One', 'value' => 'one'], ['label' => 'Two', 'value' => 'two']], 'default' => 'two'],
        TemplateFieldType::Component => ['template' => ['type' => 'component', 'slug' => 'card']],
        TemplateFieldType::Repeater => ['template' => ['type' => 'repeater', 'slug' => 'row'], 'min_items' => 1, 'max_items' => 3],
        TemplateFieldType::CrmOrganisationUnit => ['organisation_unit_type' => 'location'],
        TemplateFieldType::Wysiwyg, TemplateFieldType::Image, TemplateFieldType::Link, TemplateFieldType::CmsMenu, TemplateFieldType::CrmForm => [],
    }]), TemplateFieldType::cases());

    return [$component, $row, portableTemplate('everything', $fields)];
}

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
});

describe('exporting', function () {
    test('nested templates are included automatically and come first, with references rather than ids', function () {
        $inner = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'button']);
        $outer = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'card']);
        TemplateField::factory()->for($outer)->usingTemplate($inner)->create(['slug' => 'button']);
        $page = Template::factory()->create(['slug' => 'landing']);
        TemplateField::factory()->for($page)->usingTemplate($outer)->create(['slug' => 'card']);

        $response = $this->getJson(route('admin.cms.templates.export', ['ids' => [$page->id]]))
            ->assertOk()
            ->assertJsonPath('format', PortableContent::FORMAT)
            ->assertJsonPath('version', PortableContent::VERSION);

        $items = $response->json('items');

        expect(array_column($items, 'slug'))->toBe(['button', 'card', 'landing'])
            ->and($items[2]['fields'][0]['settings'])->toBe(['template' => ['type' => 'component', 'slug' => 'card']])
            ->and($response->getContent())->not->toContain('template_id')
            ->and($response->getContent())->not->toContain('"id"');
    });

    test('fields keep their sections and order', function () {
        $template = Template::factory()->create(['slug' => 'landing']);
        $section = TemplateSection::factory()->for($template)->create(['name' => 'Hero', 'is_collapsed' => true]);
        TemplateField::factory()->for($template)->create(['slug' => 'top', 'order' => 0]);
        TemplateField::factory()->for($template)->inSection($section)->create(['slug' => 'heading', 'order' => 1]);

        $item = $this->getJson(route('admin.cms.templates.export', ['ids' => [$template->id]]))->json('items.0');

        expect(array_column($item['fields'], 'slug'))->toBe(['top'])
            ->and($item['sections'][0]['name'])->toBe('Hero')
            ->and($item['sections'][0]['is_collapsed'])->toBeTrue()
            ->and(array_column($item['sections'][0]['fields'], 'slug'))->toBe(['heading']);
    });
});

describe('a round trip', function () {
    test('gives identical templates with different ids, for the seeded templates', function () {
        $this->seed(CmsSeeder::class);

        $exported = app(TemplateExporter::class)->export(Template::query()->with(['fields', 'sections'])->get());
        $before = exportedItems();
        $idsBefore = Template::query()->pluck('id', 'slug')->all();

        Content::query()->delete();
        Page::query()->whereNotNull('parent_id')->delete();
        Page::query()->delete();
        DB::table('cms_saved_components')->delete();
        DB::table('cms_layouts')->delete();
        Template::query()->delete();

        // Stands in for the other environment, where ids differ
        Template::factory()->create(['slug' => 'already-here']);

        confirmImport($exported['items'])->assertSessionHasNoErrors();

        Template::query()->where('slug', 'already-here')->delete();

        expect(exportedItems())->toEqual($before)
            ->and(Template::query()->pluck('id', 'slug')->all())->not->toBe($idsBefore);
    });

    test('keeps every type of field and its settings', function () {
        confirmImport(everyFieldTypeItems())->assertSessionHasNoErrors();

        $first = exportedItems();
        previewImport($first)
            ->assertOk()
            ->assertJsonPath('items.0.action', 'unchanged')
            ->assertJsonPath('items.1.action', 'unchanged')
            ->assertJsonPath('items.2.action', 'unchanged');

        $field = fn (string $slug) => TemplateField::query()->where('slug', $slug)->sole();

        expect($field('select')->settings->default)->toBe('two')
            ->and($field('repeater')->settings->max_items)->toBe(3)
            ->and($field('text')->settings->width->value)->toBe('half')
            ->and($field('component')->settings->template_id)->toBe(Template::query()->where('slug', 'card')->value('id'));
    });
});

describe('the dry run', function () {
    test('saves nothing, leaves cached pages alone, and says what would happen', function () {
        $version = app(ContentVersion::class);
        $before = $version->current();

        previewImport([portableTemplate('landing', [portableField('heading')])])
            ->assertOk()
            ->assertJsonPath('items.0.action', 'create')
            ->assertJsonPath('items.0.errors', []);

        expect(Template::query()->count())->toBe(0)
            ->and($version->current())->toBe($before);
    });

    test('an item that already exists is skipped by default, and can be overwritten', function () {
        $template = Template::factory()->create(['slug' => 'landing', 'name' => 'Old']);
        $key = 'template/page/landing';

        previewImport([portableTemplate('landing')])
            ->assertJsonPath('items.0.action', 'skip')
            ->assertJsonPath('items.0.can_overwrite', true);

        previewImport([portableTemplate('landing')], [$key])
            ->assertJsonPath('items.0.action', 'overwrite');

        expect($template->refresh()->name)->toBe('Old');
    });

    test('an item that\'s the same as what\'s here has nothing to do', function () {
        $template = Template::factory()->create(['slug' => 'landing', 'name' => 'Landing', 'description' => null]);

        previewImport([portableTemplate('landing')])
            ->assertJsonPath('items.0.action', 'unchanged')
            ->assertJsonPath('items.0.can_overwrite', false);

        expect($template->refresh()->name)->toBe('Landing');
    });

    test('overwriting warns what will be lost: removed fields with content, and on how many pages', function () {
        $template = Template::factory()->create(['slug' => 'landing']);
        $kept = TemplateField::factory()->for($template)->create(['slug' => 'kept', 'type' => TemplateFieldType::Text]);
        TemplateField::factory()->for($template)->create(['slug' => 'gone', 'type' => TemplateFieldType::Text]);
        TemplateField::factory()->for($template)->create(['slug' => 'gone_too', 'type' => TemplateFieldType::Text]);

        foreach (['one', 'two'] as $slug) {
            Page::factory()->create(['template_id' => $template->id, 'slug' => $slug])
                ->syncContent(['kept' => 'Stays', 'gone' => 'Lost', 'gone_too' => 'Lost too']);
        }

        $response = previewImport([portableTemplate('landing', [portableField('kept'), portableField('added')])]);

        $response->assertJsonPath('items.0.changes', [
            trans_choice('cms.import.changes.added', 1),
            trans_choice('cms.import.changes.removed', 2),
        ]);

        expect($response->json('items.0.warnings'))->toBe([
            trans_choice('cms.import.warnings.removes', 2, ['places' => trans_choice('cms.import.places.pages', 2)]),
        ])
            ->and($kept->template->fields()->count())->toBe(3);
    });

    test('removing a field from a nested template warns about what uses it', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'card']);
        TemplateField::factory()->for($card)->create(['slug' => 'title', 'type' => TemplateFieldType::Text]);
        TemplateField::factory()->for($card)->create(['slug' => 'gone', 'type' => TemplateFieldType::Text]);
        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->usingTemplate($card)->create(['slug' => 'card']);
        Page::factory()->create(['template_id' => $template->id])->syncContent(['card' => ['title' => 'A', 'gone' => 'B']]);

        expect(previewImport([portableTemplate('card', [portableField('title')], 'component')])->json('items.0.warnings'))->toBe([
            trans_choice('cms.import.warnings.removes', 1, ['places' => trans_choice('cms.import.places.pages', 1)]),
        ]);
    });

    test('removing a field nothing has content for needs no warning', function () {
        $template = Template::factory()->create(['slug' => 'landing']);
        TemplateField::factory()->for($template)->create(['slug' => 'gone']);

        previewImport([portableTemplate('landing')])->assertJsonPath('items.0.warnings', []);
    });
});

describe('importing', function () {
    test('new templates are created, with dependencies first whatever order the file has them in', function () {
        confirmImport([
            portableTemplate('landing', [portableField('card', 'component', ['settings' => ['template' => ['type' => 'component', 'slug' => 'card']]])]),
            portableTemplate('card', [portableField('title')], 'component'),
        ])->assertRedirect(route('admin.cms.import.show'))->assertSessionHasNoErrors();

        $card = Template::query()->where('slug', 'card')->sole();

        expect(Template::query()->where('slug', 'landing')->sole()->fields->sole()->settings->template_id)->toBe($card->id);
    });

    test('a clash is skipped unless it\'s chosen to be overwritten', function () {
        $template = Template::factory()->create(['slug' => 'landing', 'name' => 'Old']);

        confirmImport([portableTemplate('landing')])->assertSessionHasNoErrors();
        expect($template->refresh()->name)->toBe('Old');

        confirmImport([portableTemplate('landing')], ['template/page/landing'])->assertSessionHasNoErrors();
        expect($template->refresh()->name)->toBe('Landing');
    });

    test('overwriting keeps the content of fields that stay, and removes the content of those that don\'t', function () {
        $template = Template::factory()->create(['slug' => 'landing']);
        $kept = TemplateField::factory()->for($template)->create(['slug' => 'kept', 'type' => TemplateFieldType::Text]);
        TemplateField::factory()->for($template)->create(['slug' => 'gone', 'type' => TemplateFieldType::Text]);
        $page = Page::factory()->create(['template_id' => $template->id]);
        $page->syncContent(['kept' => 'Stays', 'gone' => 'Lost']);

        confirmImport([portableTemplate('landing', [portableField('kept', 'text', ['name' => 'Renamed'])])], ['template/page/landing'])
            ->assertSessionHasNoErrors();

        expect($kept->refresh()->name)->toBe('Renamed')
            ->and($page->fresh()->contentValues())->toBe(['kept' => 'Stays'])
            ->and($page->content()->count())->toBe(1);
    });

    test('overwriting keeps a section with the same name, and removes others', function () {
        $template = Template::factory()->create(['slug' => 'landing']);
        $kept = TemplateSection::factory()->for($template)->create(['name' => 'Hero']);
        TemplateSection::factory()->for($template)->create(['name' => 'Old']);

        confirmImport([portableTemplate('landing', [], 'page', ['sections' => [
            ['name' => 'Hero', 'description' => 'New words', 'is_collapsed' => true, 'fields' => [portableField('heading')]],
        ]])], ['template/page/landing'])->assertSessionHasNoErrors();

        $section = $template->sections()->sole();

        expect($section->id)->toBe($kept->id)
            ->and($section->description)->toBe('New words')
            ->and($section->fields()->sole()->slug)->toBe('heading');
    });

    test('a failure part-way changes nothing', function () {
        $existing = Template::factory()->create(['slug' => 'existing', 'name' => 'Old']);

        confirmImport([
            portableTemplate('first', [portableField('title')]),
            portableTemplate('existing'),
            portableTemplate('broken', [portableField('title', 'not_a_type')]),
        ], ['template/page/existing'])->assertSessionHasErrors('json');

        expect(Template::query()->where('slug', 'first')->exists())->toBeFalse()
            ->and($existing->refresh()->name)->toBe('Old');
    });

    test('an import refreshes cached pages', function () {
        $version = app(ContentVersion::class);
        $before = $version->current();

        confirmImport([portableTemplate('landing')])->assertSessionHasNoErrors();

        expect($version->current())->not->toBe($before);
    });
});

describe('what can\'t be imported', function () {
    test('anything that isn\'t our format is refused with a clear message', function (string $json, string $message) {
        $this->postJson(route('admin.cms.import.preview'), ['json' => $json])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['json' => __($message, ['number' => 1])]);
    })->with([
        'invalid JSON' => ['{not json', 'cms.import.validation.invalid_json'],
        'another format' => ['{"format":"other","version":1,"items":[]}', 'cms.import.validation.unknown_format'],
        'not an object' => ['"lxst-content"', 'cms.import.validation.unknown_format'],
        'no items' => ['{"format":"lxst-content","version":1,"items":[]}', 'cms.import.validation.no_items'],
        'unknown item' => ['{"format":"lxst-content","version":1,"items":[{"kind":"spaceship"}]}', 'cms.import.validation.unknown_item'],
    ]);

    test('a newer version is refused', function () {
        $json = json_encode(['format' => PortableContent::FORMAT, 'version' => PortableContent::VERSION + 1, 'items' => [portableTemplate('landing')]]);

        $this->postJson(route('admin.cms.import.preview'), ['json' => $json])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['json' => __('cms.import.validation.unsupported_version', ['max' => PortableContent::VERSION])]);
    });

    test('the same item twice is refused', function () {
        previewImport([portableTemplate('landing'), portableTemplate('landing')])
            ->assertJsonValidationErrors(['json' => __('cms.import.validation.duplicate_item', ['slug' => 'landing'])]);
    });

    test('a reference to a template in neither the file nor the target stops that item', function () {
        previewImport([portableTemplate('landing', [portableField('card', 'component', ['name' => 'Card', 'settings' => ['template' => ['type' => 'component', 'slug' => 'nowhere']]])])])
            ->assertOk()
            ->assertJsonPath('items.0.errors.0', __('cms.import.validation.missing_template', ['field' => 'Card', 'template' => 'component/nowhere']));
    });

    test('a reference to a template that\'s only on the target is fine', function () {
        Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'card']);

        previewImport([portableTemplate('landing', [portableField('card', 'component', ['settings' => ['template' => ['type' => 'component', 'slug' => 'card']]])])])
            ->assertJsonPath('items.0.errors', []);
    });

    test('templates that nest each other in the file are refused', function () {
        $response = previewImport([
            portableTemplate('one', [portableField('two', 'component', ['settings' => ['template' => ['type' => 'component', 'slug' => 'two']]])], 'component'),
            portableTemplate('two', [portableField('one', 'component', ['settings' => ['template' => ['type' => 'component', 'slug' => 'one']]])], 'component'),
        ]);

        expect(collect($response->json('items'))->pluck('errors')->flatten()->all())->toContain(__('cms.templates.validation.nests_itself'));

        confirmImport([
            portableTemplate('one', [portableField('one', 'component', ['settings' => ['template' => ['type' => 'component', 'slug' => 'one']]])], 'component'),
        ])->assertSessionHasErrors('json');

        expect(Template::query()->count())->toBe(0);
    });

    test('overwriting can\'t make a loop with what\'s on the target', function () {
        $inner = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'inner']);
        $outer = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'outer']);
        TemplateField::factory()->for($outer)->usingTemplate($inner)->create(['slug' => 'inner']);

        previewImport([portableTemplate('inner', [portableField('outer', 'component', ['settings' => ['template' => ['type' => 'component', 'slug' => 'outer']]])], 'component')], ['template/component/inner'])
            ->assertJsonPath('items.0.errors.0', __('cms.templates.validation.nests_itself'));
    });

    test('the same rules as the builder apply: field types, the nesting limit and field slugs', function () {
        previewImport([portableTemplate('card', [portableField('menu', 'cms_menu')], 'component')])
            ->assertJsonPath('items.0.errors.0', __('cms.templates.validation.field_type_not_allowed', ['field' => TemplateFieldType::CmsMenu->label(), 'template' => TemplateType::Component->label()]));

        previewImport([portableTemplate('landing', [portableField('_saved_component_id')])])
            ->assertJsonPath('items.0.errors.0', __('cms.templates.validation.field_slug_reserved'));

        $row = fn (string $slug, array $fields) => portableTemplate($slug, $fields, 'repeater');
        $card = fn (string $slug, array $fields) => portableTemplate($slug, $fields, 'component');
        $use = fn (string $type, string $slug) => portableField($slug, $type, ['settings' => ['template' => ['type' => $type, 'slug' => $slug]]]);

        $response = previewImport([
            $row('inner-row', [portableField('title')]),
            $card('inner-card', [$use('repeater', 'inner-row')]),
            $row('middle-row', [$use('component', 'inner-card')]),
            $card('outer-card', [$use('repeater', 'middle-row')]),
            $row('outer-row', [$use('component', 'outer-card')]),
            portableTemplate('landing', [$use('repeater', 'outer-row')]),
        ]);

        expect(collect($response->json('items'))->pluck('errors')->flatten()->all())
            ->toContain(__('cms.templates.validation.too_many_repeater_levels', ['max' => Template::MAX_REPEATER_LEVELS]));
    });

    test('ids in the paste go no further', function () {
        $other = Template::factory()->ofType(TemplateType::Component)->create();
        $target = Template::factory()->create(['slug' => 'landing']);
        $field = TemplateField::factory()->for(Template::factory())->create(['slug' => 'someone_elses', 'name' => 'Theirs']);

        // A raw template_id isn't a reference, and an id can't reach another template's field
        previewImport([portableTemplate('with-id', [portableField('card', 'component', ['settings' => ['template_id' => $other->id]])])])
            ->assertJsonPath('items.0.errors.0', fn (string $error) => str_contains($error, 'required') || str_contains($error, 'Template'));

        confirmImport([portableTemplate('landing', [portableField('heading', 'text', ['id' => $field->id])])], ['template/page/landing'])
            ->assertSessionHasNoErrors();

        expect($field->refresh()->name)->toBe('Theirs')
            ->and($target->fields()->sole()->slug)->toBe('heading');
    });
});

describe('access', function () {
    test('importing needs permission to manage the site\'s structure, and exporting to view it', function () {
        $template = Template::factory()->create();
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewStructure]));

        $this->getJson(route('admin.cms.templates.export', ['ids' => [$template->id]]))->assertOk();
        $this->get(route('admin.cms.import.show'))->assertForbidden();
        previewImport([portableTemplate('landing')])->assertForbidden();
        confirmImport([portableTemplate('landing')])->assertForbidden();

        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent, CmsPermission::ManageContent]));

        $this->getJson(route('admin.cms.templates.export', ['ids' => [$template->id]]))->assertForbidden();

        // They can use the page for what they manage, but not templates
        $this->get(route('admin.cms.import.show'))->assertOk();
        previewImport([portableTemplate('landing')])->assertJsonPath('items.0.errors.0', __('cms.import.validation.not_allowed'));
        confirmImport([portableTemplate('landing')])->assertSessionHasErrors('json');

        expect(Template::query()->where('slug', 'landing')->exists())->toBeFalse();
    });

    test('someone who manages structure can open the import page', function () {
        $this->get(route('admin.cms.import.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/cms/import/Index'));
    });
});
