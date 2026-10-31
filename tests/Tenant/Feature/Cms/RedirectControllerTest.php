<?php

use App\Enums\Cms\RedirectStatus;
use App\Enums\TenantModule;
use App\Models\Cms\Redirect;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\NavigationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function redirectData(array $overrides = []): array
{
    return [
        'url_from' => '/old-page',
        'url_to' => '/new-page',
        'is_enabled' => true,
        'is_permanent' => true,
        'published_at' => null,
        'expired_at' => null,
        ...$overrides,
    ];
}

describe('managing', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('redirects are listed, and can be searched and filtered by status', function () {
        Redirect::factory()->create(['url_from' => '/summer-sale', 'url_to' => '/offers']);
        Redirect::factory()->disabled()->create(['url_from' => '/winter-sale', 'url_to' => '/offers']);
        Redirect::factory()->create(['url_from' => '/about-us', 'url_to' => '/about']);

        $this->get(route('admin.cms.redirects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/redirect/Index')
                ->has('redirects.data', 3)
                ->where('canCreate', true)
            );

        $this->get(route('admin.cms.redirects.index', ['filter' => ['search' => 'sale']]))
            ->assertInertia(fn (Assert $page) => $page->has('redirects.data', 2));

        $this->get(route('admin.cms.redirects.index', ['filter' => ['search' => 'sale', 'status' => 'disabled']]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('redirects.data', 1)
                ->where('redirects.data.0.url_from', '/winter-sale')
            );
    });

    test('a redirect can be created, with URLs tidied and dates entered on the user\'s clock', function () {
        $user = $this->createUserWithPermissions(CmsPermission::cases());
        $user->update(['timezone' => 'Europe/London']);
        $this->actingAs($user);

        $this->post(route('admin.cms.redirects.store'), redirectData([
            'url_from' => 'Old-Page/',
            'url_to' => 'https://example.com/elsewhere',
            'published_at' => '2026-07-01T09:00',
        ]))
            ->assertRedirect(route('admin.cms.redirects.index'))
            ->assertSessionHas('success', __('cms.redirects.flash.created'));

        $redirect = Redirect::query()->sole();

        expect($redirect->url_from)->toBe('/Old-Page')
            ->and($redirect->url_to)->toBe('https://example.com/elsewhere')
            ->and($redirect->httpStatus())->toBe(301)
            // 09:00 British Summer Time is 08:00 UTC
            ->and($redirect->published_at?->toIso8601String())->toBe('2026-07-01T08:00:00+00:00');
    });

    test('a redirect can be edited, and deleted', function () {
        $redirect = Redirect::factory()->create(['url_from' => '/old-page']);

        $this->get(route('admin.cms.redirects.edit', $redirect))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/redirect/Edit')
                ->where('redirect.data.url_from', '/old-page')
            );

        $this->put(route('admin.cms.redirects.update', $redirect), redirectData(['url_from' => '/old-page', 'url_to' => '/newer-page', 'is_permanent' => false]))
            ->assertRedirect(route('admin.cms.redirects.index'));

        expect($redirect->refresh()->url_to)->toBe('/newer-page')
            ->and($redirect->httpStatus())->toBe(302);

        $this->delete(route('admin.cms.redirects.destroy', $redirect))
            ->assertSessionHas('success', __('cms.redirects.flash.deleted'));

        expect(Redirect::query()->count())->toBe(0);
    });
});

describe('validation', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    });

    test('a URL can only be redirected once, and not to itself', function () {
        Redirect::factory()->create(['url_from' => '/old-page']);

        $this->post(route('admin.cms.redirects.store'), redirectData(['url_from' => '/old-page/']))
            ->assertSessionHasErrors(['url_from' => __('cms.redirects.validation.from_taken')]);

        $this->post(route('admin.cms.redirects.store'), redirectData(['url_from' => '/same', 'url_to' => 'same/']))
            ->assertSessionHasErrors(['url_from' => __('cms.redirects.validation.same_url')]);
    });

    test('chains and loops are refused', function () {
        Redirect::factory()->create(['url_from' => '/a', 'url_to' => '/b']);

        // B → C would make A → B → C
        $this->post(route('admin.cms.redirects.store'), redirectData(['url_from' => '/b', 'url_to' => '/c']))
            ->assertSessionHasErrors(['url_from' => __('cms.redirects.validation.from_is_destination', ['url' => '/a'])]);

        // Z → A would make Z → A → B
        $this->post(route('admin.cms.redirects.store'), redirectData(['url_from' => '/z', 'url_to' => '/a']))
            ->assertSessionHasErrors(['url_to' => __('cms.redirects.validation.to_is_redirected', ['url' => '/a'])]);

        // B → A would loop
        $this->post(route('admin.cms.redirects.store'), redirectData(['url_from' => '/b', 'url_to' => '/a']))
            ->assertSessionHasErrors(['url_from', 'url_to']);

        // Editing a redirect doesn't clash with itself
        $redirect = Redirect::query()->sole();
        $this->put(route('admin.cms.redirects.update', $redirect), redirectData(['url_from' => '/a', 'url_to' => '/b', 'is_permanent' => false]))
            ->assertSessionHasNoErrors();
    });

    test('the end must be after the start, and external URLs must be valid', function () {
        $this->post(route('admin.cms.redirects.store'), redirectData([
            'url_to' => 'https://',
            'published_at' => '2026-07-02T09:00',
            'expired_at' => '2026-07-01T09:00',
        ]))->assertSessionHasErrors([
            'url_to' => __('cms.redirects.validation.to_invalid'),
            'expired_at' => __('cms.redirects.validation.expires_before_publish'),
        ]);
    });
});

