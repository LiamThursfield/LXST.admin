<?php

use App\Data\Cms\MenuItemData;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Models\Cms\Layout;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Authorisation\Enums\CmsPermission;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function menuItem(string $label, array $overrides = []): array
{
    return [
        'label' => $label,
        'link_type' => 'url',
        'page_id' => null,
        'url' => '/'.strtolower($label),
        'target' => null,
        'rel' => null,
        'custom_class' => null,
        'children' => [],
        ...$overrides,
    ];
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function menuData(array $items = [], array $overrides = []): array
{
    return ['name' => 'Main menu', 'slug' => '', 'menu_items' => $items, ...$overrides];
}

describe('managing', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('a menu is saved with nested items linking to pages and custom URLs', function () {
        $page = Page::factory()->create(['slug' => 'about']);

        $this->post(route('admin.cms.menus.store'), menuData([
            menuItem('About', ['link_type' => 'page', 'page_id' => $page->id, 'url' => 'ignored', '_key' => 4, 'children' => [
                menuItem('Team', ['children' => [menuItem('Jo', ['target' => '_blank', 'rel' => 'nofollow', 'custom_class' => 'highlight'])]]),
            ]]),
            menuItem('Contact', ['url' => 'mailto:hi@example.com']),
        ]))
            ->assertRedirect(route('admin.cms.menus.index'))
            ->assertSessionHas('success', __('cms.menus.flash.created'));

        $menu = Menu::query()->sole();
        /** @var MenuItemData $about */
        $about = $menu->menu_items[0];

        expect($menu->slug)->toBe('main-menu')
            ->and($about->link_type)->toBe('page')
            ->and($about->page_id)->toBe($page->id)
            // A page link keeps no URL; the API resolves the page's current one
            ->and($about->url)->toBeNull()
            ->and($about->children[0]->children[0]->label)->toBe('Jo')
            ->and($about->children[0]->children[0]->target)->toBe('_blank')
            ->and($menu->menu_items[1]->url)->toBe('mailto:hi@example.com')
            ->and($menu->pageIds())->toBe([$page->id]);

        $this->get(route('admin.cms.menus.edit', $menu))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('admin/cms/menu/Edit')
                ->where('menu.data.items_count', 4)
                ->where('menu.data.menu_items.0.children.0.label', 'Team')
                ->where('pages.0.url', '/about')
            );
    });

    test('a menu can be deleted, unless a page or layout uses it', function () {
        $menu = Menu::factory()->create();
        $unused = Menu::factory()->create();

        $template = Template::factory()->ofType(TemplateType::Layout)->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::CmsMenu, 'slug' => 'nav']);

        $this->post(route('admin.cms.layouts.store'), [
            'template_id' => $template->id,
            'name' => 'Main',
            'content' => ['nav' => (string) $menu->id],
        ])->assertSessionHasNoErrors();

        expect(Layout::query()->sole()->contentValues())->toBe(['nav' => $menu->id]);

        $this->delete(route('admin.cms.menus.destroy', $menu))
            ->assertSessionHasErrors(['menu' => __('cms.menus.validation.in_use')]);

        $this->delete(route('admin.cms.menus.destroy', $unused))
            ->assertSessionHas('success', __('cms.menus.flash.deleted'));

        expect(Menu::query()->pluck('id')->all())->toBe([$menu->id]);
    });
});

test('menu pages keep the shared sidebar navigation (the `menus` prop)', function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    Menu::factory()->create();
    $layout = Layout::factory()->create();

    $this->get(route('admin.cms.menus.index'))
        ->assertInertia(fn (Assert $page) => $page->has('menus.main')->has('cmsMenus.data', 1));

    $this->get(route('admin.cms.layouts.edit', $layout))
        ->assertInertia(fn (Assert $page) => $page->has('menus.main')->has('menuOptions', 1));
});

describe('validation', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('items are validated at every level', function () {
        $this->post(route('admin.cms.menus.store'), menuData([
            menuItem('Top', ['children' => [
                menuItem('', ['children' => [menuItem('Deep', ['link_type' => 'page', 'page_id' => 999])]]),
            ]]),
            menuItem('Bad link', ['url' => 'javascript:alert(1)']),
            menuItem('Bad class', ['custom_class' => '"><script>']),
        ]))->assertSessionHasErrors([
            'menu_items.0.children.0.label' => __('validation.required', ['attribute' => __('cms.menus.item.label')]),
            'menu_items.0.children.0.children.0.page_id',
            'menu_items.1.url' => __('cms.menus.validation.url'),
            'menu_items.2.custom_class' => __('cms.menus.validation.custom_class'),
        ]);
    });

    test('menus can only be three levels deep, and slugs are unique', function () {
        Menu::factory()->create(['slug' => 'main-menu']);

        $this->post(route('admin.cms.menus.store'), menuData([
            menuItem('One', ['children' => [menuItem('Two', ['children' => [menuItem('Three', ['children' => [menuItem('Four')]])]])]]),
        ]))->assertSessionHasErrors([
            'slug' => __('cms.menus.validation.slug_taken'),
            'menu_items.0.children.0.children.0.children' => __('cms.menus.validation.too_deep', ['depth' => 3]),
        ]);
    });

    test('a menu content field must be an existing menu', function () {
        $template = Template::factory()->ofType(TemplateType::Layout)->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::CmsMenu, 'name' => 'Navigation', 'slug' => 'nav', 'is_required' => true]);

        $this->post(route('admin.cms.layouts.store'), ['template_id' => $template->id, 'name' => 'Main', 'content' => ['nav' => 999]])
            ->assertSessionHasErrors(['content.nav' => __('validation.exists', ['attribute' => 'Navigation'])]);

        expect(TemplateFieldType::CmsMenu->isAvailable())->toBeTrue();
    });
});

describe('access', function () {
    test('menus need the structure permissions, and viewers can only look', function () {
        $menu = Menu::factory()->create();

        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent, CmsPermission::ManageContent]));
        $this->get(route('admin.cms.menus.index'))->assertForbidden();

        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewStructure]));
        $this->get(route('admin.cms.menus.show', $menu))->assertOk();
        $this->get(route('admin.cms.menus.create'))->assertForbidden();
        $this->post(route('admin.cms.menus.store'), menuData())->assertForbidden();
        $this->put(route('admin.cms.menus.update', $menu), menuData())->assertForbidden();
        $this->delete(route('admin.cms.menus.destroy', $menu))->assertForbidden();
    });

    test('menus are hidden when the tenant doesn\'t have the CMS module', function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->get(route('admin.cms.menus.index'))->assertNotFound();
    });
});
