<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page when trying to access user index', function () {
    $response = $this->get(route('admin.users.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the user index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('admin.users.index'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user/Index')
        ->has('users.data')
    );
});

test('user index can be filtered by first name', function () {
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
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
