<?php

use App\Data\Dj\GigRequestSettings;
use App\Enums\Dj\RequestsMode;
use App\Enums\TenantModule;
use App\Models\Dj\Gig;
use App\Models\Settings\DjSettings;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Dj\CurrentGig;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-01 12:00', 'UTC'));
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs]));
});

function at(string $time): CarbonImmutable
{
    return CarbonImmutable::parse($time, 'UTC');
}

describe('the current gig', function () {
    test('is the pinned gig, whatever the time', function () {
        Gig::factory()->between(at('2026-07-01 11:00'), at('2026-07-01 14:00'))->create();
        $pinned = Gig::factory()->between(at('2026-08-01 20:00'), at('2026-08-02 02:00'))->pinned()->create();

        expect(app(CurrentGig::class)->resolve()?->is($pinned))->toBeTrue();
    });

    test('is otherwise the gig on now, the one that started most recently if several are', function () {
        Gig::factory()->between(at('2026-07-01 10:00'), at('2026-07-01 16:00'))->create();
        $later = Gig::factory()->between(at('2026-07-01 11:30'), at('2026-07-01 13:00'))->create();
        Gig::factory()->between(at('2026-07-02 20:00'), at('2026-07-03 02:00'))->create();

        expect(app(CurrentGig::class)->resolve()?->is($later))->toBeTrue();
    });

    test('is otherwise the next upcoming gig, or none', function () {
        expect(app(CurrentGig::class)->resolve())->toBeNull();

        Gig::factory()->between(at('2026-06-01 20:00'), at('2026-06-02 02:00'))->create();
        $next = Gig::factory()->between(at('2026-07-05 20:00'), at('2026-07-06 02:00'))->create();
        Gig::factory()->between(at('2026-07-09 20:00'), at('2026-07-10 02:00'))->create();

        expect(app(CurrentGig::class)->resolve()?->is($next))->toBeTrue();
    });

    test('can only have one gig pinned at a time', function () {
        [$first, $second] = Gig::factory()->count(2)->create();

        $this->post(route('admin.dj.gigs.pin', $first))->assertRedirect();
        $this->post(route('admin.dj.gigs.pin', $second))->assertRedirect();

        expect($first->fresh()->is_pinned)->toBeFalse()->and($second->fresh()->is_pinned)->toBeTrue();

        $this->delete(route('admin.dj.gigs.unpin', $second))->assertSessionHas('success');
        expect($second->fresh()->is_pinned)->toBeFalse();
    });
});

describe('request settings', function () {
    test('follow the DJ settings defaults unless the gig overrides them', function () {
        $settings = app(DjSettings::class);
        $settings->max_requests_per_visitor = 5;
        $settings->allow_upvotes = true;
        $settings->save();

        $gig = Gig::factory()->create(['request_settings' => new GigRequestSettings(allow_upvotes: false)]);
        $effective = $gig->fresh()->requestSettings();

        expect($effective->max_requests_per_visitor)->toBe(5)
            ->and($effective->allow_upvotes)->toBeFalse();

        $settings->max_requests_per_visitor = 9;
        $settings->save();

        expect($gig->fresh()->requestSettings()->max_requests_per_visitor)->toBe(9);
    });

    test('open requests by mode, or around the gig when scheduled', function (RequestsMode $mode, string $now, bool $open) {
        $this->travelTo(at($now));
        $gig = Gig::factory()->between(at('2026-07-01 20:00'), at('2026-07-02 02:00'))->create([
            'request_settings' => new GigRequestSettings(requests_mode: $mode, requests_open_minutes_before: 60),
        ]);

        expect($gig->fresh()->requestsOpenAt(CarbonImmutable::now()))->toBe($open);
    })->with([
        'open' => [RequestsMode::Open, '2026-06-01 12:00', true],
        'closed during the gig' => [RequestsMode::Closed, '2026-07-01 21:00', false],
        'scheduled, too early' => [RequestsMode::Scheduled, '2026-07-01 18:59', false],
        'scheduled, an hour before' => [RequestsMode::Scheduled, '2026-07-01 19:00', true],
        'scheduled, during' => [RequestsMode::Scheduled, '2026-07-02 01:59', true],
        'scheduled, after' => [RequestsMode::Scheduled, '2026-07-02 02:00', false],
    ]);
});

