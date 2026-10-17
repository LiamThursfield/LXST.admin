<?php

namespace App\Http\Queries\Tenant\Admin\Cms;

use App\Enums\Cms\AnalyticsRange;
use App\Models\Cms\Page;
use App\Models\Cms\PageView;
use App\Models\Cms\Url;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\Domain;

/**
 * Page view stats for the dashboard: totals, views over time, and the top pages and referrers.
 * Dates are counted in UTC.
 *
 * Visitors are unique per day (the visitor hash changes daily, see VisitorPrivacy), so over a
 * longer range they're the daily counts added up.
 */
class PageViewAnalyticsQuery
{
    /** How many top pages and referrers to list */
    protected const int TOP_LIMIT = 10;

    /**
     * @return array{
     *     range: string,
     *     totals: array{views: int, visitors: int},
     *     series: list<array{date: string, views: int, visitors: int}>,
     *     top_pages: list<array{url: string, name: string|null, views: int}>,
     *     top_referrers: list<array{host: string, views: int}>,
     * }
     */
    public function handle(AnalyticsRange $range, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $start = $range->start($now);
        $series = $this->series($range, $start, $now);

        return [
            'range' => $range->value,
            'totals' => [
                'views' => (int) array_sum(array_column($series, 'views')),
                'visitors' => (int) array_sum(array_column($series, 'visitors')),
            ],
            'series' => $series,
            'top_pages' => $this->topPages($start),
            'top_referrers' => $this->topReferrers($start),
        ];
    }

    /**
     * @return Builder<PageView>
     */
    protected function views(CarbonImmutable $start): Builder
    {
        return PageView::query()->where('viewed_at', '>=', $start);
    }

    /**
     * Views and visitors per day (or month), including the ones with none
     *
     * @return list<array{date: string, views: int, visitors: int}>
     */
    protected function series(AnalyticsRange $range, CarbonImmutable $start, CarbonImmutable $now): array
    {
        /** @var Collection<string, object{views: int, visitors: int}> $days */
        $days = $this->views($start)
            ->selectRaw('DATE(viewed_at) as day, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as visitors')
            ->groupBy('day')
            ->toBase()
            ->get()
            ->keyBy(fn (object $row) => (string) $row->day);

        $format = $range->isMonthly() ? 'Y-m' : 'Y-m-d';
        /** @var array<string, array{views: int, visitors: int}> $buckets */
        $buckets = [];

        foreach (CarbonPeriod::create($start, $range->isMonthly() ? '1 month' : '1 day', $now) as $date) {
            $buckets[$date->format($format)] = ['views' => 0, 'visitors' => 0];
        }

        foreach ($days as $day => $row) {
            $bucket = CarbonImmutable::parse($day)->format($format);

            if (isset($buckets[$bucket])) {
                $buckets[$bucket]['views'] += (int) $row->views;
                $buckets[$bucket]['visitors'] += (int) $row->visitors;
            }
        }

        return array_values(collect($buckets)
            ->map(fn (array $counts, string $date) => ['date' => $date, ...$counts])
            ->all());
    }

    /**
     * The most viewed paths, named after the page now at that URL (null once it's gone)
     *
     * @return list<array{url: string, name: string|null, views: int}>
     */
    protected function topPages(CarbonImmutable $start): array
    {
        $rows = $this->views($start)
            ->select('url', DB::raw('COUNT(*) as views'))
            ->groupBy('url')
            ->orderByDesc('views')
            ->orderBy('url')
            ->limit(self::TOP_LIMIT)
            ->toBase()
            ->get();

        $names = Url::query()
            ->with('urlable')
            ->where('urlable_type', Page::class)
            ->whereIn('url_full', $rows->pluck('url'))
            ->get()
            ->mapWithKeys(fn (Url $url) => [$url->url_full => $url->urlable instanceof Page ? $url->urlable->name : null]);

        return array_values($rows
            ->map(fn (object $row) => [
                'url' => (string) $row->url,
                'name' => $names->get((string) $row->url),
                'views' => (int) $row->views,
            ])
            ->all());
    }

    /**
     * The sites that sent the most visitors, by host, leaving out the tenant's own domains
     *
     * @return list<array{host: string, views: int}>
     */
    protected function topReferrers(CarbonImmutable $start): array
    {
        // e.g. https://www.google.com/search?q=x → www.google.com
        $host = "LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '://', -1), '/', 1), '?', 1), ':', 1))";

        $ownDomains = tenant()?->domains->map(fn (Domain $domain) => (string) $domain->getAttribute('domain'))->all() ?? [];

        return array_values($this->views($start)
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->selectRaw("{$host} as host, COUNT(*) as views")
            ->groupBy('host')
            ->when($ownDomains !== [], fn (Builder $query) => $query->havingRaw(
                'host NOT IN ('.implode(',', array_fill(0, count($ownDomains), '?')).')',
                $ownDomains,
            ))
            ->orderByDesc('views')
            ->orderBy('host')
            ->limit(self::TOP_LIMIT)
            ->toBase()
            ->get()
            ->map(fn (object $row) => ['host' => (string) $row->host, 'views' => (int) $row->views])
            ->all());
    }
}
