<?php

namespace App\Http\Queries\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\SavedComponentIndexRequest;
use App\Models\Cms\SavedComponent;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SavedComponentIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, SavedComponent>
     */
    public function handle(SavedComponentIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(SavedComponent::query()->with('template'), $request)
            ->allowedFilters(
                'name',
                AllowedFilter::exact('template_id'),
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
