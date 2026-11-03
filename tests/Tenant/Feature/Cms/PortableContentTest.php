<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\CrmPermission;
use App\Services\Cms\Portable\PortableContent;
use App\Services\Cms\Portable\PortableValues;
use Illuminate\Testing\TestResponse;

/**
 * @param  list<array<string, mixed>>  $items
 * @param  list<string>  $overwrite
 */
function checkPortable(array $items, array $overwrite = []): TestResponse
{
    return test()->postJson(route('admin.cms.import.preview'), [
        'json' => json_encode(app(PortableContent::class)->wrap($items), JSON_THROW_ON_ERROR),
        'overwrite' => $overwrite,
    ]);
}

/**
 * @param  list<array<string, mixed>>  $items
 * @param  list<string>  $overwrite
 */
function confirmPortable(array $items, array $overwrite = []): TestResponse
{
    return test()->post(route('admin.cms.import.store'), [
        'json' => json_encode(app(PortableContent::class)->wrap($items), JSON_THROW_ON_ERROR),
        'overwrite' => $overwrite,
    ]);
}

/**
 * A component template with a required title, and optional image, rich text and link fields
 */
function portableCardTemplate(): Template
{
    $template = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'card']);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Text, 'name' => 'Title', 'slug' => 'title', 'is_required' => true, 'order' => 0]);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Image, 'name' => 'Photo', 'slug' => 'photo', 'order' => 1]);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Wysiwyg, 'name' => 'Body', 'slug' => 'body', 'order' => 2]);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Link, 'name' => 'Destination', 'slug' => 'destination', 'order' => 3]);

    return $template;
}

