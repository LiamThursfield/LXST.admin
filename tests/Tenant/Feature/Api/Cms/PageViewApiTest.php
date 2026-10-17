<?php

use App\Enums\TenantModule;
use App\Jobs\Cms\RecordPageView;
use App\Models\Cms\Page;
use App\Models\Cms\PageView;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Cms\Api\VisitorPrivacy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';

beforeEach(function () {
    config(['api.rate_limit_per_minute' => 10000]);
});

/**
 * @param  array<string, mixed>  $data
 */
function postView(array $data): TestResponse
{
    return test()->postJson(route('api.v1.cms.page-views.store'), $data);
}

test('a view of a published page is recorded, with the visitor\'s IP truncated and hashed', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    $page = Page::factory()->create(['slug' => 'about']);

    postView([
        'url' => 'https://acme.test/about/?ref=nav',
        'referrer' => 'https://google.com/',
        'user_agent' => BROWSER,
        'ip_address' => '203.0.113.42',
        'session_id' => 'abc123',
        'source' => ['utm_source' => 'newsletter', 'utm_medium' => 'email'],
        'additional_data' => ['locale' => 'en-GB'],
    ])->assertStatus(202);

    $view = PageView::query()->sole();

    expect($view->viewable?->is($page))->toBeTrue()
        ->and($view->url)->toBe('/about')
        ->and($view->referrer)->toBe('https://google.com/')
        ->and($view->user_agent)->toBe(BROWSER)
        ->and($view->ip_address)->toBe('203.0.113.0')
        ->and($view->visitor_hash)->toHaveLength(64)
        ->and($view->session_id)->toBe('abc123')
        ->and($view->source)->toEqual(['utm_source' => 'newsletter', 'utm_medium' => 'email'])
        ->and($view->additional_data)->toBe(['locale' => 'en-GB'])
        ->and($view->viewed_at->toDateTimeString())->toBe('2026-07-01 12:00:00')
        ->and($page->views()->count())->toBe(1);
});

test('views are queued, without the full IP address', function () {
    Queue::fake();

    postView(['url' => '/about', 'user_agent' => BROWSER, 'ip_address' => '203.0.113.42'])->assertStatus(202);

    Queue::assertPushed(RecordPageView::class, fn (RecordPageView $job) => ! str_contains(serialize($job->view), '203.0.113.42'));
});

test('the visitor hash counts the same visitor once a day, without following them across days', function () {
    $privacy = app(VisitorPrivacy::class);
    $monday = Carbon::parse('2026-07-06');

    expect($privacy->visitorHash('203.0.113.42', BROWSER, $monday))
        ->toBe($privacy->visitorHash('203.0.113.42', BROWSER, $monday->copy()->setTime(18, 0)))
        ->not->toBe($privacy->visitorHash('203.0.113.42', BROWSER, $monday->copy()->addDay()))
        ->not->toBe($privacy->visitorHash('203.0.113.43', BROWSER, $monday));
});

test('IPv6 addresses keep only their network', function () {
    expect(app(VisitorPrivacy::class)->truncateIp('2001:db8:85a3:1234:8a2e:370:7334:1'))->toBe('2001:db8:85a3::');
});

test('the request\'s own user agent and IP are used when the site doesn\'t send them', function () {
    Page::factory()->create(['slug' => 'about']);

    $this->withHeader('User-Agent', BROWSER)->postJson(route('api.v1.cms.page-views.store'), ['url' => '/about'])
        ->assertStatus(202);

    expect(PageView::query()->sole())
        ->user_agent->toBe(BROWSER)
        ->ip_address->toBe('127.0.0.0');
});

test('bots, and views of pages that aren\'t published, aren\'t recorded', function () {
    $draft = Page::factory()->create(['slug' => 'draft']);
    $draft->url->update(['is_enabled' => false]);
    Page::factory()->create(['slug' => 'about']);

    postView(['url' => '/about', 'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertStatus(202);
    postView(['url' => '/about', 'user_agent' => 'node-fetch/3.0'])->assertStatus(202);
    postView(['url' => '/draft', 'user_agent' => BROWSER])->assertStatus(202);
    postView(['url' => '/nope', 'user_agent' => BROWSER])->assertStatus(202);

    expect(PageView::query()->count())->toBe(0);
});

test('a view needs a URL, and anything sent must be valid', function () {
    postView([
        'user_agent' => BROWSER,
        'ip_address' => 'not-an-ip',
        'source' => ['utm_source' => 'x', 'gclid' => 'y'],
        'additional_data' => ['nested' => ['no']],
    ])->assertJsonValidationErrors(['url', 'ip_address', 'source', 'additional_data.nested']);
});

test('deleting a page keeps its views, unlinked', function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    $page = Page::factory()->create(['slug' => 'about']);
    PageView::factory()->for($page, 'viewable')->create(['url' => '/about']);

    $this->delete(route('admin.cms.pages.destroy', $page))->assertSessionHasNoErrors();

    expect(PageView::query()->sole())
        ->viewable_id->toBeNull()
        ->url->toBe('/about');
});

test('page views are rate limited per visitor', function () {
    config(['api.page_views.rate_limit_per_minute' => 2]);
    // Fresh addresses, as the limiter's hits live in the test cache (Redis), which outlives each test
    [$visitor, $otherVisitor] = [fake()->unique()->ipv4(), fake()->unique()->ipv4()];

    postView(['url' => '/about', 'user_agent' => BROWSER, 'ip_address' => $visitor])->assertStatus(202);
    postView(['url' => '/about', 'user_agent' => BROWSER, 'ip_address' => $visitor])->assertStatus(202);
    postView(['url' => '/about', 'user_agent' => BROWSER, 'ip_address' => $visitor])->assertTooManyRequests();
    postView(['url' => '/about', 'user_agent' => BROWSER, 'ip_address' => $otherVisitor])->assertStatus(202);
});

test('the page view API needs the CMS module', function () {
    tenant()->update(['modules' => [TenantModule::Crm]]);

    postView(['url' => '/about', 'user_agent' => BROWSER])->assertNotFound();
});
