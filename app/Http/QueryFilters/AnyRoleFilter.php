<?php

namespace App\Http\QueryFilters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Filter to check if a model has any of the specified roles.
 *
 * @implements Filter<User>
 */
class AnyRoleFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        // $value can be a single role or an array of roles
        $roles = Arr::wrap($value);

        $query->whereHas('roles', function (Builder $query) use ($roles) {
            $query->whereIn('name', $roles);
        });
    }
}
