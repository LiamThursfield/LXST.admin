<?php

use App\Enums\Cms\PublishStatus;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\TenantModule;
use App\Models\Cms\Content;
use App\Models\Cms\Layout;
use App\Models\Cms\Metadata;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\Url;
use App\Models\FileManager\File;
use App\Services\Authorisation\Enums\CmsPermission;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->template = Template::factory()->create(['name' => 'Standard']);
    TemplateField::factory()->for($this->template)->create(['type' => TemplateFieldType::Text, 'name' => 'Heading', 'slug' => 'heading', 'is_required' => true]);
    $this->layout = Layout::factory()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pageData(array $overrides = []): array
{
    return [
        'template_id' => test()->template->id,
        'layout_id' => test()->layout->id,
        'parent_id' => null,
        'name' => 'About',
        'slug' => 'about',
        'is_enabled' => true,
        'published_at' => null,
        'expired_at' => null,
        'content' => ['heading' => 'About us'],
        'metadata' => [],
        ...$overrides,
    ];
}

/**
 * The data the editor would send back for an existing page
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function existingPageData(Page $page, array $overrides = []): array
{
    return pageData([
        'template_id' => $page->template_id,
        'layout_id' => $page->layout_id,
        'parent_id' => $page->parent_id,
        'name' => $page->name,
        'slug' => $page->slug,
        ...$overrides,
    ]);
}

function makePage(string $slug, ?Page $parent = null): Page
{
    return Page::factory()
        ->state(['template_id' => test()->template->id, 'layout_id' => test()->layout->id, 'parent_id' => $parent?->id, 'slug' => $slug, 'name' => ucfirst($slug ?: 'home')])
        ->create();
}

describe('managing', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('pages are listed in URL order, indented under their parents, and can be filtered', function () {
        $about = makePage('about');
        makePage('team', $about);
        makePage('');
        $careers = makePage('careers');
        $careers->url->update(['is_enabled' => false]);

        $this->get(route('admin.cms.pages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/page/Index')
                ->where('pages.data.0.url', '/')
                ->where('pages.data.1.url', '/about')
                ->where('pages.data.2.url', '/about/team')
                ->where('pages.data.2.depth', 1)
                ->where('pages.data.3.url', '/careers')
                ->where('pages.data.3.status', 'draft')
            );

        $this->get(route('admin.cms.pages.index', ['filter' => ['status' => 'draft']]))
            ->assertInertia(fn (Assert $page) => $page->has('pages.data', 1)->where('pages.data.0.name', 'Careers'));

        $this->get(route('admin.cms.pages.index', ['filter' => ['search' => 'team']]))
            ->assertInertia(fn (Assert $page) => $page->has('pages.data', 1));
    });

    test('a page is created with its URL, publishing window, content and metadata', function () {
        Carbon::setTestNow('2026-07-01 12:00:00');
        $image = File::factory()->create(['mime_type' => 'image/png']);
        $about = makePage('about');

        $this->post(route('admin.cms.pages.store'), pageData([
            'name' => 'Our team',
            'slug' => 'Our-Team/',
            'parent_id' => $about->id,
            'published_at' => '2026-07-02T09:00',
            'metadata' => ['title' => 'Meet the team', 'og_image_file_id' => $image->id],
        ]))
            ->assertRedirect(route('admin.cms.pages.index'))
            ->assertSessionHas('success', __('cms.pages.flash.created'));

        $page = Page::query()->where('name', 'Our team')->sole();

        expect($page->slug)->toBe('our-team')
            ->and($page->url?->url_main)->toBe('/our-team')
            ->and($page->url?->url_full)->toBe('/about/our-team')
            ->and($page->url?->status())->toBe(PublishStatus::Scheduled)
            ->and($page->contentValues())->toBe(['heading' => 'About us'])
            ->and($page->metadata?->title)->toBe('Meet the team')
            ->and($page->metadata?->og_image_file_id)->toBe($image->id);
    });

    test('a top-level page with a blank slug is the home page', function () {
        $this->post(route('admin.cms.pages.store'), pageData(['name' => 'Home', 'slug' => '']))
            ->assertSessionHasNoErrors();

        expect(Page::query()->sole()->url?->url_full)->toBe('/');

        $this->post(route('admin.cms.pages.store'), pageData(['name' => 'Another home', 'slug' => '']))
            ->assertSessionHasErrors(['slug' => __('cms.pages.validation.home_taken')]);
    });

    test('changing a slug updates the URLs of every page below it', function () {
        $about = makePage('about');
        $team = makePage('team', $about);
        $jo = makePage('jo', $team);

        $this->put(route('admin.cms.pages.update', $about), existingPageData($about, ['slug' => 'company']))
            ->assertSessionHasNoErrors();

        expect($about->refresh()->url?->url_full)->toBe('/company')
            ->and($team->refresh()->url?->url_full)->toBe('/company/team')
            ->and($jo->refresh()->url?->url_full)->toBe('/company/team/jo');
    });

    test('moving a page updates its URL and its children\'s', function () {
        $about = makePage('about');
        $careers = makePage('careers');
        $team = makePage('team', $about);
        $jo = makePage('jo', $team);

        $this->put(route('admin.cms.pages.update', $team), existingPageData($team, ['parent_id' => $careers->id]))
            ->assertSessionHasNoErrors();

        expect($team->refresh()->url?->url_full)->toBe('/careers/team')
            ->and($jo->refresh()->url?->url_full)->toBe('/careers/team/jo');
    });

    test('the editor can check whether a URL is free', function () {
        $about = makePage('about');
        makePage('team', $about);

        $this->getJson(route('admin.cms.pages.url-check', ['slug' => 'team', 'parent_id' => $about->id]))
            ->assertExactJson(['url' => '/about/team', 'available' => false]);

        $this->getJson(route('admin.cms.pages.url-check', ['slug' => 'history', 'parent_id' => $about->id]))
            ->assertExactJson(['url' => '/about/history', 'available' => true]);
    });
});

describe('validation', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('slugs are unique among siblings, but the same slug can be used under different parents', function () {
        $about = makePage('about');
        $careers = makePage('careers');
        makePage('team', $about);

        $this->post(route('admin.cms.pages.store'), pageData(['slug' => 'team', 'parent_id' => $about->id]))
            ->assertSessionHasErrors(['slug' => __('cms.pages.validation.slug_taken')]);

        $this->post(route('admin.cms.pages.store'), pageData(['slug' => 'team', 'parent_id' => $careers->id]))
            ->assertSessionHasNoErrors();
    });

    test('only top-level pages can have a blank slug, and slugs must be URL-friendly', function () {
        $about = makePage('about');

        $this->post(route('admin.cms.pages.store'), pageData(['slug' => '', 'parent_id' => $about->id]))
            ->assertSessionHasErrors(['slug' => __('cms.pages.validation.slug_required')]);

        $this->post(route('admin.cms.pages.store'), pageData(['slug' => 'our team!']))
            ->assertSessionHasErrors(['slug' => __('cms.pages.validation.slug_format')]);
    });

    test('a page can\'t sit under itself, one of its own pages, or the home page', function () {
        $about = makePage('about');
        $team = makePage('team', $about);
        $home = makePage('');

        $this->put(route('admin.cms.pages.update', $about), existingPageData($about, ['parent_id' => $team->id]))
            ->assertSessionHasErrors(['parent_id' => __('cms.pages.validation.parent_loop')]);

        $this->put(route('admin.cms.pages.update', $about), existingPageData($about, ['parent_id' => $about->id]))
            ->assertSessionHasErrors(['parent_id' => __('cms.pages.validation.parent_loop')]);

        $this->post(route('admin.cms.pages.store'), pageData(['slug' => 'contact', 'parent_id' => $home->id]))
            ->assertSessionHasErrors(['parent_id' => __('cms.pages.validation.parent_is_home')]);
    });

    test('content is validated against the page template, which must be a page template', function () {
        $this->post(route('admin.cms.pages.store'), pageData(['content' => ['heading' => '']]))
            ->assertSessionHasErrors(['content.heading' => __('validation.required', ['attribute' => 'Heading'])]);

        $this->post(route('admin.cms.pages.store'), pageData(['template_id' => $this->layout->template_id]))
            ->assertSessionHasErrors(['template_id' => __('cms.pages.validation.template_type')]);
    });

    test('templates and layouts used by pages can\'t be deleted', function () {
        makePage('about');

        $this->delete(route('admin.cms.templates.destroy', $this->template))
            ->assertSessionHasErrors(['template' => __('cms.templates.validation.in_use')]);

        $this->delete(route('admin.cms.layouts.destroy', $this->layout))
            ->assertSessionHasErrors(['layout' => __('cms.layouts.validation.in_use')]);
    });
});

describe('deleting', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('a page without children is deleted with its URL, metadata and content', function () {
        $this->post(route('admin.cms.pages.store'), pageData(['metadata' => ['title' => 'About']]));
        $page = Page::query()->sole();

        $this->delete(route('admin.cms.pages.destroy', $page))
            ->assertSessionHas('success', trans_choice('cms.pages.flash.deleted', 1));

        expect(Page::query()->count())->toBe(0)
            ->and(Url::query()->count())->toBe(0)
            ->and(Metadata::query()->count())->toBe(0)
            ->and(Content::query()->count())->toBe(0);
    });

    test('a page with children isn\'t deleted, unless forced, which deletes the whole branch', function () {
        $about = makePage('about');
        $team = makePage('team', $about);
        makePage('jo', $team);
        makePage('careers');

        $this->delete(route('admin.cms.pages.destroy', $about))
            ->assertSessionHasErrors(['page' => __('cms.pages.validation.has_children', ['count' => 2])]);

        expect(Page::query()->count())->toBe(4);

        $this->delete(route('admin.cms.pages.destroy', $about), ['force' => true])
            ->assertSessionHas('success', trans_choice('cms.pages.flash.deleted', 3));

        expect(Page::query()->pluck('slug')->all())->toBe(['careers'])
            ->and(Url::query()->pluck('url_full')->all())->toBe(['/careers']);
    });
});

describe('access', function () {
    test('pages need the content permissions, not the structure ones', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewStructure, CmsPermission::ManageStructure]));

        $this->get(route('admin.cms.pages.index'))->assertForbidden();

        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent]));
        $page = makePage('about');

        $this->get(route('admin.cms.pages.show', $page))->assertOk();
        $this->get(route('admin.cms.pages.create'))->assertForbidden();
        $this->post(route('admin.cms.pages.store'), pageData())->assertForbidden();
        $this->put(route('admin.cms.pages.update', $page), existingPageData($page))->assertForbidden();
        $this->delete(route('admin.cms.pages.destroy', $page))->assertForbidden();
        $this->getJson(route('admin.cms.pages.url-check', ['slug' => 'x']))->assertForbidden();
    });

    test('pages are hidden when the tenant doesn\'t have the CMS module', function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->get(route('admin.cms.pages.index'))->assertNotFound();
    });
});
