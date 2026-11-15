<?php

use App\Models\User;

test('the registration screen is not available while registration is closed', function () {
    $response = $this->get(route('register'));

    $response->assertNotFound();
});

test('nobody can register while registration is closed', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('the registration screen can be rendered while registration is open', function () {
    config(['fortify.registration_open' => true]);

    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register while registration is open', function () {
    config(['fortify.registration_open' => true]);

    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});
