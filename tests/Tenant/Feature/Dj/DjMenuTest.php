<?php

use App\Enums\TenantModule;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\Enums\MenuItemType;
use App\Services\Navigation\NavigationRegistry;
use App\Services\Navigation\Registrars\TenantMainMenuRegistrar;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([]);

    $this->registry = new NavigationRegistry;
    (new TenantMainMenuRegistrar)->register($this->registry);
});

/**
 * Stands in for the first DJ page, as the section's items are added by later tickets
 */
function addDjMenuItem(NavigationRegistry $registry): void
{
    $registry->addMenuItemToSection(
        new MenuItem(key: 'dj-test-item', sortOrder: 1, label: 'navigation.dj', type: MenuItemType::LINK, to: 'admin.dashboard'),
        TenantMenu::MENU_MAIN_SECTION_DJ,
        TenantMenu::MENU_MAIN,
    );
}

function djMenuLabelIsVisible(NavigationRegistry $registry, mixed $user): bool
{
    return $registry->resolveForUser(TenantMenu::MENU_MAIN, $user)
        ->flatten()
        ->contains(fn (MenuItem $item) => $item->key === TenantMenu::MENU_MAIN_SECTION_DJ_LABEL);
}

test('the DJ menu section is hidden while it has nothing in it', function () {
    expect(djMenuLabelIsVisible($this->registry, $this->user))->toBeFalse();
});

test('the DJ menu section is shown when the tenant has the DJ module', function () {
    addDjMenuItem($this->registry);

    expect(djMenuLabelIsVisible($this->registry, $this->user))->toBeTrue();
});

test('the DJ menu section is hidden when the tenant does not have the DJ module', function () {
    addDjMenuItem($this->registry);
    tenant()->update(['modules' => [TenantModule::Cms, TenantModule::Crm, TenantModule::Tracker]]);

    expect(djMenuLabelIsVisible($this->registry, $this->user))->toBeFalse();
});
