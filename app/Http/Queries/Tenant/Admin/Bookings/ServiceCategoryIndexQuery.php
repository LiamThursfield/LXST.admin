<?php

namespace App\Http\Queries\Tenant\Admin\Bookings;

use App\Http\Requests\Tenant\Admin\Bookings\ServiceCategoryIndexRequest;
use App\Models\Bookings\ServiceCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceCategoryIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, ServiceCategory>
     */
    public function handle(ServiceCategoryIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(ServiceCategory::query()->withCount('services'), $request)
            ->allowedFilters(
                'name',
            )
            ->defaultSort('order', 'name')
            ->allowedSorts(
                'name',
                'order',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
