<?php

use App\Models\User;

test('the browser timezone is saved when the user has none', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.settings.timezone.update'), ['timezone' => 'Europe/London'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($user->refresh()->timezone)->toBe('Europe/London');
});

test('the browser timezone never overwrites one the user has chosen', function () {
    $user = User::factory()->inTimezone('America/New_York')->create();

    $this->actingAs($user)
        ->patch(route('admin.settings.timezone.update'), ['timezone' => 'Europe/London'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->timezone)->toBe('America/New_York');
});

test('an invalid browser timezone is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.settings.timezone.update'), ['timezone' => 'Mars/Olympus_Mons'])
        ->assertSessionHasErrors(['timezone']);

    expect($user->refresh()->timezone)->toBeNull();
});

test('the timezone can be changed on the profile page', function () {
    $user = User::factory()->inTimezone('Europe/London')->create();

    $this->actingAs($user)
        ->patch(route('admin.settings.profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'timezone' => 'Pacific/Auckland',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.settings.profile.edit'));

    expect($user->refresh()->timezone)->toBe('Pacific/Auckland');
});

test('the profile page rejects an invalid timezone', function () {
    $user = User::factory()->inTimezone('Europe/London')->create();

    $this->actingAs($user)
        ->patch(route('admin.settings.profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'timezone' => 'Not/AZone',
        ])
        ->assertSessionHasErrors(['timezone']);

    expect($user->refresh()->timezone)->toBe('Europe/London');
});
