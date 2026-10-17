<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->me = User::factory()->create(['email' => 'me@example.com']);
    $this->actingAs($this->me);
});

test('central users are listed and can be searched', function () {
    User::factory()->create(['first_name' => 'Ada', 'email' => 'ada@example.com']);
    User::factory()->create(['first_name' => 'Grace', 'email' => 'grace@example.com']);

    $this->get(route('central.admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/user/Index')
            ->has('users.data', 3)
            ->where('users.data.2.email', 'me@example.com')
            ->where('users.data.2.is_you', true)
            // You can't delete yourself from the list (only edit)
            ->has('users.data.2.actions', 1)
            ->has('users.data.0.actions', 2)
        );

    $this->get(route('central.admin.users.index', ['filter' => ['search' => 'grac']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', 'grace@example.com')
        );
});

test('a new central user is invited to set their password on the central admin', function () {
    Notification::fake();

    $this->post(route('central.admin.users.store'), [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ])
        ->assertRedirect(route('central.admin.users.index'))
        ->assertSessionHas('success', __('users.flash.created', ['email' => 'ada@example.com']));

    $ada = User::query()->where('email', 'ada@example.com')->sole();

    Notification::assertSentTo($ada, ResetPassword::class, function (ResetPassword $notification) use ($ada) {
        $url = $notification->toMail($ada)->actionUrl;

        return str_starts_with($url, config('app.url').'/reset-password/');
    });
});

test('a central user needs a name and a unique email', function () {
    $this->post(route('central.admin.users.store'), [
        'first_name' => '',
        'last_name' => '',
        'email' => 'me@example.com',
    ])->assertSessionHasErrors(['first_name', 'last_name', 'email']);
});

test('a central user can be edited, keeping their own email', function () {
    $ada = User::factory()->create(['email' => 'ada@example.com']);

    $this->get(route('central.admin.users.edit', $ada))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/user/Edit')
            ->where('user.data.email', 'ada@example.com')
        );

    $this->put(route('central.admin.users.update', $ada), [
        'first_name' => 'Ada',
        'last_name' => 'King',
        'email' => 'ada@example.com',
    ])->assertSessionHasNoErrors()->assertRedirect(route('central.admin.users.index'));

    expect($ada->refresh()->last_name)->toBe('King');
});

test('another central user can be deleted, though central has no role tables', function () {
    $ada = User::factory()->create();

    $this->delete(route('central.admin.users.destroy', $ada))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('users.flash.deleted'));

    expect(User::query()->whereKey($ada->id)->exists())->toBeFalse();
});

test('you can\'t delete yourself from the users list', function () {
    User::factory()->create();

    $this->delete(route('central.admin.users.destroy', $this->me))
        ->assertSessionHasErrors(['user' => __('users.central.validation.delete_self')]);

    expect(User::query()->whereKey($this->me->id)->exists())->toBeTrue();
});

test('a central user can delete their own account from their profile', function () {
    User::factory()->create();

    $this->delete(route('central.admin.settings.profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect(User::query()->whereKey($this->me->id)->exists())->toBeFalse();
});

test('the last central user can\'t delete their account', function () {
    $this->delete(route('central.admin.settings.profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors(['password' => __('users.central.validation.last_user')]);

    expect(User::query()->whereKey($this->me->id)->exists())->toBeTrue();
});

test('the central menu links to the users list', function () {
    $this->get(route('central.admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('menus.main.2.1.label', __('navigation.users'))
            ->where('menus.main.2.1.to', route('central.admin.users.index', absolute: false))
        );
});

test('managing central users needs a logged in central user', function () {
    auth()->logout();

    $this->get(route('central.admin.users.index'))->assertRedirect(route('central.login'));
});
