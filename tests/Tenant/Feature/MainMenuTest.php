<?php

use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Enums\CrmPermission;
use App\Services\Authorisation\Enums\FileManagerPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Authorisation\Enums\TrackerPermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\NavigationService;

test('the main menu lists the modules alphabetically, then tools, admin and account', function () {
    $user = $this->createUserWithPermissions([
        CmsPermission::ViewContent,
        CrmPermission::ViewContacts,
        TrackerPermission::ViewTasks,
        FileManagerPermission::ViewFiles,
        CorePermission::ViewUsers,
        SettingsPermission::ViewSettings,
    ]);

    $sections = app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user);

    // The DJ section stays hidden until its first page is added
    expect($sections->map(fn ($section) => $section->first()->key)->all())->toBe([
        'dashboard',
        TenantMenu::MENU_MAIN_SECTION_CMS_LABEL,
        TenantMenu::MENU_MAIN_SECTION_CRM_LABEL,
        TenantMenu::MENU_MAIN_SECTION_TRACKER_LABEL,
        TenantMenu::MENU_MAIN_SECTION_TOOLS_LABEL,
        TenantMenu::MENU_MAIN_SECTION_ADMIN_LABEL,
        TenantMenu::MENU_MAIN_SECTION_ACCOUNT_LABEL,
    ]);
});

test('no menu link starts with another link\'s address, which would highlight both', function () {
    $user = $this->createUserWithPermissions(BookingsPermission::cases());
    $menu = json_encode(app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)->toArray(), JSON_UNESCAPED_SLASHES);

    expect($menu)->toContain(route('admin.bookings.staff.index', [], false));

    preg_match_all('/"to":"([^"]+)"/', $menu, $matches);
    $links = array_unique($matches[1]);

    foreach ($links as $link) {
        foreach ($links as $other) {
            $clashes = $other !== $link && str_starts_with($other, $link) && $other[strlen($link)] !== '/';

            expect($clashes)->toBeFalse("{$other} also highlights {$link}");
        }
    }
});
