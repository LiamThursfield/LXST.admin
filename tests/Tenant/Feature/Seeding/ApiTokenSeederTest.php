<?php

use App\Enums\TenantModule;
use App\Models\ApiToken;
use App\Models\Dj\Gig;
use App\Services\Authorisation\Enums\SettingsPermission;
use Carbon\CarbonImmutable;
use Database\Seeders\ApiTokenSeeder;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([SettingsPermission::ManageApiTokens]);
    app(ApiTokenSeeder::class)->run($this->user);
});

test('the API token seeder gives the user a fixed CLI token that reports now playing', function () {
    tenant()->update(['modules' => [TenantModule::Dj]]);
    Gig::factory()->between(CarbonImmutable::now()->subHour(), CarbonImmutable::now()->addHours(3))->create();

    expect(ApiTokenSeeder::token()->tokenable->is($this->user))->toBeTrue()
        ->and(ApiTokenSeeder::token()->abilities)->toBe(['dj:now-playing']);

    $this->withToken(ApiTokenSeeder::TOKEN)
        ->postJson(route('api.v1.dj.now-playing.store'), ['artist' => 'Tyla', 'title' => 'Water'])
        ->assertCreated();
});

test('running the API token seeder again creates nothing new', function () {
    app(ApiTokenSeeder::class)->run($this->user);

    expect(ApiToken::count())->toBe(1);
});
