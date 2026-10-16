<?php

namespace App\Http\Queries\Tenant\Admin\Cms;

use App\Enums\Cms\PublishStatus;
use App\Http\Requests\Tenant\Admin\Cms\PageIndexRequest;
use App\Models\Cms\Page;
use App\Models\Cms\Url;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PageIndexQuery
{
    /**
     * Pages in URL order, so each page's children follow it (shown indented, as a tree)
     *
     * @return LengthAwarePaginator<int, Page>
     */
    public function handle(PageIndexRequest $request): LengthAwarePaginator
    {
        $query = Page::query()
            ->select('cms_pages.*')
            ->leftJoin('cms_urls', fn ($join) => $join->on('cms_urls.urlable_id', '=', 'cms_pages.id')->where('cms_urls.urlable_type', Page::class))
            ->with(['url', 'template'])
            ->withCount('children')
            ->orderBy('cms_urls.url_full');

        return QueryBuilder::for($query, $request)
            ->allowedFilters(
                // Matches the name or the URL
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->where(
                    fn (Builder $query) => $query->whereLike('cms_pages.name', "%{$value}%")->orWhereLike('cms_urls.url_full', "%{$value}%")
                )),
                AllowedFilter::callback('status', function (Builder $query, string $value): void {
                    $query->whereHas('url', function (Builder $query) use ($value): void {
                        /** @var Builder<Url> $query */
                        $query->withStatus(PublishStatus::from($value));
                    });
                }),
            )
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());
    }
}
