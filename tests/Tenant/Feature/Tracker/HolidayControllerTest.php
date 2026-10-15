<?php

use App\Models\Tracker\Holiday;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected when accessing the holiday index', function () {
    $this->get(route('admin.tracker.holidays.index'))->assertRedirect(route('login'));
});

test('users without the manage-holidays permission cannot see the holiday index', function () {
    $this->actingAs($this->createUserWithPermissions([]));

    $this->get(route('admin.tracker.holidays.index'))->assertForbidden();
});

test('users can see the holiday index with the manage-holidays permission', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ManageHolidays]));

    $this->get(route('admin.tracker.holidays.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tracker/holiday/Index')
            ->has('holidays.data')
        );
});

test('the holiday index only shows the users own holidays', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageHolidays]);
    $this->actingAs($user);

    Holiday::factory()->forUser($user)->create(['label' => 'Mine']);
    Holiday::factory()->forUser(User::factory()->create())->create(['label' => 'Theirs']);

    $this->get(route('admin.tracker.holidays.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('holidays.data', 1)
            ->where('holidays.data.0.label', 'Mine')
        );
});

test('the holiday index can be filtered by label', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageHolidays]);
    $this->actingAs($user);

    Holiday::factory()->forUser($user)->create(['label' => 'Italy trip']);
    Holiday::factory()->forUser($user)->create(['label' => 'Ski week']);

    $this->get(route('admin.tracker.holidays.index', ['filter[label]' => 'Ital']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('holidays.data', 1)
            ->where('holidays.data.0.label', 'Italy trip')
        );
});

test('users can create a holiday', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageHolidays]);
    $this->actingAs($user);

    $response = $this->post(route('admin.tracker.holidays.store'), [
        'label' => 'Italy trip',
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-09-14',
    ]);

    $response->assertRedirect(route('admin.tracker.holidays.index'));
    $response->assertSessionHas('success', 'Holiday created successfully.');

    $holiday = Holiday::where('label', 'Italy trip')->first();
    expect($holiday)->not->toBeNull()
        ->and($holiday->user_id)->toBe($user->id)
        ->and($holiday->starts_on->toDateString())->toBe('2026-09-01');
});

test('users without the manage permission cannot create a holiday', function () {
    $this->actingAs($this->createUserWithPermissions([]));

    $this->post(route('admin.tracker.holidays.store'), [
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-09-14',
    ])->assertForbidden();
});

test('creating a holiday rejects an end date before the start date', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ManageHolidays]));

    $this->post(route('admin.tracker.holidays.store'), [
        'starts_on' => '2026-09-14',
        'ends_on' => '2026-09-01',
    ])->assertSessionHasErrors(['ends_on']);
});

test('users can update their own holiday', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageHolidays]);
    $this->actingAs($user);

    $holiday = Holiday::factory()->forUser($user)->create(['label' => 'Old']);

    $response = $this->put(route('admin.tracker.holidays.update', $holiday), [
        'label' => 'New',
        'starts_on' => '2026-10-01',
        'ends_on' => '2026-10-05',
    ]);

    $response->assertRedirect(route('admin.tracker.holidays.index'));
    expect($holiday->fresh()->label)->toBe('New');
});

test('users cannot update another users holiday', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ManageHolidays]));

    $holiday = Holiday::factory()->forUser(User::factory()->create())->create();

    $this->put(route('admin.tracker.holidays.update', $holiday), [
        'label' => 'Hijacked',
        'starts_on' => '2026-10-01',
        'ends_on' => '2026-10-05',
    ])->assertForbidden();
});

test('users can delete their own holiday', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageHolidays]);
    $this->actingAs($user);

    $holiday = Holiday::factory()->forUser($user)->create();

    $this->delete(route('admin.tracker.holidays.destroy', $holiday))->assertRedirect();
    expect(Holiday::find($holiday->id))->toBeNull();
});

test('users cannot delete another users holiday', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ManageHolidays]));

    $holiday = Holiday::factory()->forUser(User::factory()->create())->create();

    $this->delete(route('admin.tracker.holidays.destroy', $holiday))->assertForbidden();
    expect(Holiday::find($holiday->id))->not->toBeNull();
});
