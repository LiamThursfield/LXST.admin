<?php

namespace App\Http\Queries\Tenant\Admin\Bookings;

use App\Http\Requests\Tenant\Admin\Bookings\ServiceIndexRequest;
use App\Models\Bookings\Service;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Service>
     */
    public function handle(ServiceIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Service::query()->with('category'), $request)
            ->allowedFilters(
                'name',
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