test('a redirect\'s status follows its enabled flag and dates, in the model and the status filter', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $active = Redirect::factory()->create(['published_at' => now()->subDay(), 'expired_at' => now()->addDay()]);
    $scheduled = Redirect::factory()->scheduled()->create();
    $expired = Redirect::factory()->expired()->create();
    $disabled = Redirect::factory()->disabled()->scheduled()->create();

    expect($active->status())->toBe(RedirectStatus::Active)
        ->and($scheduled->status())->toBe(RedirectStatus::Scheduled)
        ->and($expired->status())->toBe(RedirectStatus::Expired)
        ->and($disabled->status())->toBe(RedirectStatus::Disabled);

    foreach (RedirectStatus::cases() as $status) {
        $ids = Redirect::query()->withStatus($status)->pluck('id')->all();

        expect($ids)->toBe([${$status->value}->id]);
    }

    expect(Redirect::query()->active()->pluck('id')->all())->toBe([$active->id]);
});

describe('access', function () {
    test('users who can only view the site structure can list and open redirects but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewStructure]));
        $redirect = Redirect::factory()->create(['url_from' => '/old-page']);

        $this->get(route('admin.cms.redirects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreate', false)
                ->has('redirects.data.0.actions', 1)
                ->where('redirects.data.0.actions.0.url', route('admin.cms.redirects.show', $redirect))
            );

        $this->get(route('admin.cms.redirects.show', $redirect))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/cms/redirect/Show')
                ->where('redirect.data.url_from', '/old-page')
            );

        $this->get(route('admin.cms.redirects.edit', $redirect))->assertForbidden();
        $this->get(route('admin.cms.redirects.create'))->assertForbidden();
        $this->post(route('admin.cms.redirects.store'), redirectData())->assertForbidden();
        $this->put(route('admin.cms.redirects.update', $redirect), redirectData())->assertForbidden();
        $this->delete(route('admin.cms.redirects.destroy', $redirect))->assertForbidden();
    });

    test('users with only content permissions cannot see redirects', function () {
        $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent, CmsPermission::ManageContent]));

        $this->get(route('admin.cms.redirects.index'))->assertForbidden();
    });

    test('admins manage the structure, editors manage content and view the structure, and viewers only view', function () {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = Role::findByName(RoleEnum::Admin->value);
        $editor = Role::findByName(RoleEnum::Editor->value);
        $viewer = Role::findByName(RoleEnum::Viewer->value);

        expect($admin->hasPermissionTo(CmsPermission::ManageStructure->value))->toBeTrue()
            ->and($editor->hasPermissionTo(CmsPermission::ManageContent->value))->toBeTrue()
            ->and($editor->hasPermissionTo(CmsPermission::ViewStructure->value))->toBeTrue()
            ->and($editor->hasPermissionTo(CmsPermission::ManageStructure->value))->toBeFalse()
            ->and($viewer->hasPermissionTo(CmsPermission::ViewStructure->value))->toBeTrue()
            ->and($viewer->hasPermissionTo(CmsPermission::ManageContent->value))->toBeFalse();
    });

    test('redirects and the CMS menu are hidden when the tenant doesn\'t have the CMS module', function () {
        $user = $this->createUserWithPermissions([CmsPermission::ViewStructure]);
        $this->actingAs($user);

        $menuHasRedirects = fn () => str_contains(
            json_encode(app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)->toArray(), JSON_UNESCAPED_SLASHES),
            route('admin.cms.redirects.index', [], false)
        );

        expect($menuHasRedirects())->toBeTrue();

        tenant()->update(['modules' => [TenantModule::Crm, TenantModule::Tracker]]);

        $this->get(route('admin.cms.redirects.index'))->assertNotFound();
        expect($menuHasRedirects())->toBeFalse();
    });
});
