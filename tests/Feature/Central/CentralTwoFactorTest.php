<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the central security page asks for your password on the central confirm page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('central.admin.settings.security.edit'))
        ->assertRedirect(route('central.password.confirm'));
});

test('the central security page shows whether two-factor login is on', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('central.admin.settings.security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/Security')
            ->where('twoFactorEnabled', true)
        );
});

test('turning on two-factor login on the central admin asks for your password on the central confirm page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('central.two-factor.enable'))
        ->assertRedirect(route('central.password.confirm'));

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('two-factor login can be turned on from the central admin once your password is confirmed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('central.two-factor.enable'))
        ->assertRedirect();

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

test('central users with two-factor login on are asked for a code on the central admin', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('central.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('central.two-factor.login'));

    $this->assertGuest();

    $this->get(route('central.two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/TwoFactorChallenge'));
});

test('central users can finish logging in with a recovery code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('central.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post(route('central.two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

test('central users can not finish logging in with a wrong code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('central.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post(route('central.two-factor.login.store'), [
        'recovery_code' => 'not-a-recovery-code',
    ])->assertSessionHasErrors('recovery_code');

    $this->assertGuest();
});
