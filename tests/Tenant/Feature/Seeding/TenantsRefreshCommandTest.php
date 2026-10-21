<?php

use App\Models\User;

test('the reset needs a tenant that exists', function () {
    $this->artisan('tenants:refresh', ['tenant' => 'nope'])
        ->expectsOutputToContain('No tenant with the ID nope.')
        ->assertFailed();
});

test('the reset only runs locally unless forced', function () {
    app()->detectEnvironment(fn () => 'production');
    $user = User::factory()->create();

    $this->artisan('tenants:refresh', ['tenant' => tenant()->getTenantKey(), '--seed' => true])
        ->expectsOutputToContain('only runs locally')
        ->assertFailed();

    expect($user->fresh())->not->toBeNull();
});

test('the reset asks first, and changes nothing when the answer is no', function () {
    app()->detectEnvironment(fn () => 'local');
    $user = User::factory()->create();

    $this->artisan('tenants:refresh', ['tenant' => tenant()->getTenantKey()])
        ->expectsConfirmation('Delete all of tenant '.tenant()->getTenantKey()."'s data and start again?", 'no')
        ->expectsOutputToContain('Nothing was changed.')
        ->assertSuccessful();

    expect($user->fresh())->not->toBeNull();
});