test('the gigs page shows the current gig and upcoming, past or all gigs', function () {
    $past = Gig::factory()->between(at('2026-06-01 20:00'), at('2026-06-02 02:00'))->create(['name' => 'Last month']);
    $upcoming = Gig::factory()->between(at('2026-07-05 20:00'), at('2026-07-06 02:00'))->create(['name' => 'Saturday']);

    $this->get(route('admin.dj.gigs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/gig/Index')
            ->where('current.data.id', $upcoming->id)
            ->where('current.data.is_current', true)
            ->has('gigs.data', 1)
            ->where('gigs.data.0.name', 'Saturday')
            ->where('canCreate', true)
        );

    $this->get(route('admin.dj.gigs.index', ['filter' => ['when' => 'past']]))
        ->assertInertia(fn (Assert $page) => $page->has('gigs.data', 1)->where('gigs.data.0.id', $past->id));

    $this->get(route('admin.dj.gigs.index', ['filter' => ['when' => 'all', 'search' => 'satur']]))
        ->assertInertia(fn (Assert $page) => $page->has('gigs.data', 1)->where('gigs.data.0.id', $upcoming->id));
});

test('a gig is created with its times on its own clock, and only the request settings it overrides', function () {
    $this->post(route('admin.dj.gigs.store'), [
        'name' => 'Fabric',
        'venue' => 'Fabric',
        'location' => 'London',
        'timezone' => 'Europe/London',
        'starts_at' => '2026-07-10T22:00',
        'ends_at' => '2026-07-11T04:00',
        'notes' => null,
        'request_settings' => [
            'requests_mode' => 'open',
            'max_requests_per_visitor' => null,
            'allow_upvotes' => false,
        ],
    ])->assertSessionHasNoErrors();

    $gig = Gig::sole();

    expect($gig->starts_at->toIso8601String())->toBe('2026-07-10T21:00:00+00:00')
        ->and($gig->ends_at->toIso8601String())->toBe('2026-07-11T03:00:00+00:00')
        ->and($gig->request_settings->toArray())->toMatchArray([
            'requests_mode' => 'open',
            'allow_upvotes' => false,
            'max_requests_per_visitor' => null,
        ]);

    $this->get(route('admin.dj.gigs.edit', $gig))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/gig/Edit')
            ->where('gig.data.starts_at_local', '2026-07-10T22:00')
            ->where('gig.data.request_settings.requests_mode', 'open')
        );
});

test('a gig with no overrides follows every default', function () {
    $gig = Gig::factory()->create(['request_settings' => new GigRequestSettings(allow_upvotes: false)]);

    $this->put(route('admin.dj.gigs.update', $gig), [
        'name' => $gig->name,
        'timezone' => 'UTC',
        'starts_at' => '2026-07-10T22:00',
        'ends_at' => '2026-07-11T04:00',
        'request_settings' => ['requests_mode' => null, 'allow_upvotes' => null],
    ])->assertRedirect(route('admin.dj.gigs.edit', $gig));

    expect($gig->fresh()->request_settings)->toBeNull();
});

test('gig details are validated', function (array $data, string $error) {
    $this->post(route('admin.dj.gigs.store'), [
        'name' => 'Fabric',
        'timezone' => 'Europe/London',
        'starts_at' => '2026-07-10T22:00',
        'ends_at' => '2026-07-11T04:00',
        ...$data,
    ])->assertSessionHasErrors($error);
})->with([
    'no name' => [['name' => ''], 'name'],
    'ending before it starts' => [['ends_at' => '2026-07-10T21:00'], 'ends_at'],
    'an unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'a time in the wrong format' => [['starts_at' => '10/07/2026'], 'starts_at'],
    'a negative limit' => [['request_settings' => ['max_requests_per_visitor' => -1]], 'request_settings.max_requests_per_visitor'],
    'an unknown requests mode' => [['request_settings' => ['requests_mode' => 'sometimes']], 'request_settings.requests_mode'],
]);

