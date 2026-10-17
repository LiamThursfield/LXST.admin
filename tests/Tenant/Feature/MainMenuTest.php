<?php

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
