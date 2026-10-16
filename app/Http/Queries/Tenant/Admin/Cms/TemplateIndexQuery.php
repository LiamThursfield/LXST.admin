<?php

namespace App\Http\Queries\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\TemplateIndexRequest;
use App\Models\Cms\Template;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TemplateIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Template>
     */
    public function handle(TemplateIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Template::query()->withCount('fields'), $request)
            ->allowedFilters(
                'name',
                AllowedFilter::exact('type'),
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
