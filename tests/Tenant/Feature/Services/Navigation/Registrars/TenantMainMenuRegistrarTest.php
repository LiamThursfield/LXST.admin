<?php

use App\Models\User;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\NavigationRegistry;
use App\Services\Navigation\Registrars\TenantMainMenuRegistrar;

it('registers the tenant menus to the navigation registry', function () {
    $registrar = app(TenantMainMenuRegistrar::class);

    $registry = new NavigationRegistry;

    // Initial manual invocation of registrar
    $registrar->register($registry);

    $user = User::factory()->make();

    $resolvedMenu = $registry->resolveForUser(TenantMenu::MENU_MAIN, $user);

    expect($resolvedMenu)->not->toBeEmpty()
        ->and($resolvedMenu[0]->first()->key)->toBe(TenantMenu::MENU_MAIN_SECTION_DASHBOARD);
});
