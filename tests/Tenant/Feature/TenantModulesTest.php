<?php

use App\Enums\TenantModule;
use App\Services\Authorisation\Enums\TrackerPermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\NavigationService;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([TrackerPermission::ViewTasks]);
    $this->actingAs($this->user);
});

function trackerMenuIsVisible(mixed $user): bool
{
    $menu = app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user);

    return str_contains(
        json_encode($menu->toArray(), JSON_UNESCAPED_SLASHES),
        route('admin.tracker.today', [], false)
    );
}

test('tracker routes can be visited when the tenant has the tracker module', function () {
    $this->get(route('admin.tracker.today'))->assertOk();
});

test('tracker routes return not found when the tenant does not have the tracker module', function () {
    tenant()->update(['modules' => [TenantModule::Cms, TenantModule::Crm]]);

    $this->get(route('admin.tracker.today'))->assertNotFound();
    $this->get(route('admin.tracker.tasks.index'))->assertNotFound();
});

test('the tracker menu section is shown when the tenant has the tracker module', function () {
    expect(trackerMenuIsVisible($this->user))->toBeTrue();
});

test('the tracker menu section is hidden when the tenant does not have the tracker module', function () {
    tenant()->update(['modules' => []]);

    expect(trackerMenuIsVisible($this->user))->toBeFalse();
});
