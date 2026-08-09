<?php

namespace App\Http\Queries\Tenant\Admin\Tracker;

use App\Http\Requests\Tenant\Admin\Tracker\HolidayIndexRequest;
use App\Models\Tracker\Holiday;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class HolidayIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Holiday>
     */
    public function handle(HolidayIndexRequest $request): LengthAwarePaginator
    {
        /** @var User $user */
        $user = $request->user();

        return QueryBuilder::for(Holiday::ownedBy($user), $request)
            ->allowedFilters(
                'label',
            )
            ->defaultSort('-starts_on')
            ->allowedSorts(
                'starts_on',
                'ends_on',
                'label',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
