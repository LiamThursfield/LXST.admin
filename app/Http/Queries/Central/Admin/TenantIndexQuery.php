<?php

namespace App\Http\Queries\Central\Admin;

use App\Http\Requests\Central\Admin\TenantIndexRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TenantIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function handle(TenantIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Tenant::query()->with('domains'), $request)
            ->allowedFilters(
                // Matches the name, the ID or any of the tenant's domains
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->where(
                    fn (Builder $query) => $query->whereLike('name', "%{$value}%")
                        ->orWhereLike('id', "%{$value}%")
                        ->orWhereHas('domains', fn (Builder $query) => $query->whereLike('domain', "%{$value}%"))
                )),
                AllowedFilter::callback('module', fn (Builder $query, string $value) => $query->whereJsonContains('modules', $value)),
            )
            ->defaultSort('name')
            ->allowedSorts(
                'name',
                'id',
                'created_at',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
