<?php

namespace App\Http\Queries\Tenant\Admin\Tracker;

use App\Http\Requests\Tenant\Admin\Tracker\CategoryIndexRequest;
use App\Models\Tracker\Category;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Category>
     */
    public function handle(CategoryIndexRequest $request): LengthAwarePaginator
    {
        /** @var User $user */
        $user = $request->user();

        return QueryBuilder::for(Category::visibleTo($user), $request)
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
