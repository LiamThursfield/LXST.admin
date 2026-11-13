<?php

namespace App\Http\Queries\Tenant\Admin\Catalogue;

use App\Http\Requests\Tenant\Admin\Catalogue\TagIndexRequest;
use App\Models\Catalogue\Tag;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class TagIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Tag>
     */
    public function handle(TagIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Tag::query()->withCount('items'), $request)
            ->allowedFilters(
                'name',
            )
            ->defaultSort('kind', 'order', 'name')
            ->allowedSorts(
                'name',
                'kind',
                'order',
            )
            ->paginate($request->input('per_page', 50))
            ->appends($request->query());
    }
}
