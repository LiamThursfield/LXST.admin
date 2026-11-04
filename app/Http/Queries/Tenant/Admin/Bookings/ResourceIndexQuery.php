<?php

namespace App\Http\Queries\Tenant\Admin\Bookings;

use App\Enums\Bookings\ResourceType;
use App\Http\Requests\Tenant\Admin\Bookings\ResourceIndexRequest;
use App\Models\Bookings\BookableResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ResourceIndexQuery
{
    /**
     * Either the people, or everything that isn't a person
     *
     * @return LengthAwarePaginator<int, BookableResource>
     */
    public function handle(ResourceIndexRequest $request, bool $people): LengthAwarePaginator
    {
        return QueryBuilder::for(
            BookableResource::query()
                ->where('type', $people ? '=' : '!=', ResourceType::Person)
                ->with('location')
                ->withCount('services'),
            $request
        )
            ->allowedFilters(
                'name',
                AllowedFilter::exact('type'),
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
