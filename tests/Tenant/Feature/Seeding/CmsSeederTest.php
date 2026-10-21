<?php

use App\Enums\Cms\PublishStatus;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Models\Cms\Layout;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\PageView;
use App\Models\Cms\Redirect;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\Url;
use App\Services\Authorisation\Enums\CmsPermission;
use Database\Seeders\CmsSeeder;
use Database\Seeders\CrmSeeder;

beforeEach(function () {
    tenant()->update(['modules' => TenantModule::cases()]);
    $this->seed(CrmSeeder::class);
    $this->seed(CmsSeeder::class);
});

test('the CMS seeder makes templates of every type using every field type', function () {
    expect(Template::query()->pluck('type')->unique()->sort()->values()->all())
        ->toEqualCanonicalizing(TemplateType::cases())
        ->and(TemplateField::query()->pluck('type')->unique()->values()->all())
        ->toEqualCanonicalizing(TemplateFieldType::cases());
});

test('the CMS seeder makes pages in every publish status, nested, with their content', function () {
    foreach (PublishStatus::cases() as $status) {
        expect(Url::query()->withStatus($status)->exists())->toBeTrue("No {$status->value} page");
    }

    $home = Page::query()->where('slug', '')->sole();
    $team = Page::query()->where('slug', 'team')->sole();

    expect($home->url->url_full)->toBe('/')
        ->and($team->url->url_full)->toBe('/about/team')
        ->and($home->contentValues())
        ->toHaveKeys(['hero_title', 'body', 'features', 'call_to_action'])
        ->and($home->contentValues()['features'])->toHaveCount(3)
        ->and($home->contentValues()['body']['html'])->toContain('<p>')
        ->and(Page::query()->where('slug', 'contact')->sole()->contentValues()['contact_form'])->not->toBeNull()
        ->and($home->metadata->title)->toContain('Home');
});

test('the CMS seeder fills layouts and nested menus that link to the seeded pages', function () {
    $main = Menu::query()->where('slug', 'main')->sole();
    $about = $main->menu_items->toCollection()->firstWhere('label', 'About');

    expect(Layout::count())->toBe(2)
        ->and(Layout::query()->where('slug', 'default')->sole()->contentValues()['main_menu'])->toBe($main->id)
        ->and($about->children)->toHaveCount(2)
        ->and($main->pageIds())->toContain(Page::query()->where('slug', 'team')->value('id'))
        ->and(Menu::query()->where('slug', 'footer')->sole()->menu_items->toCollection()->pluck('target')->filter()->values()->all())->toBe(['_blank']);
});

test('the CMS seeder makes redirects and a few weeks of page views for the published pages', function () {
    expect(Redirect::query()->where('is_permanent', true)->exists())->toBeTrue()
        ->and(Redirect::query()->where('is_permanent', false)->exists())->toBeTrue()
        ->and(PageView::count())->toBeGreaterThan(100)
        ->and(PageView::query()->min('viewed_at'))->toBeGreaterThanOrEqual(now()->subDays(28)->startOfDay()->toDateTimeString())
        ->and(PageView::query()->whereNotNull('source')->exists())->toBeTrue()
        ->and(PageView::query()->where('url', '/coming-soon')->exists())->toBeFalse();
});

test('the CMS admin pages load with the seeded data, including scheduled and expired dates', function () {
    $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent, CmsPermission::ViewStructure]));

    $this->get(route('admin.cms.pages.index'))->assertOk();
    $this->get(route('admin.cms.redirects.index'))->assertOk();
});

test('running the CMS seeder again creates nothing new', function () {
    $counts = fn () => [Template::count(), TemplateField::count(), Layout::count(), Menu::count(), Page::count(), Url::count(), Redirect::count(), PageView::count()];
    $first = $counts();

    $this->seed(CmsSeeder::class);

    expect($counts())->toBe($first);
});
