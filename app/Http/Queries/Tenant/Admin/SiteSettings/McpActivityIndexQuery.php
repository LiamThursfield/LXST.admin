<?php

namespace App\Http\Queries\Tenant\Admin\SiteSettings;

use App\Http\Requests\Tenant\Admin\SiteSettings\McpActivityIndexRequest;
use App\Models\McpAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class McpActivityIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, McpAuditLog>
     */
    public function handle(McpActivityIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(McpAuditLog::query()->with('apiToken', 'user'), $request)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->whereLike('tool', "%{$value}%")),
                AllowedFilter::exact('token', 'api_token_id'),
                AllowedFilter::exact('outcome'),
            )
            ->defaultSort('-id')
            ->allowedSorts('id', 'tool', 'outcome')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());
    }
}
