<?php

namespace App\Http\Queries\Tenant\Admin\Dj;

use App\Http\Requests\Tenant\Admin\Dj\LibraryIndexRequest;
use App\Models\Dj\LibraryImport;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The import history across every library, newest first
 */
class LibraryImportIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, LibraryImport>
     */
    public function handle(LibraryIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(LibraryImport::query()->with(['library', 'user']), $request)
            ->allowedFilters(AllowedFilter::exact('library_id'))
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->paginate($request->input('per_page', 10), pageName: 'imports_page')
            ->appends($request->query());
    }
}
