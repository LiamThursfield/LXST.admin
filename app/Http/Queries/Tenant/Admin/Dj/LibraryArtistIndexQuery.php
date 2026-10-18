<?php

namespace App\Http\Queries\Tenant\Admin\Dj;

use App\Http\Requests\Tenant\Admin\Dj\LibraryArtistIndexRequest;
use App\Models\Dj\LibraryArtist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LibraryArtistIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, LibraryArtist>
     */
    public function handle(LibraryArtistIndexRequest $request): LengthAwarePaginator
    {
        $query = LibraryArtist::query()
            ->with(['library', 'canonicalArtist'])
            // Only the tracks the latest import still had
            ->withCount(['tracks' => fn (Builder $tracks) => $tracks->whereNull('missing_since')]);

        return QueryBuilder::for($query, $request)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->whereLike('name', "%{$value}%")),
                AllowedFilter::exact('library_id'),
                AllowedFilter::callback('hidden', function (Builder $query, mixed $value): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereHas('canonicalArtist', fn (Builder $artist) => $artist->whereNotNull('hidden_from_requests_at'));
                    }
                }),
            )
            ->defaultSort('name')
            ->allowedSorts('name', 'tracks_count')
            ->paginate($request->integer('per_page', 50))
            ->appends($request->query());
    }
}
