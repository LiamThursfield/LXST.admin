<?php

namespace App\Http\Queries\Central\Admin;

use App\Http\Requests\Central\Admin\User\UserIndexRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        return QueryBuilder::for(User::query(), $request)
            ->allowedFilters(
                // Matches the first name, last name or email
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->where(
                    fn (Builder $query) => $query->whereLike('first_name', "%{$value}%")
                        ->orWhereLike('last_name', "%{$value}%")
                        ->orWhereLike('email', "%{$value}%")
                )),
            )
            ->defaultSort('email')
            ->allowedSorts(
                'first_name',
                'last_name',
                'email',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
