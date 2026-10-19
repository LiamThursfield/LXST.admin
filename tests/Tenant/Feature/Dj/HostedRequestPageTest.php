<?php

use App\Enums\Api\ContentScope;
use App\Enums\TenantModule;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use App\Models\Settings\DjSettings;
use App\Services\Cms\Api\ContentVersion;
use Inertia\Testing\AssertableInertia as Assert;

test('the hosted request page is public, for the current gig or one by its public ID, and not indexed', function () {
    $gig = Gig::factory()->create();

    $this->get(route('dj.request'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/dj/Request')
            ->where('gigId', null)
            ->where('site.name', fn ($name) => is_string($name))
        );

    $this->get(route('dj.request.gig', $gig->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('gigId', $gig->public_id));

    $this->get('/request/'.$gig->id)->assertNotFound();
});

test('guests open a setlist by its public ID, but not a gig\'s requests list', function () {
    $setlist = SongList::factory()->create();
    $requests = SongList::factory()->requests()->forGig()->create();

    $this->get(route('dj.setlist', $setlist->public_id))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/dj/Setlist')
            ->where('setlistId', $setlist->public_id)
        );

    $this->get(route('dj.setlist', $requests->public_id))->assertNotFound();
    $this->get('/setlist/'.$setlist->id)->assertNotFound();
});

test('song lists get a public ID, which the guest API uses', function () {
    $setlist = SongList::factory()->create();
    GuestPass::factory()->for($setlist)->create(['password' => 'maple-river-42']);

    expect($setlist->public_id)->toBeString()->toHaveLength(26);

    $this->postJson('/api/v1/dj/song-lists/'.$setlist->public_id.'/guest-token', ['password' => 'maple-river-42'])
        ->assertCreated()
        ->assertJsonPath('data.list.id', $setlist->public_id);

    $this->postJson('/api/v1/dj/song-lists/'.$setlist->id.'/guest-token', ['password' => 'maple-river-42'])->assertNotFound();
});

test('gig links use the hosted page while no request page address is set, and nothing when it\'s off', function () {
    $gig = Gig::factory()->create();
    $settings = app(DjSettings::class);

    expect($gig->requestUrl())->toBe(route('dj.request.gig', $gig->public_id));

    $settings->request_page_url = 'https://mydjsite.com/request?gig={gig}';
    $settings->save();

    expect($gig->fresh()->requestUrl())->toBe("https://mydjsite.com/request?gig={$gig->public_id}");

    $settings->request_page_url = null;
    $settings->hosted_page_enabled = false;
    $settings->save();

    expect($gig->fresh()->requestUrl())->toBeNull();
});

test('the gig details give the requests list for guest passes', function () {
    $gig = Gig::factory()->create();

    $this->getJson(route('api.v1.dj.gigs.show', $gig->public_id))->assertJsonPath('data.requests.list_id', null);

    $list = SongList::factory()->requests()->forGig($gig)->create();
    GuestPass::factory()->for($list)->create();

    // As adding a pass in the admin would
    ContentVersion::for(ContentScope::Dj)->bump();

    $this->getJson(route('api.v1.dj.gigs.show', $gig->public_id))
        ->assertJsonPath('data.requests.list_id', $list->public_id)
        ->assertJsonPath('data.requests.requires_pass', true);
});

test('the hosted pages are off with the setting, or without the DJ module', function () {
    $setlist = SongList::factory()->create();
    $settings = app(DjSettings::class);
    $settings->hosted_page_enabled = false;
    $settings->save();

    $this->get(route('dj.request'))->assertNotFound();
    $this->get(route('dj.setlist', $setlist->public_id))->assertNotFound();

    $settings->hosted_page_enabled = true;
    $settings->save();
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('dj.request'))->assertNotFound();
});
