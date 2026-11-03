<?php

use App\Enums\Cms\PublishStatus;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Content;
use App\Models\Cms\Layout;
use App\Models\Cms\Menu;
use App\Models\Cms\Metadata;
use App\Models\Cms\Page;
use App\Models\Cms\PageView;
use App\Models\Cms\Redirect;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\Url;
use App\Models\FileManager\File;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\CrmPermission;
use App\Services\Cms\Portable\PortableContent;
use App\Services\Cms\Portable\SiteExporter;
use Database\Seeders\CmsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * @param  list<array<string, mixed>>  $items
 * @param  list<string>  $overwrite
 */
function checkSite(array $items, array $overwrite = []): TestResponse
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
function confirmSite(array $items, array $overwrite = []): TestResponse
{
    return test()->post(route('admin.cms.import.store'), [
        'json' => json_encode(app(PortableContent::class)->wrap($items), JSON_THROW_ON_ERROR),
        'overwrite' => $overwrite,
    ]);
}

/**
 * A page template with a heading, a menu field and a link
 */
function sitePageTemplate(): Template
{
    $template = Template::factory()->create(['slug' => 'standard']);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Text, 'name' => 'Heading', 'slug' => 'heading', 'order' => 0]);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::CmsMenu, 'name' => 'Side menu', 'slug' => 'side_menu', 'order' => 1]);
    TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Link, 'name' => 'Next', 'slug' => 'next', 'order' => 2]);

    return $template;
}

function siteLayout(string $slug = 'main'): Layout
{
    $template = Template::factory()->ofType(TemplateType::Layout)->create(['slug' => "layout-{$slug}"]);

    return Layout::factory()->create(['slug' => $slug, 'name' => ucfirst($slug), 'template_id' => $template->id]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function portablePage(string $url, array $overrides = []): array
{
    return [
        'kind' => 'page',
        'url' => $url,
        'name' => 'Imported',
        'template' => ['type' => 'page', 'slug' => 'standard'],
        'layout' => 'main',
        'metadata' => ['title' => 'A title'],
        'content' => ['heading' => 'Hello'],
        ...$overrides,
    ];
}

function pageAtUrl(string $url): ?Page
{
    return Page::query()->whereHas('url', fn ($query) => $query->where('url_full', $url))->first();
}

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions([...CmsPermission::cases(), ...CrmPermission::cases()]));
});

describe('exporting', function () {
    test('a page brings its parents, template, layout and saved components, and no publishing or views', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'card']);
        TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Text, 'slug' => 'title']);
        $saved = SavedComponent::factory()->withContent(['title' => 'Standard'])->create(['slug' => 'standard-card', 'template_id' => $card->id]);

        $template = sitePageTemplate();
        TemplateField::factory()->for($template)->usingTemplate($card)->create(['slug' => 'card', 'order' => 3]);
        $layout = siteLayout();

        $parent = Page::factory()->create(['slug' => 'about', 'template_id' => $template->id, 'layout_id' => $layout->id]);
        $parent->url->update(['published_at' => now()->subDay()]);
        $child = Page::factory()->create(['slug' => 'team', 'parent_id' => $parent->id, 'template_id' => $template->id, 'layout_id' => $layout->id]);
        $child->url->update(['url_full' => '/about/team']);
        $child->syncContent(['heading' => 'Team', 'card' => $saved->reference()]);
        PageView::factory()->create(['url' => '/about/team']);

        $response = $this->getJson(route('admin.cms.pages.export', ['ids' => [$child->id]]))->assertOk();
        $items = collect($response->json('items'));

        expect($items->pluck('kind')->unique()->values()->all())->toBe(['template', 'saved_component', 'layout', 'page'])
            ->and($items->where('kind', 'page')->pluck('url')->all())->toBe([$parent->url->url_full, '/about/team'])
            ->and($items->firstWhere('kind', 'saved_component')['slug'])->toBe('standard-card')
            ->and($items->firstWhere('url', '/about/team')['content']['card'])->toBe(['saved_component' => 'standard-card'])
            ->and($response->getContent())->not->toContain('published_at')
            ->and($response->getContent())->not->toContain('is_enabled')
            ->and($response->getContent())->not->toContain('viewed_at');
    });

    test('a menu\'s page links are stored as URLs', function () {
        $page = Page::factory()->create();
        $menu = Menu::factory()->create(['slug' => 'main', 'menu_items' => [
            ['label' => 'A page', 'link_type' => 'page', 'page_id' => $page->id],
            ['label' => 'Elsewhere', 'link_type' => 'url', 'url' => 'https://example.com', 'target' => '_blank'],
            ['label' => 'Gone', 'link_type' => 'page', 'page_id' => 999999],
        ]]);

        $items = $this->getJson(route('admin.cms.menus.export', ['ids' => [$menu->id]]))->json('items.0.items');

        expect($items)->toHaveCount(2)
            ->and($items[0]['page'])->toBe($page->url->url_full)
            ->and($items[0])->not->toHaveKey('page_id')
            ->and($items[1]['url'])->toBe('https://example.com');
    });

    test('redirects and layouts can be exported', function () {
        $redirect = Redirect::factory()->create(['url_from' => '/old', 'url_to' => '/new']);
        $layout = siteLayout();

        $this->getJson(route('admin.cms.redirects.export', ['ids' => [$redirect->id]]))
            ->assertJsonPath('items.0.url_from', '/old')
            ->assertJsonMissingPath('items.0.published_at');

        $this->getJson(route('admin.cms.layouts.export', ['ids' => [$layout->id]]))
            ->assertJsonPath('items.0.kind', 'template')
            ->assertJsonPath('items.1.kind', 'layout')
            ->assertJsonPath('items.1.slug', 'main');
    });
});

