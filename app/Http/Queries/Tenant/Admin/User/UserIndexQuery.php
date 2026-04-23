<?php

namespace App\Http\Queries\Tenant\Admin\User;

use App\Http\QueryFilters\AnyRoleFilter;
use App\Http\Requests\Tenant\Admin\User\UserIndexRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(UserIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(User::class, $request)
            ->allowedFilters(
                'first_name',
                'last_name',
                'email',
                AllowedFilter::custom('role', new AnyRoleFilter),
            )->with(['roles'])
            ->paginate($request->input('per_page', 15));
    }
}
