<?php

use App\Enums\Bookings\TimeOffStatus;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\TimeOff;
use App\Services\Authorisation\Enums\BookingsPermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-02 12:00:00');
});

function timeOffData(int $resourceId, array $overrides = []): array
{
    return [
        'resource_id' => $resourceId,
        'type' => 'holiday',
        'starts_on' => '2026-11-10',
        'ends_on' => '2026-11-12',
        'reason' => 'Family wedding',
        ...$overrides,
    ];
}

describe('asking for time off', function () {
    beforeEach(function () {
        $this->user = $this->createUserWithPermissions([BookingsPermission::RequestTimeOff]);
        $this->actingAs($this->user);
        $this->mine = BookableResource::factory()->forUser($this->user)->create();
    });

    test('someone can ask for their own, which waits for approval', function () {
        $this->post(route('admin.bookings.time-off.store'), timeOffData($this->mine->id))
            ->assertRedirect(route('admin.bookings.time-off.index'));

        $timeOff = TimeOff::query()->sole();

        expect($timeOff->status)->toBe(TimeOffStatus::Requested)
            ->and($timeOff->requested_by_id)->toBe($this->user->id)
            ->and($timeOff->reviewed_at)->toBeNull();
    });

    test('they cannot ask for someone else\'s, or for past dates', function () {
        $other = BookableResource::factory()->create();

        $this->post(route('admin.bookings.time-off.store'), timeOffData($other->id))->assertSessionHasErrors('resource_id');
        $this->post(route('admin.bookings.time-off.store'), timeOffData($this->mine->id, ['starts_on' => '2026-10-01']))->assertSessionHasErrors('starts_on');

        expect(TimeOff::query()->count())->toBe(0);
    });

    test('they see only their own, and can only ask for themselves', function () {
        TimeOff::factory()->for($this->mine, 'resource')->create(['reason' => 'Mine']);
        TimeOff::factory()->create(['reason' => 'Private to someone else']);

        $this->get(route('admin.bookings.time-off.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('timeOff.data', 1)->where('timeOff.data.0.reason', 'Mine'))
            ->assertDontSee('Private to someone else');

        $this->get(route('admin.bookings.time-off.create'))
            ->assertInertia(fn (Assert $page) => $page->has('resources', 1)->where('resources.0.value', $this->mine->id)->where('canManage', false));
    });

    test('they cannot approve, but can withdraw only while it is waiting', function () {
        $waiting = TimeOff::factory()->for($this->mine, 'resource')->create(['requested_by_id' => $this->user->id]);
        $approved = TimeOff::factory()->for($this->mine, 'resource')->approved()->create([
            'requested_by_id' => $this->user->id,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-02',
        ]);

        $this->post(route('admin.bookings.time-off.approve', $waiting))->assertForbidden();
        $this->delete(route('admin.bookings.time-off.destroy', $approved))->assertForbidden();
        $this->delete(route('admin.bookings.time-off.destroy', $waiting))->assertRedirect();

        expect(TimeOff::query()->pluck('id')->all())->toBe([$approved->id]);
    });

    test('someone without a linked resource has nothing to ask for', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::RequestTimeOff]));

        $this->get(route('admin.bookings.time-off.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
    });
});

describe('managing time off', function () {
    beforeEach(function () {
        $this->manager = $this->createUserWithPermissions([BookingsPermission::ManageTimeOff]);
        $this->actingAs($this->manager);
    });

    test('recording it for anyone needs no approval, and can be in the past', function () {
        $van = BookableResource::factory()->vehicle()->create();

        $this->post(route('admin.bookings.time-off.store'), timeOffData($van->id, ['type' => 'maintenance', 'starts_on' => '2026-10-01', 'ends_on' => null]))
            ->assertSessionHasNoErrors();

        $timeOff = TimeOff::query()->sole();

        expect($timeOff->status)->toBe(TimeOffStatus::Approved)
            ->and($timeOff->reviewed_by_id)->toBe($this->manager->id)
            ->and($timeOff->ends_on->toDateString())->toBe('2026-10-01');
    });

    test('a request can be approved or declined, once', function (string $route, TimeOffStatus $status) {
        $timeOff = TimeOff::factory()->create();

        $this->post(route("admin.bookings.time-off.{$route}", $timeOff))->assertRedirect();

        expect($timeOff->refresh()->status)->toBe($status)
            ->and($timeOff->reviewed_by_id)->toBe($this->manager->id);

        $this->post(route('admin.bookings.time-off.approve', $timeOff))->assertForbidden();
    })->with([
        'approve' => ['approve', TimeOffStatus::Approved],
        'decline' => ['decline', TimeOffStatus::Declined],
    ]);

    test('the same days cannot be added twice, unless the earlier was declined', function () {
        $person = BookableResource::factory()->create();
        TimeOff::factory()->for($person, 'resource')->create(['starts_on' => '2026-11-11', 'ends_on' => '2026-11-11']);

        $this->post(route('admin.bookings.time-off.store'), timeOffData($person->id))->assertSessionHasErrors('starts_on');

        TimeOff::query()->update(['status' => TimeOffStatus::Declined]);

        $this->post(route('admin.bookings.time-off.store'), timeOffData($person->id))->assertSessionHasNoErrors();
    });

    test('the end cannot be before the start', function () {
        $person = BookableResource::factory()->create();

        $this->post(route('admin.bookings.time-off.store'), timeOffData($person->id, ['ends_on' => '2026-11-01']))
            ->assertSessionHasErrors('ends_on');
    });
});

test('without any time off permission, the page is forbidden', function () {
    $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewResources]));

    $this->get(route('admin.bookings.time-off.index'))->assertForbidden();
    $this->post(route('admin.bookings.time-off.store'), [])->assertForbidden();
});
