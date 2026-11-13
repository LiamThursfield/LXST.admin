<?php

namespace App\Http\Queries\Tenant\Admin\Catalogue;

use App\Http\Requests\Tenant\Admin\Catalogue\CatalogueIndexRequest;
use App\Models\Catalogue\Catalogue;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class CatalogueIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Catalogue>
     */
    public function handle(CatalogueIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Catalogue::query()->withCount(['sections', 'items']), $request)
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
