<?php

use App\Models\Bookings\Service;
use App\Models\User;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Enums\CrmPermission;
use App\Services\Authorisation\Enums\FileManagerPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Authorisation\Enums\TrackerPermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\NavigationService;
use Illuminate\Support\Collection;

/**
 * The visible Bookings section for the user, keyed by item key, with each group's visible child keys
 *
 * @return array<string, list<string>>
 */
function bookingsMenuFor(User $user): array
{
    /** @var Collection<int, MenuItem> $section */
    $section = app(NavigationService::class)
        ->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)
        ->first(fn (Collection $section) => $section->first()->key === TenantMenu::MENU_MAIN_SECTION_BOOKINGS_LABEL, collect());

    return $section
        ->reject(fn (MenuItem $item) => $item->isLabel())
        ->mapWithKeys(fn (MenuItem $item) => [$item->key => $item->children->pluck('key')->all()])
        ->all();
}

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

test('the bookings section keeps the day-to-day links at the top and groups the rest', function () {
    tenant()->update(['industry_overrides' => ['booking_types' => ['appointment', 'class']]]);

    $menu = bookingsMenuFor($this->createUserWithPermissions(BookingsPermission::cases()));

    expect($menu)->toBe([
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_CALENDAR => [],
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_BOOKINGS => [],
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_SESSIONS => [],
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_WAITLIST => [],
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_GROUP_MONEY => [
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_PAYMENTS,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_QUOTES,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_INVOICES,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_GIFT_CARDS,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_PAYMENT_REPORT,
        ],
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_GROUP_SERVICES => [
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_SERVICES,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_SERVICE_CATEGORIES,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_RESOURCES,
        ],
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_GROUP_TEAM => [
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_STAFF,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_ROTA,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_TIME_OFF,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_STAFF_HOURS,
        ],
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_GROUP_MESSAGES => [
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_MESSAGES,
            TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_MESSAGE_LOG,
        ],
    ]);
});

test('a bookings group is hidden when the user can see none of its links', function () {
    $menu = bookingsMenuFor($this->createUserWithPermissions([BookingsPermission::ViewBookings]));

    expect($menu)->not->toHaveKeys([
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_GROUP_MONEY,
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_GROUP_SERVICES,
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_GROUP_TEAM,
    ])->and($menu[TenantMenu::MENU_MAIN_SECTION_BOOKINGS_GROUP_MESSAGES])->toBe([
        TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_MESSAGE_LOG,
    ]);
});

test('the timetable link only shows for tenants that run classes or tickets', function (array $bookingTypes, bool $hasClassService, bool $shown) {
    tenant()->update(['industry_overrides' => ['booking_types' => $bookingTypes]]);

    if ($hasClassService) {
        Service::factory()->groupClass()->create();
    }

    $menu = bookingsMenuFor($this->createUserWithPermissions([BookingsPermission::ViewBookings]));

    expect(array_key_exists(TenantMenu::MENU_MAIN_SECTION_BOOKINGS_ITEM_SESSIONS, $menu))->toBe($shown);
})->with([
    'appointments only' => [['appointment'], false, false],
    'classes' => [['appointment', 'class'], false, true],
    'tickets' => [['ticket'], false, true],
    'appointments only, with a class service already' => [['appointment'], true, true],
]);
