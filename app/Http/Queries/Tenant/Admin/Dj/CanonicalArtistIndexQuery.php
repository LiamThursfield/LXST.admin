<?php

namespace App\Http\Queries\Tenant\Admin\Dj;

use App\Http\Requests\Tenant\Admin\Dj\CanonicalArtistIndexRequest;
use App\Models\Dj\CanonicalArtist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Canonical artists, each counted once across libraries
 */
class CanonicalArtistIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, CanonicalArtist>
     */
    public function handle(CanonicalArtistIndexRequest $request): LengthAwarePaginator
    {
        $query = CanonicalArtist::query()->withCount(['tracks', 'libraryArtists']);

        return QueryBuilder::for($query, $request)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->whereLike('name', "%{$value}%")),
                AllowedFilter::callback('verified', fn (Builder $query, string $value) => $value === 'yes'
                    ? $query->whereNotNull('verified_at')
                    : $query->whereNull('verified_at')),
                AllowedFilter::callback('orphaned', function (Builder $query, mixed $value): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereDoesntHave('libraryArtists');
                    }
                }),
                AllowedFilter::callback('hidden', function (Builder $query, mixed $value): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereNotNull('hidden_from_requests_at');
                    }
                }),
            )
            ->defaultSort('name')
            ->allowedSorts('name', 'tracks_count', 'library_artists_count')
            ->paginate($request->integer('per_page', 50))
            ->appends($request->query());
    }
}
