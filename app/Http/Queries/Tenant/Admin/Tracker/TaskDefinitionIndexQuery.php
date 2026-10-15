<?php

namespace App\Http\Queries\Tenant\Admin\Tracker;

use App\Http\Requests\Tenant\Admin\Tracker\TaskDefinitionIndexRequest;
use App\Models\Tracker\TaskDefinition;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskDefinitionIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, TaskDefinition>
     */
    public function handle(TaskDefinitionIndexRequest $request): LengthAwarePaginator
    {
        /** @var User $user */
        $user = $request->user();

        return QueryBuilder::for(TaskDefinition::ownedBy($user), $request)
            ->allowedFilters(
                'title',
                AllowedFilter::exact('status'),
            )
            ->defaultSort('sort_order')
            ->allowedSorts(
                'title',
                'sort_order',
                'created_at',
            )
            ->with('category')
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
