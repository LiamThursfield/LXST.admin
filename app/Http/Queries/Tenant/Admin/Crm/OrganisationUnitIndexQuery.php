<?php

namespace App\Http\Queries\Tenant\Admin\Crm;

use App\Http\Requests\Tenant\Admin\Crm\OrganisationUnitIndexRequest;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrganisationUnitIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, OrganisationUnit>
     */
    public function handle(OrganisationUnitIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(OrganisationUnit::query()->with('parent')->withCount('children'), $request)
            ->allowedFilters(
                'name',
                AllowedFilter::exact('type'),
                AllowedFilter::exact('parent_id'),
            )
            ->defaultSort('name')
            ->allowedSorts(
                'name',
                'type',
                'created_at',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
