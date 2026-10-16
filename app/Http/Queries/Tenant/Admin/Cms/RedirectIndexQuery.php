<?php

namespace App\Http\Queries\Tenant\Admin\Cms;

use App\Enums\Cms\RedirectStatus;
use App\Http\Requests\Tenant\Admin\Cms\RedirectIndexRequest;
use App\Models\Cms\Redirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RedirectIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Redirect>
     */
    public function handle(RedirectIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Redirect::query(), $request)
            ->allowedFilters(
                // Matches either end of the redirect
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->where(
                    fn (Builder $query) => $query->whereLike('url_from', "%{$value}%")->orWhereLike('url_to', "%{$value}%")
                )),
                AllowedFilter::callback('status', function (Builder $query, string $value): void {
                    /** @var Builder<Redirect> $query */
                    $query->withStatus(RedirectStatus::from($value));
                }),
            )
            ->defaultSort('url_from')
            ->allowedSorts(
                'url_from',
                'url_to',
                'created_at',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
