<?php

namespace App\Http\Queries\Tenant\Admin\Bookings;

use App\Http\Requests\Tenant\Admin\Bookings\ResourceIndexRequest;
use App\Models\Bookings\BookableResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ResourceIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, BookableResource>
     */
    public function handle(ResourceIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(BookableResource::query()->with('location')->withCount('services'), $request)
            ->allowedFilters(
                'name',
                AllowedFilter::exact('type'),
            )
            ->defaultSort('name')
            ->allowedSorts(
                'name',
                'created_at',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
