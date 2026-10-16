<?php

namespace App\Http\Queries\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\LayoutIndexRequest;
use App\Models\Cms\Layout;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LayoutIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Layout>
     */
    public function handle(LayoutIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Layout::query()->with('template'), $request)
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
