<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('security settings page asks for your password first', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('admin.settings.security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security settings page is displayed once your password is confirmed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.security.edit'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/Security')
            ->where('twoFactorEnabled', false)
        );
});

test('security settings page shows when two-factor login is on', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.security.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('twoFactorEnabled', true)
    );
});

test('two-factor login that was never confirmed with a code is turned off when you come back', function () {
    $user = User::factory()->create();
    $user->forceFill(['two_factor_secret' => encrypt('secret')])->save();

    // Set-up started a minute ago, and the page was left without entering a code
    $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'two_factor_confirming_at' => time() - 60,
        ])
        ->get(route('admin.settings.security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('twoFactorEnabled', false)
        );

    expect($user->fresh()->two_factor_secret)->toBeNull();
});
