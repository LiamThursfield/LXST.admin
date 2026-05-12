<?php

use App\Models\User;
use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Enums\Role;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page when trying to access user index', function () {
    $response = $this->get(route('admin.users.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users cannot visit the user index if they do not have the required permission', function () {
    $user = $this->createUserWithPermissions([]);
    $this->actingAs($user);

    $response = $this->get(route('admin.users.index'));

    $response->assertForbidden();
});

test('authenticated users can visit the user index if they have the required permission', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers]);
    $this->actingAs($user);

    $response = $this->get(route('admin.users.index'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user/Index')
        ->has('users.data')
    );
});

test('user index can be filtered by first name', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers]);
    $this->actingAs($user);

    User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $response = $this->get(route('admin.users.index', ['filter[first_name]' => 'Joh']));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user/Index')
        ->has('users.data', 1)
        ->where('users.data.0.first_name', 'John')
    );
});

test('user index can be filtered by last name', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers]);
    $this->actingAs($user);

    User::factory()->create(['first_name' => 'John', 'last_name' => 'Smith']);
    User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $response = $this->get(route('admin.users.index', ['filter[last_name]' => 'Smi']));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user/Index')
        ->has('users.data', 1)
        ->where('users.data.0.last_name', 'Smith')
    );
});

test('user index can be filtered by email', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers]);
    $this->actingAs($user);

    User::factory()->create(['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john@example.com']);
    User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com']);

    $response = $this->get(route('admin.users.index', ['filter[email]' => 'john@e']));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user/Index')
        ->has('users.data', 1)
        ->where('users.data.0.email', 'john@example.com')
    );
});

test('user index can be filtered by role', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers]);
    $this->actingAs($user);

    $admin = User::factory()->create(['first_name' => 'Admin']);
    $admin->assignRole('Admin');

    $editor = User::factory()->create(['first_name' => 'Viewer']);
    $editor->assignRole('Viewer');

    $response = $this->get(route('admin.users.index', ['filter[role]' => ['Admin']]));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user/Index')
        ->has('users.data', 1)
        ->where('users.data.0.first_name', 'Admin')
    );
});

test('user index can be sorted by first name', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers], ['first_name' => 'Mister']);
    $this->actingAs($user);

    User::factory()->create(['first_name' => 'Zebra']);
    User::factory()->create(['first_name' => 'Apple']);

    $response = $this->get(route('admin.users.index', ['sort' => 'first_name']));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user/Index')
        ->where('users.data.0.first_name', 'Apple')
        ->where('users.data.2.first_name', 'Zebra')
    );
});

test('authenticated users cannot view a user if they do not have the required permission', function () {
    $user = $this->createUserWithPermissions([]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();

    $response = $this->get(route('admin.users.show', $otherUser));

    $response->assertForbidden();
});

test('authenticated users can view a user if they have the required permission', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers]);
    $this->actingAs($user);

    $otherUser = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $response = $this->get(route('admin.users.show', $otherUser));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user/Show')
        ->where('user.data.first_name', 'Jane')
        ->where('user.data.last_name', 'Doe')
        ->where('user.data.email', 'jane@example.com')
    );
});

test('authenticated users cannot edit a user if they do not have the required permission', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();

    $response = $this->get(route('admin.users.edit', $otherUser));

    $response->assertForbidden();
});

test('authenticated users can edit a user if they have the required permission', function () {
    $user = $this->createUserWithPermissions([CorePermission::ManageUsers]);
    $this->actingAs($user);

    $otherUser = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $response = $this->get(route('admin.users.edit', $otherUser));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user/Edit')
        ->where('user.data.first_name', 'Jane')
        ->where('user.data.last_name', 'Doe')
        ->where('user.data.email', 'jane@example.com')
    );
});

test('authenticated users cannot update a user if they do not have the required permission', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();

    $response = $this->put(route('admin.users.update', $otherUser), [
        'first_name' => 'Updated',
        'last_name' => 'User',
        'email' => 'updated@example.com',
    ]);

    $response->assertForbidden();
});

test('authenticated users can update a user if they have the required permission', function () {
    $user = $this->createUserWithPermissions([CorePermission::ManageUsers]);
    $this->actingAs($user);

    $otherUser = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $response = $this->put(route('admin.users.update', $otherUser), [
        'first_name' => 'Jane Updated',
        'last_name' => 'Doe Updated',
        'email' => 'jane.updated@example.com',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success', 'User updated successfully.');

    $otherUser->refresh();
    expect($otherUser->first_name)->toBe('Jane Updated');
    expect($otherUser->last_name)->toBe('Doe Updated');
    expect($otherUser->email)->toBe('jane.updated@example.com');
});

test('updating a user can sync roles', function () {
    $user = $this->createUserWithPermissions([CorePermission::ManageUsers]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();
    $otherUser->assignRole(Role::Viewer->value);

    expect($otherUser->hasRole(Role::Viewer->value))->toBeTrue();
    expect($otherUser->hasRole(Role::Admin->value))->toBeFalse();

    $response = $this->put(route('admin.users.update', $otherUser), [
        'first_name' => $otherUser->first_name,
        'last_name' => $otherUser->last_name,
        'email' => $otherUser->email,
        'roles' => [Role::Admin->value],
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $otherUser->refresh();
    expect($otherUser->hasRole(Role::Admin->value))->toBeTrue();
    expect($otherUser->hasRole(Role::Viewer->value))->toBeFalse();
});

test('updating a user without roles does not change existing roles', function () {
    $user = $this->createUserWithPermissions([CorePermission::ManageUsers]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();
    $otherUser->assignRole(Role::Viewer->value);

    $response = $this->put(route('admin.users.update', $otherUser), [
        'first_name' => 'Updated',
        'last_name' => 'User',
        'email' => 'updated@example.com',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $otherUser->refresh();
    expect($otherUser->hasRole(Role::Viewer->value))->toBeTrue();
});

test('updating a user validates unique email', function () {
    $user = $this->createUserWithPermissions([CorePermission::ManageUsers]);
    $this->actingAs($user);

    User::factory()->create(['email' => 'existing@example.com']);
    $otherUser = User::factory()->create(['email' => 'other@example.com']);

    $response = $this->put(route('admin.users.update', $otherUser), [
        'first_name' => 'Updated',
        'last_name' => 'User',
        'email' => 'existing@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('authenticated users cannot delete a user if they do not have the required permission', function () {
    $user = $this->createUserWithPermissions([CorePermission::ViewUsers]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();

    $response = $this->delete(route('admin.users.destroy', $otherUser));

    $response->assertForbidden();
});

test('authenticated users can delete a user if they have the required permission', function () {
    $user = $this->createUserWithPermissions([CorePermission::ManageUsers]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();

    $response = $this->delete(route('admin.users.destroy', $otherUser));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'User deleted successfully.');

    expect(User::find($otherUser->id))->toBeNull();
});