test('overlapping gigs are listed on the edit page', function () {
    $gig = Gig::factory()->between(at('2026-07-10 20:00'), at('2026-07-11 02:00'))->create();
    Gig::factory()->between(at('2026-07-11 01:00'), at('2026-07-11 05:00'))->create(['name' => 'Afters']);
    Gig::factory()->between(at('2026-07-11 02:00'), at('2026-07-11 06:00'))->create(['name' => 'Back to back']);

    $this->get(route('admin.dj.gigs.edit', $gig))
        ->assertInertia(fn (Assert $page) => $page->has('overlaps', 1)->where('overlaps.0.name', 'Afters'));
});

test('a gig can be deleted', function () {
    $gig = Gig::factory()->create();

    $this->delete(route('admin.dj.gigs.destroy', $gig))->assertRedirect(route('admin.dj.gigs.index'));

    expect($gig->fresh())->toBeNull();
});

test('each gig has a request link and QR code once the request page address is set', function () {
    $gig = Gig::factory()->create(['name' => 'Fabric Night']);
    $settings = app(DjSettings::class);
    $settings->hosted_page_enabled = false;
    $settings->save();

    $this->get(route('admin.dj.gigs.qr-code', $gig))->assertNotFound();

    $settings->request_page_url = 'https://mydjsite.com/request?gig={gig}';
    $settings->save();

    expect($gig->fresh()->requestUrl())->toBe("https://mydjsite.com/request?gig={$gig->public_id}");

    $this->get(route('admin.dj.gigs.qr-code', $gig))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');

    $this->get(route('admin.dj.gigs.qr-code', [$gig, 'download' => 1]))
        ->assertHeader('Content-Disposition', 'attachment; filename="fabric-night-requests.svg"');
});

test('DJ settings can be changed', function () {
    $this->get(route('admin.dj.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/dj/settings/Edit')->where('canManage', true));

    $this->put(route('admin.dj.settings.update'), [
        'default_timezone' => 'Europe/Berlin',
        'request_page_url' => 'https://mydjsite.com/request',
        'requests_mode' => 'open',
        'requests_open_minutes_before' => 30,
        'max_requests_per_visitor' => 2,
        'max_pending_requests' => 40,
        'allow_custom_requests' => false,
        'show_public_list' => true,
        'allow_upvotes' => false,
        'show_pass_labels' => true,
        'hosted_page_enabled' => false,
    ])->assertRedirect(route('admin.dj.settings.edit'));

    $settings = app(DjSettings::class)->refresh();

    expect($settings->default_timezone)->toBe('Europe/Berlin')
        ->and($settings->requests_mode)->toBe(RequestsMode::Open)
        ->and($settings->max_pending_requests)->toBe(40)
        ->and($settings->allow_custom_requests)->toBeFalse()
        ->and($settings->show_pass_labels)->toBeTrue()
        ->and($settings->hosted_page_enabled)->toBeFalse();

    $this->get(route('admin.dj.gigs.create'))->assertInertia(fn (Assert $page) => $page->where('defaultTimezone', 'Europe/Berlin'));

    $this->put(route('admin.dj.settings.update'), ['default_timezone' => 'Nowhere', 'request_page_url' => 'not a url'])
        ->assertSessionHasErrors(['default_timezone', 'request_page_url', 'requests_mode']);
});

test('users who can only view gigs cannot change them or the settings', function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs]));
    $gig = Gig::factory()->create();

    $this->get(route('admin.dj.gigs.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
    $this->get(route('admin.dj.settings.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->get(route('admin.dj.gigs.create'))->assertForbidden();
    $this->post(route('admin.dj.gigs.store'), [])->assertForbidden();
    $this->get(route('admin.dj.gigs.edit', $gig))->assertForbidden();
    $this->put(route('admin.dj.gigs.update', $gig), [])->assertForbidden();
    $this->delete(route('admin.dj.gigs.destroy', $gig))->assertForbidden();
    $this->post(route('admin.dj.gigs.pin', $gig))->assertForbidden();
    $this->put(route('admin.dj.settings.update'), [])->assertForbidden();
});

test('gigs are not found without gig permissions or the DJ module', function () {
    $this->actingAs($this->createUserWithPermissions([]));
    $this->get(route('admin.dj.gigs.index'))->assertForbidden();
    $this->get(route('admin.dj.settings.edit'))->assertForbidden();

    tenant()->update(['modules' => [TenantModule::Cms]]);
    $this->get(route('admin.dj.gigs.index'))->assertNotFound();
});
