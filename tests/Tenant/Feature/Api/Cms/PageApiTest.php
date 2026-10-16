<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Cms\Layout;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\Redirect;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Crm\Form;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Cms\Api\ContentVersion;
use App\Services\FileManager\FileManagerStorage;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['api.rate_limit_per_minute' => 10000]);

    // The test cache (Redis) outlives each test's database, so start every test with fresh cached pages
    app(ContentVersion::class)->bump();
});

function getPage(string $url, array $query = []): TestResponse
{
    return test()->getJson(route('api.v1.cms.pages.show', ['url' => $url, ...$query]));
}

/**
 * A published page at /{slug} using a template with the given fields, in a layout with a title
 *
 * @param  list<array<string, mixed>>  $fields
 * @param  array<string, mixed>  $content
 */
function apiPage(string $slug, array $fields = [], array $content = [], ?Page $parent = null): Page
{
    $template = Template::factory()->create();

    foreach ($fields as $order => $field) {
        TemplateField::factory()->for($template)->create([...$field, 'order' => $order]);
    }

    // Every page shares one layout
    $layout = Layout::query()->firstWhere('slug', 'main');

    if ($layout === null) {
        $layoutTemplate = Template::factory()->ofType(TemplateType::Layout)->create(['slug' => 'site']);
        TemplateField::factory()->for($layoutTemplate)->create(['type' => TemplateFieldType::Text, 'slug' => 'site_title']);
        $layout = Layout::factory()->create(['template_id' => $layoutTemplate->id, 'slug' => 'main']);
        $layout->syncContent(['site_title' => 'Acme']);
    }

    $page = Page::factory()->create([
        'template_id' => $template->id,
        'layout_id' => $layout->id,
        'parent_id' => $parent?->id,
        'slug' => $slug,
        'name' => ucfirst($slug),
    ]);
    $page->syncContent($content);
    $page->metadata()->create(['title' => 'About Acme']);

    return $page;
}

describe('pages', function () {
    test('a published page comes back with its metadata, layout and content', function () {
        apiPage('about', [['type' => TemplateFieldType::Text, 'slug' => 'heading'], ['type' => TemplateFieldType::Number, 'slug' => 'count']], ['heading' => 'About us', 'count' => 3]);

        getPage('/about/')
            ->assertOk()
            ->assertJsonPath('data.type', 'page')
            ->assertJsonPath('data.page.url', '/about')
            ->assertJsonPath('data.metadata.title', 'About Acme')
            ->assertJsonPath('data.layout.slug', 'main')
            ->assertJsonPath('data.layout.content.site_title', 'Acme')
            ->assertJsonPath('data.content.heading', 'About us')
            ->assertJsonPath('data.content.count', 3);
    });

    test('pages that are drafts, scheduled or expired aren\'t found', function () {
        Carbon::setTestNow('2026-07-01 12:00:00');
        apiPage('draft')->url->update(['is_enabled' => false]);
        apiPage('soon')->url->update(['published_at' => now()->addDay()]);
        apiPage('gone')->url->update(['expired_at' => now()->subDay()]);

        foreach (['/draft', '/soon', '/gone', '/missing'] as $url) {
            getPage($url)->assertNotFound()->assertJsonPath('message', __('cms.api.not_found', ['url' => $url]));
        }
    });

    test('a scheduled page goes live at its publish time, even if it was cached before', function () {
        Carbon::setTestNow('2026-07-01 12:00:00');
        apiPage('launch')->url->update(['published_at' => now()->addMinutes(5)]);

        getPage('/launch')->assertNotFound();

        Carbon::setTestNow('2026-07-01 12:06:00');

        getPage('/launch')->assertOk();
    });

    test('an admin change refreshes cached pages straight away', function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
        $page = apiPage('about', [['type' => TemplateFieldType::Text, 'slug' => 'heading']], ['heading' => 'Old']);

        getPage('/about')->assertJsonPath('data.content.heading', 'Old');

        $this->put(route('admin.cms.pages.update', $page), [
            'template_id' => $page->template_id,
            'layout_id' => $page->layout_id,
            'name' => $page->name,
            'slug' => 'about',
            'is_enabled' => true,
            'content' => ['heading' => 'New'],
        ])->assertSessionHasNoErrors();

        getPage('/about')->assertJsonPath('data.content.heading', 'New');
    });
});

describe('redirects', function () {
    test('an active redirect comes back instead of a page', function () {
        apiPage('about');
        Redirect::factory()->permanent()->create(['url_from' => '/about', 'url_to' => '/company']);

        getPage('/about')
            ->assertOk()
            ->assertJsonPath('data.type', 'redirect')
            ->assertJsonPath('data.redirect.to', '/company')
            ->assertJsonPath('data.redirect.status', 301);
    });

    test('disabled redirects are ignored', function () {
        apiPage('about');
        Redirect::factory()->disabled()->create(['url_from' => '/about', 'url_to' => '/company']);

        getPage('/about')->assertJsonPath('data.type', 'page');
    });
});