describe('importing pages', function () {
    beforeEach(function () {
        sitePageTemplate();
        siteLayout();
    });

    test('a new page arrives disabled, with its URL, metadata and content', function () {
        checkSite([portablePage('/imported')])
            ->assertJsonPath('items.0.action', 'create')
            ->assertJsonPath('items.0.changes.0', __('cms.import.changes.arrives_disabled'));

        expect(pageAtUrl('/imported'))->toBeNull();

        confirmSite([portablePage('/imported')])->assertSessionHasNoErrors();

        $page = pageAtUrl('/imported');

        expect($page->url->status())->toBe(PublishStatus::Draft)
            ->and($page->name)->toBe('Imported')
            ->and($page->slug)->toBe('imported')
            ->and($page->metadata->title)->toBe('A title')
            ->and($page->contentValues()['heading'])->toBe('Hello');
    });

    test('publish state in the paste goes no further', function () {
        confirmSite([portablePage('/imported', ['is_enabled' => true, 'published_at' => '2020-01-01T00:00'])])->assertSessionHasNoErrors();

        expect(pageAtUrl('/imported')->url->is_enabled)->toBeFalse();
    });

    test('an overwritten page stays published, with its dates, and warns it goes live', function () {
        $page = Page::factory()->create(['slug' => 'live', 'template_id' => Template::query()->where('slug', 'standard')->value('id'), 'layout_id' => Layout::query()->value('id')]);
        $published = Carbon::parse('2026-01-01 09:00:00');
        $page->url->update(['is_enabled' => true, 'published_at' => $published, 'expired_at' => null]);
        $key = "page/{$page->url->url_full}";
        $url = $page->url->url_full;

        checkSite([portablePage($url)])
            ->assertJsonPath('items.0.action', 'skip')
            ->assertJsonPath('items.0.warnings.0', __('cms.import.warnings.page_live'));

        confirmSite([portablePage($url)])->assertSessionHasNoErrors();
        expect($page->fresh()->name)->not->toBe('Imported');

        confirmSite([portablePage($url)], [$key])->assertSessionHasNoErrors();

        $page->refresh();

        expect($page->name)->toBe('Imported')
            ->and($page->url->status())->toBe(PublishStatus::Published)
            ->and($page->url->published_at->equalTo($published))->toBeTrue()
            ->and(Page::query()->whereHas('url', fn ($query) => $query->where('url_full', $url))->count())->toBe(1);
    });

    test('a parent in the same file comes first, whatever order they\'re in', function () {
        confirmSite([portablePage('/about/team/leeds'), portablePage('/about/team'), portablePage('/about')])->assertSessionHasNoErrors();

        $team = pageAtUrl('/about/team');

        expect(pageAtUrl('/about/team/leeds')->parent_id)->toBe($team->id)
            ->and($team->parent_id)->toBe(pageAtUrl('/about')->id)
            ->and($team->url->url_main)->toBe('/team');
    });

    test('a parent already on the target is used', function () {
        confirmSite([portablePage('/about')])->assertSessionHasNoErrors();
        confirmSite([portablePage('/about/team')])->assertSessionHasNoErrors();

        expect(pageAtUrl('/about/team')->parent_id)->toBe(pageAtUrl('/about')->id);
    });

    test('a page whose parent is in neither the file nor the target stops with a clear message', function () {
        checkSite([portablePage('/about/team')])
            ->assertJsonPath('items.0.errors.0', __('cms.import.validation.missing_parent', ['url' => '/about']));

        confirmSite([portablePage('/about/team')])->assertSessionHasErrors('json');

        expect(pageAtUrl('/about/team'))->toBeNull();
    });

    test('a missing layout or template stops that page', function () {
        checkSite([portablePage('/one', ['layout' => 'nowhere'])])
            ->assertJsonPath('items.0.errors.0', __('cms.import.validation.missing_layout', ['layout' => 'nowhere']));

        checkSite([portablePage('/two', ['template' => ['type' => 'page', 'slug' => 'nowhere']])])
            ->assertJsonPath('items.0.errors.0', __('cms.import.validation.item_template_missing', ['template' => 'page/nowhere']));
    });

    test('the home page can be imported', function () {
        confirmSite([portablePage('/')])->assertSessionHasNoErrors();

        expect(pageAtUrl('/')->slug)->toBe('')
            ->and(pageAtUrl('/')->parent_id)->toBeNull();
    });

    test('a URL that isn\'t a page URL is refused', function (string $url) {
        checkSite([portablePage($url)])
            ->assertJsonValidationErrors(['json' => __('cms.import.validation.unknown_item', ['number' => 1])]);
    })->with(['about', '/About', '/a b', '/about/', '//about', '/../etc']);

    test('page content goes through the same rules as the editor', function () {
        $template = Template::query()->where('slug', 'standard')->sole();
        TemplateField::query()->where('template_id', $template->id)->where('slug', 'heading')->update(['is_required' => true]);

        checkSite([portablePage('/imported', ['content' => []])])
            ->assertJsonPath('items.0.errors.0', __('validation.required', ['attribute' => 'Heading']));

        checkSite([portablePage('/imported', ['content' => ['heading' => 'A', 'next' => ['link_type' => 'url', 'url' => 'javascript:alert(1)']]])])
            ->assertJsonPath('items.0.errors.0', __('cms.menus.validation.url'));
    });

    test('the sharing image goes by its path, and is left empty with a warning if it\'s not here', function () {
        $file = File::factory()->create(['path' => 'images/share.jpg', 'mime_type' => 'image/jpeg']);

        confirmSite([portablePage('/one', ['metadata' => ['og_image' => ['file' => 'images/share.jpg']]])])->assertSessionHasNoErrors();
        expect(pageAtUrl('/one')->metadata->og_image_file_id)->toBe($file->id);

        checkSite([portablePage('/two', ['metadata' => ['og_image' => ['file' => 'images/gone.jpg'], 'og_image_file_id' => $file->id]])])
            ->assertJsonPath('items.0.warnings.0', __('cms.import.missing.optional', [
                'what' => __('cms.import.missing.kinds.file', ['reference' => 'images/gone.jpg']),
                'field' => __('cms.import.og_image'),
            ]));
    });

    test('importing pages needs content permission', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ManageStructure]));

        checkSite([portablePage('/imported')])->assertJsonPath('items.0.errors.0', __('cms.import.validation.not_allowed'));
    });
});

