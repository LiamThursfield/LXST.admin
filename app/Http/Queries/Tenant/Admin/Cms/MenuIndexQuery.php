<?php

namespace App\Http\Queries\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\MenuIndexRequest;
use App\Models\Cms\Menu;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class MenuIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Menu>
     */
    public function handle(MenuIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Menu::query(), $request)
            ->allowedFilters('name')
            ->defaultSort('name')
            ->allowedSorts('name', 'created_at')
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