describe('content', function () {
    test('rich text comes back in both formats, or the one asked for', function () {
        $json = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]]]];
        apiPage('about', [['type' => TemplateFieldType::Wysiwyg, 'slug' => 'body']], ['body' => ['json' => $json, 'html' => '<p>Hi</p>']]);

        getPage('/about')->assertJsonPath('data.content.body.html', '<p>Hi</p>')->assertJsonPath('data.content.body.json.type', 'doc');
        getPage('/about', ['format' => 'html'])->assertJsonPath('data.content.body', '<p>Hi</p>');
        getPage('/about', ['format' => 'json'])->assertJsonPath('data.content.body.type', 'doc');
        getPage('/about', ['format' => 'xml'])->assertUnprocessable()->assertJsonValidationErrors('format');
    });

    test('images, components and repeaters are resolved', function () {
        $file = File::factory()->create(['name' => 'team.jpg', 'path' => 'team.jpg', 'mime_type' => 'image/jpeg', 'alt_text' => 'File alt']);

        $component = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->for($component)->create(['type' => TemplateFieldType::Text, 'slug' => 'title']);
        $repeater = Template::factory()->ofType(TemplateType::Repeater)->create();
        TemplateField::factory()->for($repeater)->create(['type' => TemplateFieldType::Image, 'slug' => 'picture']);

        apiPage('about', [
            ['type' => TemplateFieldType::Image, 'slug' => 'hero'],
            ['type' => TemplateFieldType::Component, 'slug' => 'cta', 'settings' => ['template_id' => $component->id]],
            ['type' => TemplateFieldType::Repeater, 'slug' => 'slides', 'settings' => ['template_id' => $repeater->id]],
        ], [
            'hero' => ['file_id' => $file->id, 'alt' => 'Our team'],
            'cta' => ['title' => 'Join us'],
            'slides' => [['picture' => ['file_id' => $file->id, 'alt' => null]], ['picture' => ['file_id' => 999, 'alt' => null]]],
        ]);

        getPage('/about')
            ->assertJsonPath('data.content.hero.url', app(FileManagerStorage::class)->url($file))
            ->assertJsonPath('data.content.hero.alt', 'Our team')
            ->assertJsonPath('data.content.cta.title', 'Join us')
            // No alt text of its own, so the file's is used; a missing file is null
            ->assertJsonPath('data.content.slides.0.picture.alt', 'File alt')
            ->assertJsonPath('data.content.slides.1.picture', null);
    });

    test('menus link to pages\' current URLs, leaving out unpublished pages', function () {
        $team = apiPage('team');
        $draft = apiPage('draft');
        $draft->url->update(['is_enabled' => false]);

        $menu = Menu::factory()->create(['slug' => 'main', 'menu_items' => [
            ['label' => 'Team', 'link_type' => 'page', 'page_id' => $team->id, 'children' => [
                ['label' => 'Docs', 'link_type' => 'url', 'url' => 'https://docs.example.com', 'target' => '_blank', 'children' => []],
            ]],
            ['label' => 'Draft', 'link_type' => 'page', 'page_id' => $draft->id, 'children' => []],
        ]]);

        apiPage('about', [['type' => TemplateFieldType::CmsMenu, 'slug' => 'nav']], ['nav' => $menu->id]);

        getPage('/about')
            ->assertJsonPath('data.content.nav.slug', 'main')
            ->assertJsonCount(1, 'data.content.nav.items')
            ->assertJsonPath('data.content.nav.items.0.url', '/team')
            ->assertJsonPath('data.content.nav.items.0.children.0.target', '_blank');
    });

    test('CRM forms and organisation units are resolved, without a cached spam token, and hidden without CRM', function () {
        $form = Form::factory()->create(['slug' => 'contact-us']);
        $unit = OrganisationUnit::factory()->create(['type' => OrganisationUnitType::Company, 'name' => 'Acme Ltd']);

        apiPage('contact', [
            ['type' => TemplateFieldType::CrmForm, 'slug' => 'form'],
            ['type' => TemplateFieldType::CrmOrganisationUnit, 'slug' => 'office'],
        ], ['form' => $form->id, 'office' => $unit->id]);

        getPage('/contact')
            ->assertJsonPath('data.content.form.slug', 'contact-us')
            ->assertJsonPath('data.content.form.api_url', route('api.v1.crm.forms.show', 'contact-us'))
            ->assertJsonMissingPath('data.content.form.spam_protection')
            ->assertJsonPath('data.content.office.name', 'Acme Ltd');

        tenant()->update(['modules' => [TenantModule::Cms]]);
        app(ContentVersion::class)->bump();

        getPage('/contact')
            ->assertJsonPath('data.content.form', null)
            ->assertJsonPath('data.content.office', null);
    });
});

test('the page API needs the CMS module', function () {
    tenant()->update(['modules' => [TenantModule::Crm]]);

    getPage('/about')->assertNotFound();
});

test('only successful admin changes refresh cached pages', function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    $version = app(ContentVersion::class);

    $before = $version->current();
    $this->get(route('admin.cms.redirects.index'))->assertOk();
    $this->post(route('admin.cms.redirects.store'), ['url_from' => ''])->assertSessionHasErrors();

    expect($version->current())->toBe($before);

    // The next request still sees the failed one's errors in the session, but it succeeds
    $this->post(route('admin.cms.redirects.store'), ['url_from' => '/old', 'url_to' => '/new', 'is_enabled' => true, 'is_permanent' => true])
        ->assertSessionHasNoErrors();

    expect($version->current())->not->toBe($before);
});
