<?php

namespace App\Http\Queries\Tenant\Admin\Dj;

use App\Http\Requests\Tenant\Admin\Dj\GigIndexRequest;
use App\Models\Dj\Gig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Upcoming gigs (including any on now) soonest first, or past gigs latest first
 */
class GigIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Gig>
     */
    public function handle(GigIndexRequest $request): LengthAwarePaginator
    {
        $now = CarbonImmutable::now();
        $when = $request->period();

        $query = Gig::query()
            ->when($when === 'upcoming', fn (Builder $query) => $query->where('ends_at', '>', $now)->oldest('starts_at'))
            ->when($when === 'past', fn (Builder $query) => $query->where('ends_at', '<=', $now)->latest('starts_at'))
            ->when($when === 'all', fn (Builder $query) => $query->latest('starts_at'));

        return QueryBuilder::for($query, $request)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query
                    ->whereLike('name', "%{$value}%")
                    ->orWhereLike('venue', "%{$value}%")
                    ->orWhereLike('location', "%{$value}%"))),
                // Applied above
                AllowedFilter::callback('when', fn () => null),
            )
            ->paginate(25)
            ->appends($request->query());
    }
}