describe('references between items', function () {
    beforeEach(function () {
        sitePageTemplate();
        siteLayout();
    });

    test('a menu and a page that reference each other import correctly in one go', function () {
        confirmSite([
            ['kind' => 'menu', 'slug' => 'side', 'name' => 'Side', 'items' => [['label' => 'Team', 'link_type' => 'page', 'page' => '/team']]],
            portablePage('/team', ['content' => ['heading' => 'Team', 'side_menu' => ['menu' => 'side']]]),
        ])->assertSessionHasNoErrors();

        $menu = Menu::query()->where('slug', 'side')->sole();
        $page = pageAtUrl('/team');

        expect($menu->name)->toBe('Side')
            ->and($menu->menu_items->toCollection()->first()->page_id)->toBe($page->id)
            ->and($page->contentValues()['side_menu'])->toBe($menu->id);
    });

    test('pages can link to each other, and to one later in the file', function () {
        confirmSite([
            portablePage('/one', ['content' => ['next' => ['link_type' => 'page', 'page' => '/two', 'target' => null]]]),
            portablePage('/two', ['content' => ['next' => ['link_type' => 'page', 'page' => '/one', 'target' => '_blank']]]),
        ])->assertSessionHasNoErrors();

        expect(pageAtUrl('/one')->contentValues()['next']['page_id'])->toBe(pageAtUrl('/two')->id)
            ->and(pageAtUrl('/two')->contentValues()['next']['page_id'])->toBe(pageAtUrl('/one')->id)
            ->and(pageAtUrl('/two')->contentValues()['next']['target'])->toBe('_blank');
    });

    test('a layout can use a menu that links to a page using that layout', function () {
        $layoutTemplate = Template::factory()->ofType(TemplateType::Layout)->create(['slug' => 'site']);
        TemplateField::factory()->for($layoutTemplate)->create(['type' => TemplateFieldType::CmsMenu, 'name' => 'Menu', 'slug' => 'menu']);

        confirmSite([
            ['kind' => 'layout', 'slug' => 'site', 'name' => 'Site', 'template' => ['type' => 'layout', 'slug' => 'site'], 'content' => ['menu' => ['menu' => 'main']]],
            ['kind' => 'menu', 'slug' => 'main', 'name' => 'Main', 'items' => [['label' => 'Home', 'link_type' => 'page', 'page' => '/home']]],
            portablePage('/home', ['layout' => 'site']),
        ])->assertSessionHasNoErrors();

        $layout = Layout::query()->where('slug', 'site')->sole();
        $menu = Menu::query()->where('slug', 'main')->sole();

        expect($layout->contentValues()['menu'])->toBe($menu->id)
            ->and(pageAtUrl('/home')->layout_id)->toBe($layout->id)
            ->and($menu->pageIds())->toBe([pageAtUrl('/home')->id]);
    });

    test('a saved component can link to a page in the same file, and a page can use it', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'card']);
        TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Link, 'name' => 'Go', 'slug' => 'go']);
        TemplateField::factory()->for(Template::query()->where('slug', 'standard')->sole())->usingTemplate($card)->create(['slug' => 'card', 'order' => 3]);

        confirmSite([
            ['kind' => 'saved_component', 'slug' => 'promo', 'name' => 'Promo', 'template' => ['type' => 'component', 'slug' => 'card'], 'content' => ['go' => ['link_type' => 'page', 'page' => '/promo']]],
            portablePage('/promo', ['content' => ['card' => ['saved_component' => 'promo']]]),
        ])->assertSessionHasNoErrors();

        $saved = SavedComponent::query()->where('slug', 'promo')->sole();

        expect($saved->contentValues()['go']['page_id'])->toBe(pageAtUrl('/promo')->id)
            ->and(pageAtUrl('/promo')->contentValues()['card'])->toBe($saved->reference())
            ->and($saved->isInUse())->toBeTrue();
    });

    test('references to things that aren\'t here warn, and are left empty', function () {
        $items = [portablePage('/imported', ['content' => ['side_menu' => ['menu' => 'gone'], 'next' => ['link_type' => 'page', 'page' => '/gone']]])];

        $warnings = checkSite($items)->assertJsonPath('items.0.errors', [])->json('items.0.warnings');

        expect($warnings)->toContain(__('cms.import.missing.optional', [
            'what' => __('cms.import.missing.kinds.menu', ['reference' => 'gone']),
            'field' => 'Side menu',
        ]));

        confirmSite($items)->assertSessionHasNoErrors();

        expect(pageAtUrl('/imported')->contentValues())->toMatchArray(['side_menu' => null, 'next' => null]);
    });

    test('a menu item whose page isn\'t here is left out, with a warning', function () {
        $items = [['kind' => 'menu', 'slug' => 'side', 'name' => 'Side', 'items' => [
            ['label' => 'Gone', 'link_type' => 'page', 'page' => '/gone'],
            ['label' => 'Here', 'link_type' => 'url', 'url' => '/here'],
        ]]];

        checkSite($items)->assertJsonPath('items.0.warnings.0', __('cms.import.missing.menu_item', ['label' => 'Gone', 'page' => '/gone']));

        confirmSite($items)->assertSessionHasNoErrors();

        expect(Menu::query()->where('slug', 'side')->sole()->menu_items->toCollection()->pluck('label')->all())->toBe(['Here']);
    });

    test('ids in the paste go no further', function () {
        $menu = Menu::factory()->create();
        $target = Page::factory()->create();

        confirmSite([
            portablePage('/imported', ['content' => ['side_menu' => $menu->id, 'next' => ['link_type' => 'page', 'page_id' => $target->id]]]),
            ['kind' => 'menu', 'slug' => 'side', 'name' => 'Side', 'items' => [['label' => 'A', 'link_type' => 'page', 'page_id' => $target->id]]],
        ])->assertSessionHasNoErrors();

        expect(pageAtUrl('/imported')->contentValues())->toMatchArray(['side_menu' => null, 'next' => null])
            ->and(Menu::query()->where('slug', 'side')->sole()->menu_items->toCollection()->count())->toBe(0);
    });

    test('a failure in the second pass leaves nothing changed', function () {
        $menuBefore = Menu::query()->count();

        confirmSite([
            ['kind' => 'menu', 'slug' => 'side', 'name' => 'Side', 'items' => []],
            portablePage('/fine'),
            portablePage('/broken', ['content' => ['next' => ['link_type' => 'url', 'url' => 'javascript:alert(1)']]]),
        ])->assertSessionHasErrors('json');

        expect(Menu::query()->count())->toBe($menuBefore)
            ->and(pageAtUrl('/fine'))->toBeNull();
    });
});

