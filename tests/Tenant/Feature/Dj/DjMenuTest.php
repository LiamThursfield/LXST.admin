<?php

use App\Enums\TenantModule;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\NavigationService;

/**
 * The keys of the DJ section's visible items, or an empty list when the section is hidden
 *
 * @return list<string>
 */
function visibleDjMenuKeys(mixed $user): array
{
    return app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)
        ->first(fn ($section) => $section->contains(fn (MenuItem $item) => $item->key === TenantMenu::MENU_MAIN_SECTION_DJ_LABEL))
        ?->pluck('key')
        ->all() ?? [];
}

test('the DJ menu section shows the library to users who can view it', function () {
    $user = $this->createUserWithPermissions([DjPermission::ViewLibrary]);

    expect(visibleDjMenuKeys($user))->toBe([
        TenantMenu::MENU_MAIN_SECTION_DJ_LABEL,
        TenantMenu::MENU_MAIN_SECTION_DJ_ITEM_LIBRARY,
    ]);
});

test('the DJ menu section is hidden from users with no DJ permissions', function () {
    expect(visibleDjMenuKeys($this->createUserWithPermissions([])))->toBe([]);
});

test('the DJ menu section is hidden when the tenant does not have the DJ module', function () {
    $user = $this->createUserWithPermissions([DjPermission::ViewLibrary]);
    tenant()->update(['modules' => [TenantModule::Cms, TenantModule::Crm, TenantModule::Tracker]]);

    expect(visibleDjMenuKeys($user))->toBe([]);
});
