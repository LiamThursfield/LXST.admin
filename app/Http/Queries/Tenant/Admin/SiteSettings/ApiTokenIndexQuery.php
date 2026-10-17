<?php

namespace App\Http\Queries\Tenant\Admin\SiteSettings;

use App\Http\Requests\Tenant\Admin\SiteSettings\ApiTokenIndexRequest;
use App\Models\ApiToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ApiTokenIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, ApiToken>
     */
    public function handle(ApiTokenIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(ApiToken::query()->with('tokenable'), $request)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->whereLike('name', "%{$value}%")),
            )
            ->defaultSort('-created_at')
            ->allowedSorts(
                'name',
                'last_used_at',
                'expires_at',
                'created_at',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}
