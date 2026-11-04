<?php

namespace App\Http\Queries\Tenant\Admin\Bookings;

use App\Http\Requests\Tenant\Admin\Bookings\TimeOffIndexRequest;
use App\Models\Bookings\TimeOff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TimeOffIndexQuery
{
    /**
     * Everyone's time off for those allowed to see it, otherwise only the user's own, as it can say
     * why someone is away
     *
     * @return LengthAwarePaginator<int, TimeOff>
     */
    public function handle(TimeOffIndexRequest $request): LengthAwarePaginator
    {
        $user = $request->user();

        $query = TimeOff::query()
            ->with(['resource', 'requestedBy'])
            ->when(! $user?->can('viewEveryone', TimeOff::class), fn (Builder $query) => $query
                ->whereHas('resource', fn (Builder $query) => $query->where('user_id', $user?->id)));

        return QueryBuilder::for($query, $request)
            ->allowedFilters(
                AllowedFilter::exact('status'),
            )
            ->defaultSort('-starts_on')
            ->allowedSorts(
                'starts_on',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
