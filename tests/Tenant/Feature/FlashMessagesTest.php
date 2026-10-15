<?php

use App\Models\Tracker\Category;
use App\Services\Authorisation\Enums\TrackerPermission;
use Inertia\Testing\AssertableInertia as Assert;

test('a flashed success message is shared as inertia flash data after a redirect', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ViewCategories, TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $category = Category::factory()->forUser($user)->create();

    $this->from(route('admin.tracker.categories.index'))
        ->followingRedirects()
        ->delete(route('admin.tracker.categories.destroy', $category))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tracker/category/Index')
            ->hasFlash('success', 'Category deleted successfully.')
        );
});

test('a flashed message is only shared once', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ViewCategories, TrackerPermission::ManageCategories]);
    $this->actingAs($user);

    $category = Category::factory()->forUser($user)->create();

    $this->from(route('admin.tracker.categories.index'))
        ->followingRedirects()
        ->delete(route('admin.tracker.categories.destroy', $category));

    $this->get(route('admin.tracker.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missingFlash('success'));
});