/**
 * @param  array<string, mixed>  $content
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function portableSaved(array $content, array $overrides = []): array
{
    return ['kind' => 'saved_component', 'slug' => 'standard', 'name' => 'Standard', 'template' => ['type' => 'component', 'slug' => 'card'], 'content' => $content, ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function portableForm(array $overrides = []): array
{
    return [
        'kind' => 'form',
        'slug' => 'enquiry',
        'name' => 'Enquiry',
        'marketing_email' => true,
        'marketing_sms' => false,
        'marketing_telephone' => false,
        'submit_button_text' => 'Send',
        'redirect_url' => null,
        'success_message' => 'Thanks',
        'fields' => [['slug' => 'message', 'type' => 'textarea', 'name' => 'Message', 'is_required' => true, 'settings' => ['rows' => 3]]],
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function richTextDocument(string $text): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
}

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions([...CmsPermission::cases(), ...CrmPermission::cases()]));
});

describe('saved components', function () {
    test('export with their template first, and references rather than ids', function () {
        $template = portableCardTemplate();
        $file = File::factory()->create(['path' => 'images/hero.jpg', 'mime_type' => 'image/jpeg']);
        $target = Page::factory()->create(['slug' => 'contact']);
        $saved = SavedComponent::factory()->create(['slug' => 'standard', 'template_id' => $template->id]);
        $saved->syncContent([
            'title' => 'Standard',
            'photo' => ['file_id' => $file->id, 'alt' => 'A photo'],
            'body' => ['json' => richTextDocument('Hello'), 'html' => '<p>Hello</p>'],
            'destination' => ['link_type' => 'page', 'page_id' => $target->id, 'url' => null, 'target' => '_blank'],
        ]);

        $response = $this->getJson(route('admin.cms.saved-components.export', ['ids' => [$saved->id]]))->assertOk();
        $item = $response->json('items.1');

        expect($response->json('items.0.kind'))->toBe('template')
            ->and($response->json('items.0.slug'))->toBe('card')
            ->and($item['kind'])->toBe('saved_component')
            ->and($item['template'])->toBe(['type' => 'component', 'slug' => 'card'])
            ->and($item['content']['photo'])->toBe(['file' => 'images/hero.jpg', 'alt' => 'A photo'])
            ->and($item['content']['body'])->toEqual(['json' => richTextDocument('Hello')])
            ->and($item['content']['destination'])->toBe(['link_type' => 'page', 'page' => $target->url->url_full, 'target' => '_blank']);
    });

    test('a round trip gives the same saved component, pointing at this site\'s records', function () {
        $template = portableCardTemplate();
        $file = File::factory()->create(['path' => 'images/hero.jpg', 'mime_type' => 'image/jpeg']);
        $saved = SavedComponent::factory()->create(['slug' => 'standard', 'name' => 'Standard', 'template_id' => $template->id]);
        $saved->syncContent(['title' => 'Standard', 'photo' => ['file_id' => $file->id, 'alt' => null]]);

        $items = $this->getJson(route('admin.cms.saved-components.export', ['ids' => [$saved->id]]))->json('items');

        checkPortable($items)
            ->assertJsonPath('items.0.action', 'unchanged')
            ->assertJsonPath('items.1.action', 'unchanged');

        $saved->content()->delete();
        $saved->delete();

        confirmPortable($items)->assertSessionHasNoErrors();

        expect(SavedComponent::query()->where('slug', 'standard')->sole()->contentValues()['photo']['file_id'])->toBe($file->id);
    });

    test('a template in the same file is imported first', function () {
        confirmPortable([
            portableSaved(['title' => 'Standard']),
            ['kind' => 'template', 'type' => 'component', 'slug' => 'card', 'name' => 'Card', 'fields' => [['slug' => 'title', 'type' => 'text', 'name' => 'Title']], 'sections' => []],
        ])->assertSessionHasNoErrors();

        expect(SavedComponent::query()->where('slug', 'standard')->sole()->contentValues())->toBe(['title' => 'Standard']);
    });

    test('rich text HTML is rebuilt from its JSON, never trusted from the paste', function () {
        portableCardTemplate();

        confirmPortable([portableSaved(['title' => 'Standard', 'body' => ['json' => richTextDocument('Safe'), 'html' => '<script>alert(1)</script>']])])
            ->assertSessionHasNoErrors();

        $html = SavedComponent::query()->sole()->contentValues()['body']['html'];

        expect($html)->toContain('Safe')->not->toContain('script');
    });

    test('unsafe rich text and links are refused', function () {
        portableCardTemplate();

        checkPortable([portableSaved(['title' => 'A', 'destination' => ['link_type' => 'url', 'url' => 'javascript:alert(1)']])])
            ->assertJsonPath('items.0.errors.0', __('cms.menus.validation.url'));
    });

    test('a saved component can\'t contain references to another', function () {
        $template = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'card']);
        $button = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'button']);
        TemplateField::factory()->for($button)->create(['type' => TemplateFieldType::Text, 'slug' => 'label']);
        TemplateField::factory()->for($template)->usingTemplate($button)->create(['slug' => 'button', 'name' => 'Button']);
        SavedComponent::factory()->create(['slug' => 'other', 'template_id' => $button->id]);

        checkPortable([portableSaved(['button' => ['saved_component' => 'other']])])
            ->assertJsonPath('items.0.errors.0', __('cms.saved_components.validation.no_references'));
    });

    test('a missing optional file is left empty with a warning', function () {
        portableCardTemplate();

        $items = [portableSaved(['title' => 'Standard', 'photo' => ['file' => 'images/gone.jpg', 'alt' => null]])];

        checkPortable($items)
            ->assertJsonPath('items.0.errors', [])
            ->assertJsonPath('items.0.warnings.0', __('cms.import.missing.optional', [
                'what' => __('cms.import.missing.kinds.file', ['reference' => 'images/gone.jpg']),
                'field' => 'Photo',
            ]));

        confirmPortable($items)->assertSessionHasNoErrors();

        expect(SavedComponent::query()->sole()->contentValues()['photo'])->toBeNull();
    });

    test('a missing required file stops that item, so it can be uploaded first', function () {
        $template = portableCardTemplate();
        TemplateField::query()->where('template_id', $template->id)->where('slug', 'photo')->update(['is_required' => true]);

        $items = [portableSaved(['title' => 'Standard', 'photo' => ['file' => 'images/gone.jpg', 'alt' => null]])];

        checkPortable($items)->assertJsonPath('items.0.errors.0', __('cms.import.missing.required', [
            'what' => __('cms.import.missing.kinds.file', ['reference' => 'images/gone.jpg']),
            'field' => 'Photo',
        ]));

        confirmPortable($items)->assertSessionHasErrors('json');

        expect(SavedComponent::query()->count())->toBe(0);
    });

    test('a link to a page that isn\'t here is left empty with a warning', function () {
        portableCardTemplate();

        checkPortable([portableSaved(['title' => 'A', 'destination' => ['link_type' => 'page', 'page' => '/gone']])])
            ->assertJsonPath('items.0.errors', [])
            ->assertJsonPath('items.0.warnings.0', __('cms.import.missing.optional', [
                'what' => __('cms.import.missing.kinds.page', ['reference' => '/gone']),
                'field' => 'Destination',
            ]));
    });

    test('ids in the paste go no further', function () {
        portableCardTemplate();
        $file = File::factory()->create(['mime_type' => 'image/jpeg']);
        $page = Page::factory()->create();

        confirmPortable([portableSaved(['title' => 'A', 'photo' => ['file_id' => $file->id], 'destination' => ['link_type' => 'page', 'page_id' => $page->id]])])
            ->assertSessionHasNoErrors();

        $values = SavedComponent::query()->sole()->contentValues();

        expect($values['photo'])->toBeNull()->and($values['destination'])->toBeNull();
    });

    test('a template that\'s in neither the file nor the target stops that item', function () {
        checkPortable([portableSaved(['title' => 'A'])])
            ->assertJsonPath('items.0.errors.0', __('cms.import.validation.missing_saved_component_template', ['template' => 'component/card']));
    });

    test('only component templates can be used', function () {
        Template::factory()->create(['slug' => 'card']);

        checkPortable([portableSaved(['title' => 'A'], ['template' => ['type' => 'page', 'slug' => 'card']])])
            ->assertJsonPath('items.0.errors.0', __('cms.saved_components.validation.template_type'));
    });

    test('a clash skips by default, and overwriting warns it goes live where it\'s used', function () {
        $template = portableCardTemplate();
        $saved = SavedComponent::factory()->withContent(['title' => 'Old'])->create(['slug' => 'standard', 'name' => 'Standard', 'template_id' => $template->id]);
        $pageTemplate = Template::factory()->create();
        TemplateField::factory()->for($pageTemplate)->usingTemplate($template)->create(['slug' => 'card']);
        Page::factory()->create(['template_id' => $pageTemplate->id])->syncContent(['card' => $saved->reference()]);

        $items = [portableSaved(['title' => 'New'])];
        $warning = __('cms.import.warnings.saved_component_in_use', ['places' => trans_choice('cms.import.places.pages', 1)]);

        checkPortable($items)
            ->assertJsonPath('items.0.action', 'skip')
            ->assertJsonPath('items.0.warnings.0', $warning);

        confirmPortable($items)->assertSessionHasNoErrors();
        expect($saved->fresh()->contentValues()['title'])->toBe('Old');

        confirmPortable($items, ['saved_component/standard'])->assertSessionHasNoErrors();
        expect($saved->fresh()->contentValues()['title'])->toBe('New');
    });

    test('overwriting one in use can\'t change its template', function () {
        $template = portableCardTemplate();
        $saved = SavedComponent::factory()->create(['slug' => 'standard', 'template_id' => $template->id]);
        $pageTemplate = Template::factory()->create();
        TemplateField::factory()->for($pageTemplate)->usingTemplate($template)->create(['slug' => 'card']);
        Page::factory()->create(['template_id' => $pageTemplate->id])->syncContent(['card' => $saved->reference()]);
        Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'other']);

        checkPortable([portableSaved([], ['template' => ['type' => 'component', 'slug' => 'other']])], ['saved_component/standard'])
            ->assertJsonPath('items.0.errors.0', __('cms.saved_components.validation.template_in_use'));
    });

    test('importing them needs content permission, whatever else is in the file', function () {
        portableCardTemplate();
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewStructure, CmsPermission::ManageStructure]));

        checkPortable([
            portableSaved(['title' => 'A']),
            ['kind' => 'template', 'type' => 'page', 'slug' => 'landing', 'name' => 'Landing', 'fields' => [], 'sections' => []],
        ])
            ->assertJsonPath('items.0.errors.0', __('cms.import.validation.not_allowed'))
            ->assertJsonPath('items.1.errors', []);
    });

    test('an editor can import them when the template is already here', function () {
        portableCardTemplate();
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent, CmsPermission::ManageContent]));

        confirmPortable([portableSaved(['title' => 'A'])])->assertSessionHasNoErrors();

        expect(SavedComponent::query()->count())->toBe(1);

        $this->getJson(route('admin.cms.saved-components.export', ['ids' => [SavedComponent::query()->value('id')]]))->assertOk();
    });
});

describe('CRM forms', function () {
    test('export with their fields, and never their email recipients or submissions', function () {
        $form = Form::factory()->create(['slug' => 'enquiry', 'email_recipients' => ['staging@example.com']]);
        FormField::factory()->for($form)->create(['slug' => 'message']);

        $response = $this->getJson(route('admin.crm.forms.export', ['ids' => [$form->id]]))->assertOk();

        expect($response->getContent())->not->toContain('staging@example.com')
            ->and($response->getContent())->not->toContain('email_recipients')
            ->and($response->getContent())->not->toContain('submissions')
            ->and($response->json('items.0.kind'))->toBe('form')
            ->and($response->json('items.0.fields.0.slug'))->toBe('message');
    });

    test('deleted forms can\'t be exported', function () {
        $form = Form::factory()->create();
        $form->delete();

        $this->getJson(route('admin.crm.forms.export', ['ids' => [$form->id]]))->assertJsonValidationErrors('ids.0');
    });

    test('a round trip gives the same form', function () {
        confirmPortable([portableForm()])->assertSessionHasNoErrors();

        $form = Form::query()->where('slug', 'enquiry')->sole();
        $items = $this->getJson(route('admin.crm.forms.export', ['ids' => [$form->id]]))->json('items');

        expect($form->name)->toBe('Enquiry')
            ->and($form->marketing_email)->toBeTrue()
            ->and($form->fields()->sole()->settings->rows)->toBe(3);

        checkPortable($items)->assertJsonPath('items.0.action', 'unchanged');
    });

    test('recipients in a paste are ignored, and a new form has none', function () {
        confirmPortable([portableForm(['email_recipients' => ['someone@example.com']])])->assertSessionHasNoErrors();

        expect(Form::query()->sole()->email_recipients)->toBeNull();
    });

    test('overwriting keeps the target\'s own recipients', function () {
        $form = Form::factory()->create(['slug' => 'enquiry', 'name' => 'Old', 'email_recipients' => ['prod@example.com']]);

        confirmPortable([portableForm(['email_recipients' => ['staging@example.com']])], ['form/enquiry'])->assertSessionHasNoErrors();

        expect($form->refresh()->name)->toBe('Enquiry')
            ->and($form->email_recipients)->toBe(['prod@example.com']);
    });

    test('overwriting keeps fields by slug, and says what changes', function () {
        $form = Form::factory()->create(['slug' => 'enquiry']);
        $kept = FormField::factory()->for($form)->create(['slug' => 'message']);
        FormField::factory()->for($form)->create(['slug' => 'gone']);

        checkPortable([portableForm()], ['form/enquiry'])
            ->assertJsonPath('items.0.changes', [trans_choice('cms.import.changes.removed', 1)]);

        confirmPortable([portableForm()], ['form/enquiry'])->assertSessionHasNoErrors();

        expect($form->fields()->sole()->id)->toBe($kept->id);
    });

    test('the same rules as the builder apply', function () {
        checkPortable([portableForm(['fields' => [['slug' => 'honeypot', 'type' => 'not_a_type', 'name' => 'A']]])])
            ->assertJsonPath('items.0.errors.0', fn (string $error) => $error !== '');
    });

    test('a slug that a deleted form used is refused rather than brought back', function () {
        Form::factory()->create(['slug' => 'enquiry'])->delete();

        checkPortable([portableForm()])
            ->assertJsonPath('items.0.action', 'create')
            ->assertJsonPath('items.0.errors.0', fn (string $error) => str_contains($error, 'taken'));
    });

    test('the preview says so if the CRM module is off', function () {
        tenant()->update(['modules' => [TenantModule::Cms]]);

        checkPortable([portableForm()])->assertJsonPath('items.0.errors.0', __('cms.import.validation.crm_off'));
    });

    test('importing them needs permission to manage forms, and exporting to view them', function () {
        $form = Form::factory()->create();
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ManageStructure]));

        checkPortable([portableForm()])->assertJsonPath('items.0.errors.0', __('cms.import.validation.not_allowed'));
        $this->getJson(route('admin.crm.forms.export', ['ids' => [$form->id]]))->assertForbidden();
    });

    test('someone with no import permissions can\'t use the page', function () {
        $this->actingAs($this->createUserWithPermissions([CrmPermission::ViewForms, CmsPermission::ViewContent]));

        $this->get(route('admin.cms.import.show'))->assertForbidden();
    });
});

describe('content references', function () {
    test('every reference goes to its natural key and back', function () {
        $menu = Menu::factory()->create(['slug' => 'main']);
        $form = Form::factory()->create(['slug' => 'enquiry']);
        $unit = OrganisationUnit::factory()->create(['slug' => 'leeds']);
        $page = Page::factory()->create();
        $card = Template::factory()->ofType(TemplateType::Component)->create();
        $saved = SavedComponent::factory()->create(['slug' => 'standard', 'template_id' => $card->id]);

        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::CmsMenu, 'slug' => 'menu']);
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::CrmForm, 'slug' => 'form']);
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::CrmOrganisationUnit, 'slug' => 'unit']);
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Link, 'slug' => 'link']);
        TemplateField::factory()->for($template)->usingTemplate($card)->create(['slug' => 'card']);

        $values = app(PortableValues::class);
        $portable = $values->export($template->load('fields'), [
            'menu' => $menu->id,
            'form' => $form->id,
            'unit' => $unit->id,
            'link' => ['link_type' => 'page', 'page_id' => $page->id, 'url' => null, 'target' => null],
            'card' => $saved->reference(),
        ]);

        expect($portable['menu'])->toBe(['menu' => 'main'])
            ->and($portable['form'])->toBe(['form' => 'enquiry'])
            ->and($portable['unit'])->toBe(['organisation_unit' => 'leeds'])
            ->and($portable['link']['page'])->toBe($page->url->url_full)
            ->and($portable['card'])->toBe(['saved_component' => 'standard']);

        $missing = [];
        $back = $values->import($template, $portable, $missing);

        expect($missing)->toBe([])
            ->and($back['menu'])->toBe($menu->id)
            ->and($back['form'])->toBe($form->id)
            ->and($back['unit'])->toBe($unit->id)
            ->and($back['link']['page_id'])->toBe($page->id)
            ->and($back['card'])->toBe($saved->reference());
    });

    test('every field type is handled in both directions', function (TemplateFieldType $type) {
        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => $type, 'slug' => 'field']);

        $values = app(PortableValues::class);
        $missing = [];

        expect($values->export($template->load('fields'), ['field' => 1]))->toHaveKey('field')
            ->and($values->import($template, ['field' => 1], $missing))->toHaveKey('field');
    })->with(TemplateFieldType::cases());

    test('references that aren\'t here are reported, by what they are', function () {
        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::CrmOrganisationUnit, 'name' => 'Office', 'slug' => 'unit']);

        $missing = [];
        $values = app(PortableValues::class)->import($template->load('fields'), ['unit' => ['organisation_unit' => 'gone']], $missing);

        expect($values['unit'])->toBeNull()
            ->and($missing)->toBe([['field' => 'Office', 'required' => false, 'kind' => 'organisation_unit', 'reference' => 'gone']]);
    });
});
