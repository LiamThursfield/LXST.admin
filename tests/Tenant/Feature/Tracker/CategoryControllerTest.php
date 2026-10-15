<?php

use App\Models\Tracker\Category;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page when trying to access the category index', function () {
    $response = $this->get(route('admin.tracker.categories.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users cannot visit the category index without the view permission', function () {
    $user = $this->createUserWithPermissions([]);
    $this->actingAs($user);

    $response = $this->get(route('admin.tracker.categories.index'));

    $response->assertForbidden();
});

test('authenticated users can visit the category index with the view permission', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ViewCategories]);
    $this->actingAs($user);

    $response = $this->get(route('admin.tracker.categories.index'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/tracker/category/Index')
        ->has('categories.data')
    );
});

test('the category index only shows public categories and the users own categories', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ViewCategories]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();

    $ownPrivate = Category::factory()->forUser($user)->create(['name' => 'My Private']);
    $publicShared = Category::factory()->public()->create(['name' => 'Shared Public']);
    $global = Category::factory()->global()->create(['name' => 'Global']);
    $otherPrivate = Category::factory()->forUser($otherUser)->create(['name' => 'Their Private']);

    $response = $this->get(route('admin.tracker.categories.index'));

    $response->assertOk();

    $response->assertInertia(function (Assert $page) use ($ownPrivate, $publicShared, $global, $otherPrivate) {
        $names = collect($page->toArray()['props']['categories']['data'])->pluck('name');

        expect($names)->toContain($ownPrivate->name)
            ->and($names)->toContain($publicShared->name)
            ->and($names)->toContain($global->name)
            ->and($names)->not->toContain($otherPrivate->name);
    });
});

test('the category index can be filtered by name', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ViewCategories]);
    $this->actingAs($user);

    Category::factory()->forUser($user)->create(['name' => 'Health']);
    Category::factory()->forUser($user)->create(['name' => 'Productivity']);

    $response = $this->get(route('admin.tracker.categories.index', ['filter[name]' => 'Heal']));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/tracker/category/Index')
        ->has('categories.data', 1)
        ->where('categories.data.0.name', 'Health')
    );
});

test('users can create a private category', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $response = $this->post(route('admin.tracker.categories.store'), [
        'name' => 'Health',
        'colour' => '#4f46e5',
        'icon' => 'i-lucide-heart',
        'is_public' => false,
    ]);

    $response->assertRedirect(route('admin.tracker.categories.index'));
    $response->assertSessionHas('success', 'Category created successfully.');

    $category = Category::where('name', 'Health')->first();
    expect($category)->not->toBeNull()
        ->and($category->user_id)->toBe($user->id)
        ->and($category->is_public)->toBeFalse();
});

test('users without the public permission cannot create a public category', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $response = $this->post(route('admin.tracker.categories.store'), [
        'name' => 'Shared',
        'is_public' => true,
    ]);

    $response->assertForbidden();
    expect(Category::where('name', 'Shared')->exists())->toBeFalse();
});

test('users with the public permission can create a public category', function () {
    $user = $this->createUserWithPermissions([
        TrackerPermission::ManageCategories,
        TrackerPermission::ManagePublicCategories,
    ]);
    $this->actingAs($user);

    $response = $this->post(route('admin.tracker.categories.store'), [
        'name' => 'Shared',
        'is_public' => true,
    ]);

    $response->assertRedirect(route('admin.tracker.categories.index'));

    $category = Category::where('name', 'Shared')->first();
    expect($category)->not->toBeNull()
        ->and($category->is_public)->toBeTrue();
});

test('private category names are unique per user but not across users', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();
    // Another user already has a private "Health" — must NOT block this user.
    Category::factory()->forUser($otherUser)->create(['name' => 'Health']);
    // This user already has their own "Health" — the duplicate below must be blocked.
    Category::factory()->forUser($user)->create(['name' => 'Health']);

    $response = $this->post(route('admin.tracker.categories.store'), [
        'name' => 'Health',
        'is_public' => false,
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('a user can have a private category with the same name as a public one', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    Category::factory()->global()->create(['name' => 'Health']);

    $response = $this->post(route('admin.tracker.categories.store'), [
        'name' => 'Health',
        'is_public' => false,
    ]);

    $response->assertRedirect(route('admin.tracker.categories.index'));
    expect(Category::where('name', 'Health')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('users can update their own private category', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $category = Category::factory()->forUser($user)->create(['name' => 'Old']);

    $response = $this->put(route('admin.tracker.categories.update', $category), [
        'name' => 'New',
        'is_public' => false,
    ]);

    $response->assertRedirect(route('admin.tracker.categories.index'));

    $category->refresh();
    expect($category->name)->toBe('New');
});

test('users cannot update another users private category', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();
    $category = Category::factory()->forUser($otherUser)->create();

    $response = $this->put(route('admin.tracker.categories.update', $category), [
        'name' => 'Hijacked',
        'is_public' => false,
    ]);

    $response->assertForbidden();
});

test('users without the public permission cannot update a public category', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $category = Category::factory()->public()->create(['name' => 'Public']);

    $response = $this->put(route('admin.tracker.categories.update', $category), [
        'name' => 'Changed',
        'is_public' => true,
    ]);

    $response->assertForbidden();
});

test('users can delete their own private category', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $category = Category::factory()->forUser($user)->create();

    $response = $this->delete(route('admin.tracker.categories.destroy', $category));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Category deleted successfully.');
    expect(Category::find($category->id))->toBeNull();
});

test('users cannot delete another users private category', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $otherUser = User::factory()->create();
    $category = Category::factory()->forUser($otherUser)->create();

    $response = $this->delete(route('admin.tracker.categories.destroy', $category));

    $response->assertForbidden();
    expect(Category::find($category->id))->not->toBeNull();
});
