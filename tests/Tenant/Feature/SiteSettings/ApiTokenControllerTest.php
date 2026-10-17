<?php

use App\Enums\Api\ApiTokenAbility;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\NavigationService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions(SettingsPermission::cases(), ['timezone' => 'Europe/London']);
    $this->actingAs($this->user);
});

test('the index lists every token on the site, with who created it', function () {
    $other = User::factory()->create(['first_name' => 'Sam', 'last_name' => 'Jones']);
    ApiToken::factory()->for($other, 'tokenable')->create(['name' => 'Rekordbox CLI', 'created_at' => now()->subDay()]);
    ApiToken::factory()->for($this->user, 'tokenable')->expired()->create(['name' => 'Old token']);

    $this->get(route('admin.site-settings.api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site-settings/ApiTokens')
            ->has('tokens.data', 2)
            ->where('tokens.data.0.name', 'Old token')
            ->where('tokens.data.0.is_expired', true)
            ->where('tokens.data.1.name', 'Rekordbox CLI')
            ->where('tokens.data.1.owner', 'Sam Jones')
            ->missing('tokens.data.1.token')
            ->where('canCreate', true)
        );
});

test('the index can be searched by name', function () {
    ApiToken::factory()->for($this->user, 'tokenable')->create(['name' => 'Rekordbox CLI']);
    ApiToken::factory()->for($this->user, 'tokenable')->create(['name' => 'Something else']);

    $this->get(route('admin.site-settings.api-tokens.index', ['filter' => ['search' => 'rekord']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tokens.data', 1)
            ->where('tokens.data.0.name', 'Rekordbox CLI')
        );
});

test('a token is created with its abilities and flashed once in plain text', function () {
    $this->post(route('admin.site-settings.api-tokens.store'), [
        'name' => 'Rekordbox CLI',
        'abilities' => [ApiTokenAbility::DjSync->value, ApiTokenAbility::DjNowPlaying->value],
    ])
        ->assertRedirect(route('admin.site-settings.api-tokens.index'))
        ->assertSessionHas('success', __('site_settings.api_tokens.flash.created'));

    $token = ApiToken::sole();
    $flashed = session('inertia.flash_data')['apiToken'];

    expect($token->name)->toBe('Rekordbox CLI')
        ->and($token->tokenable->is($this->user))->toBeTrue()
        ->and($token->abilities)->toBe(['dj:sync', 'dj:now-playing'])
        ->and($token->expires_at)->toBeNull()
        ->and($flashed['name'])->toBe('Rekordbox CLI')
        ->and(ApiToken::findToken($flashed['token'])?->is($token))->toBeTrue()
        // Only the hash is stored
        ->and($token->token)->not->toBe(explode('|', $flashed['token'], 2)[1]);
});

test('a token expires at the end of the chosen day on the user\'s clock', function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-01 12:00', 'UTC'));

    $this->post(route('admin.site-settings.api-tokens.store'), [
        'name' => 'Rekordbox CLI',
        'abilities' => [ApiTokenAbility::DjSync->value],
        'expires_on' => '2026-07-31',
    ])->assertSessionHasNoErrors();

    // 23:59:59 in London (BST) is 22:59:59 UTC
    expect(ApiToken::sole()->expires_at->toIso8601String())->toBe('2026-07-31T22:59:59+00:00');
});

test('creating a token is validated', function (array $data, array $errors) {
    $this->post(route('admin.site-settings.api-tokens.store'), [
        'name' => 'Rekordbox CLI',
        'abilities' => [ApiTokenAbility::DjSync->value],
        ...$data,
    ])->assertSessionHasErrors($errors);

    expect(ApiToken::count())->toBe(0);
})->with([
    'no name' => [['name' => ''], ['name']],
    'a name too long' => [['name' => str_repeat('a', 256)], ['name']],
    'no abilities' => [['abilities' => []], ['abilities']],
    'an unknown ability' => [['abilities' => ['cms:everything']], ['abilities.0']],
    'an expiry in the past' => [['expires_on' => '2020-01-01'], ['expires_on']],
    'an expiry that is not a date' => [['expires_on' => 'soon'], ['expires_on']],
]);

test('a token can be revoked', function () {
    $token = ApiToken::factory()->for(User::factory(), 'tokenable')->create();

    $this->delete(route('admin.site-settings.api-tokens.destroy', $token))
        ->assertRedirect()
        ->assertSessionHas('success', __('site_settings.api_tokens.flash.revoked'));

    expect($token->fresh())->toBeNull();
});

test('users who can only view settings cannot manage API tokens', function () {
    $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings, SettingsPermission::ManageSettings]));
    $token = ApiToken::factory()->for($this->user, 'tokenable')->create();

    $this->get(route('admin.site-settings.api-tokens.index'))->assertForbidden();
    $this->post(route('admin.site-settings.api-tokens.store'), [
        'name' => 'Rekordbox CLI',
        'abilities' => [ApiTokenAbility::DjSync->value],
    ])->assertForbidden();
    $this->delete(route('admin.site-settings.api-tokens.destroy', $token))->assertForbidden();

    expect(ApiToken::count())->toBe(1);
});

test('only admins are given the permission to manage API tokens', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::findByName(RoleEnum::Admin->value)->hasPermissionTo(SettingsPermission::ManageApiTokens->value))->toBeTrue()
        ->and(Role::findByName(RoleEnum::Editor->value)->hasPermissionTo(SettingsPermission::ManageApiTokens->value))->toBeFalse()
        ->and(Role::findByName(RoleEnum::Viewer->value)->hasPermissionTo(SettingsPermission::ManageApiTokens->value))->toBeFalse();
});

test('the API tokens menu item is only shown to users who can manage them', function () {
    $hasMenuItem = fn (User $user) => app(NavigationService::class)
        ->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)
        ->flatten()
        ->flatMap(fn (MenuItem $item) => $item->children)
        ->contains(fn ($child) => $child->key === TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_API_TOKENS);

    expect($hasMenuItem($this->user))->toBeTrue()
        ->and($hasMenuItem($this->createUserWithPermissions([SettingsPermission::ViewSettings])))->toBeFalse();
});
