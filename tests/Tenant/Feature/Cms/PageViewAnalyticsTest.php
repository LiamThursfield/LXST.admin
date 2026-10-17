<?php

use App\Enums\Cms\AnalyticsRange;
use App\Enums\TenantModule;
use App\Models\Cms\Page;
use App\Models\Cms\PageView;
use App\Services\Authorisation\Enums\CmsPermission;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15 12:00:00');
    tenant()->update(['modules' => [TenantModule::Cms]]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function viewOf(string $url, string $viewedAt, array $attributes = []): PageView
{
    return PageView::factory()->create(['url' => $url, 'viewed_at' => $viewedAt, ...$attributes]);
}

test('the dashboard shows page views, visitors, top pages and top referrers for the range', function () {
    $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewAnalytics]));
    $about = Page::factory()->create(['slug' => 'about', 'name' => 'About us']);

    // Two visitors on the 14th (one viewing twice), one on the 15th, and one outside the range
    viewOf('/about', '2026-07-14 09:00:00', ['visitor_hash' => 'a', 'referrer' => 'https://www.google.com/search?q=acme', 'viewable_type' => Page::class, 'viewable_id' => $about->id]);
    viewOf('/about', '2026-07-14 10:00:00', ['visitor_hash' => 'a', 'referrer' => 'https://tenant.test/']);
    viewOf('/gone', '2026-07-14 11:00:00', ['visitor_hash' => 'b', 'referrer' => 'https://www.google.com/']);
    viewOf('/about', '2026-07-15 08:00:00', ['visitor_hash' => 'a', 'referrer' => 'https://news.example.com:8080/item']);
    viewOf('/about', '2026-06-01 08:00:00', ['visitor_hash' => 'c']);

    $this->get(route('admin.dashboard', ['range' => '7d']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->has('analyticsRanges', count(AnalyticsRange::cases()))
            // Loaded after the page
            ->missing('analytics')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('analytics.range', '7d')
                ->where('analytics.totals', ['views' => 4, 'visitors' => 3])
                ->has('analytics.series', 7)
                ->where('analytics.series.5', ['date' => '2026-07-14', 'views' => 3, 'visitors' => 2])
                ->where('analytics.series.6', ['date' => '2026-07-15', 'views' => 1, 'visitors' => 1])
                ->where('analytics.top_pages', [
                    ['url' => '/about', 'name' => 'About us', 'views' => 3],
                    ['url' => '/gone', 'name' => null, 'views' => 1],
                ])
                // By host, without the tenant's own domain
                ->where('analytics.top_referrers', [
                    ['host' => 'www.google.com', 'views' => 2],
                    ['host' => 'news.example.com', 'views' => 1],
                ])
            )
        );
});

test('the 12 month range counts views per month', function () {
    $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewAnalytics]));
    viewOf('/about', '2026-07-01 08:00:00', ['visitor_hash' => 'a']);
    viewOf('/about', '2026-07-02 08:00:00', ['visitor_hash' => 'a']);
    viewOf('/about', '2025-08-03 08:00:00', ['visitor_hash' => 'b']);

    $this->get(route('admin.dashboard', ['range' => '12m']))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('analytics.series', 12)
                ->where('analytics.series.0', ['date' => '2025-08', 'views' => 1, 'visitors' => 1])
                // A visitor is counted once a day, so twice over the month
                ->where('analytics.series.11', ['date' => '2026-07', 'views' => 2, 'visitors' => 2])
            )
        );
});

test('the range defaults to 30 days, and must be one of the options', function () {
    $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewAnalytics]));

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('analytics.range', '30d')
                ->has('analytics.series', 30)
            )
        );

    $this->get(route('admin.dashboard', ['range' => '5y']))->assertSessionHasErrors('range');
});

test('page views need the analytics permission', function () {
    $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewContent]));

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('analytics', null)
            ->where('analyticsRanges', [])
        );
});

test('page views need the CMS module', function () {
    tenant()->update(['modules' => [TenantModule::Crm]]);
    $this->actingAs($this->createUserWithPermissions([CmsPermission::ViewAnalytics]));

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('analytics', null));
});