describe('layouts and menus', function () {
    test('a layout is created and overwritten by its slug', function () {
        $template = Template::factory()->ofType(TemplateType::Layout)->create(['slug' => 'site']);
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Text, 'name' => 'Title', 'slug' => 'title']);
        $item = ['kind' => 'layout', 'slug' => 'main', 'name' => 'Main', 'template' => ['type' => 'layout', 'slug' => 'site'], 'content' => ['title' => 'First']];

        confirmSite([$item])->assertSessionHasNoErrors();

        $layout = Layout::query()->where('slug', 'main')->sole();

        checkSite([$item])->assertJsonPath('items.0.action', 'unchanged');

        confirmSite([[...$item, 'content' => ['title' => 'Second']]], ['layout/main'])->assertSessionHasNoErrors();

        expect($layout->fresh()->contentValues()['title'])->toBe('Second');
    });

    test('overwriting a layout warns which pages it changes', function () {
        $layout = siteLayout();
        Page::factory()->create(['layout_id' => $layout->id]);

        checkSite([['kind' => 'layout', 'slug' => 'main', 'name' => 'Renamed', 'template' => ['type' => 'layout', 'slug' => 'layout-main'], 'content' => []]])
            ->assertJsonPath('items.0.warnings.0', __('cms.import.warnings.in_use', ['places' => trans_choice('cms.import.places.pages', 1)]));
    });

    test('a layout must use a layout template', function () {
        Template::factory()->ofType(TemplateType::Component)->create(['slug' => 'card']);

        checkSite([['kind' => 'layout', 'slug' => 'main', 'name' => 'Main', 'template' => ['type' => 'component', 'slug' => 'card']]])
            ->assertJsonPath('items.0.errors.0', __('cms.layouts.validation.template_type'));
    });

    test('a menu keeps its items, nesting and link details', function () {
        confirmSite([['kind' => 'menu', 'slug' => 'main', 'name' => 'Main', 'items' => [
            ['label' => 'Top', 'link_type' => 'url', 'url' => '/top', 'target' => '_blank', 'rel' => 'nofollow', 'custom_class' => 'big', 'children' => [
                ['label' => 'Child', 'link_type' => 'url', 'url' => '/child'],
            ]],
        ]]])->assertSessionHasNoErrors();

        $top = Menu::query()->where('slug', 'main')->sole()->menu_items->toCollection()->first();

        expect($top->target)->toBe('_blank')
            ->and($top->rel)->toBe('nofollow')
            ->and($top->custom_class)->toBe('big')
            ->and($top->children[0]->label)->toBe('Child');
    });

    test('the same rules as the menu editor apply', function () {
        checkSite([['kind' => 'menu', 'slug' => 'main', 'name' => 'Main', 'items' => [['label' => 'Bad', 'link_type' => 'url', 'url' => 'javascript:alert(1)']]]])
            ->assertJsonPath('items.0.errors.0', __('cms.menus.validation.url'));
    });

    test('importing layouts and menus needs structure permission', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent, CmsPermission::ManageContent]));

        checkSite([['kind' => 'menu', 'slug' => 'main', 'name' => 'Main', 'items' => []]])
            ->assertJsonPath('items.0.errors.0', __('cms.import.validation.not_allowed'));
    });
});

describe('redirects', function () {
    test('are matched by the URL they start from, however it\'s written', function () {
        $redirect = Redirect::factory()->create(['url_from' => '/old', 'url_to' => '/first', 'is_enabled' => true, 'is_permanent' => true]);
        $item = ['kind' => 'redirect', 'url_from' => 'old/', 'url_to' => '/second', 'is_enabled' => true, 'is_permanent' => false];

        checkSite([$item])
            ->assertJsonPath('items.0.action', 'skip')
            ->assertJsonPath('items.0.key', 'redirect//old');

        confirmSite([$item], ['redirect//old'])->assertSessionHasNoErrors();

        expect($redirect->refresh()->url_to)->toBe('/second')
            ->and($redirect->is_permanent)->toBeFalse()
            ->and(Redirect::query()->count())->toBe(1);
    });

    test('overwriting keeps its dates, and a new one has none', function () {
        $date = Carbon::parse('2026-06-01 09:00:00');
        $redirect = Redirect::factory()->create(['url_from' => '/old', 'published_at' => $date]);

        confirmSite([
            ['kind' => 'redirect', 'url_from' => '/old', 'url_to' => '/second', 'is_enabled' => true, 'is_permanent' => true, 'published_at' => '2020-01-01T00:00'],
            ['kind' => 'redirect', 'url_from' => '/new', 'url_to' => '/third', 'is_enabled' => false, 'is_permanent' => true, 'published_at' => '2020-01-01T00:00'],
        ], ['redirect//old'])->assertSessionHasNoErrors();

        expect($redirect->refresh()->published_at->equalTo($date))->toBeTrue()
            ->and(Redirect::query()->where('url_from', '/new')->sole()->published_at)->toBeNull();
    });

    test('an enabled one warns it goes live straight away', function () {
        checkSite([['kind' => 'redirect', 'url_from' => '/old', 'url_to' => '/new', 'is_enabled' => true, 'is_permanent' => true]])
            ->assertJsonPath('items.0.warnings.0', __('cms.import.warnings.redirect_live'));

        checkSite([['kind' => 'redirect', 'url_from' => '/old', 'url_to' => '/new', 'is_enabled' => false, 'is_permanent' => true]])
            ->assertJsonPath('items.0.warnings', []);
    });

    test('the same rules as the admin apply, including chains', function () {
        checkSite([
            ['kind' => 'redirect', 'url_from' => '/a', 'url_to' => '/b', 'is_enabled' => true, 'is_permanent' => true],
            ['kind' => 'redirect', 'url_from' => '/b', 'url_to' => '/c', 'is_enabled' => true, 'is_permanent' => true],
        ])->assertJsonPath('items.1.errors.0', __('cms.redirects.validation.from_is_destination', ['url' => '/a']));
    });
});

describe('a round trip', function () {
    /**
     * Everything the site has, as an export would give it, in an order that doesn't depend on ids
     *
     * @return list<array<string, mixed>>
     */
    function exportedSite(): array
    {
        $exporter = app(SiteExporter::class);

        return collect([
            ...$exporter->pages(Page::query()->with('url')->get())['items'],
            ...$exporter->menus(Menu::query()->get())['items'],
            ...$exporter->redirects(Redirect::query()->get())['items'],
        ])
            ->unique(fn (array $item) => json_encode([$item['kind'], $item['type'] ?? '', $item['url'] ?? $item['slug'] ?? $item['url_from']]))
            ->sortBy(fn (array $item) => json_encode([$item['kind'], $item['type'] ?? '', $item['url'] ?? $item['slug'] ?? $item['url_from']]))
            ->values()
            ->all();
    }

    test('an export imports back as unchanged', function () {
        $this->seed(CmsSeeder::class);

        $results = checkSite(exportedSite())->assertOk()->json('items');

        expect(collect($results)->pluck('action')->unique()->values()->all())->toBe(['unchanged']);
    });

    test('importing a whole site gives identical content with different ids, and every page disabled', function () {
        $this->seed(CmsSeeder::class);

        $before = exportedSite();
        $idsBefore = Page::query()->pluck('id', 'slug')->all();

        Content::query()->delete();
        Page::query()->whereNotNull('parent_id')->delete();
        Page::query()->delete();
        Url::query()->delete();
        Metadata::query()->delete();
        SavedComponent::query()->delete();
        Layout::query()->delete();
        Menu::query()->delete();
        Redirect::query()->delete();
        Template::query()->delete();

        confirmSite($before)->assertSessionHasNoErrors();

        expect(exportedSite())->toEqual($before)
            ->and(Page::query()->pluck('id', 'slug')->all())->not->toBe($idsBefore)
            ->and(Url::query()->where('is_enabled', true)->count())->toBe(0)
            ->and(PageView::query()->where('url', '/imported')->count())->toBe(0);
    });
});
