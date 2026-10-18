<?php

namespace App\Http\Queries\Tenant\Admin\Dj;

use App\Http\Requests\Tenant\Admin\Dj\SongIndexRequest;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Services\Dj\Matching\CanonicalCleaner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Canonical tracks ("songs"), each counted once across libraries
 */
class SongIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, CanonicalTrack>
     */
    public function handle(SongIndexRequest $request): LengthAwarePaginator
    {
        $query = CanonicalTrack::query()
            ->with('artist')
            ->withCount([
                'libraryTracks',
                'libraryTracks as libraries_count' => fn (Builder $tracks) => $tracks->select(DB::raw('count(distinct library_id)')),
            ]);

        return QueryBuilder::for($query, $request)
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, string $value): void {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('title', "%{$value}%")
                        ->orWhereHas('artist', fn (Builder $artist) => $artist->whereLike('name', "%{$value}%"))
                    );
                }),
                AllowedFilter::exact('artist_id', 'canonical_artist_id'),
                AllowedFilter::callback('verified', fn (Builder $query, string $value) => $value === 'yes'
                    ? $query->whereNotNull('verified_at')
                    : $query->whereNull('verified_at')),
                AllowedFilter::callback('shared', function (Builder $query, mixed $value): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereRaw('(select count(distinct library_id) from dj_library_tracks where dj_library_tracks.canonical_track_id = dj_canonical_tracks.id) > 1');
                    }
                }),
                AllowedFilter::callback('orphaned', function (Builder $query, mixed $value): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        /** @var Builder<CanonicalTrack> $query */
                        CanonicalCleaner::orphanedTracks($query);
                    }
                }),
                AllowedFilter::exact('key'),
                AllowedFilter::callback('bpm_min', fn (Builder $query, string $value) => $query->where('bpm', '>=', (float) $value)),
                AllowedFilter::callback('bpm_max', fn (Builder $query, string $value) => $query->where('bpm', '<=', (float) $value)),
            )
            ->defaultSort('title')
            ->allowedSorts(
                'title',
                'bpm',
                'library_tracks_count',
                AllowedSort::callback('artist', function (Builder $query, bool $descending): void {
                    $query->orderBy(
                        CanonicalArtist::query()->select('name')->whereColumn('dj_canonical_artists.id', 'dj_canonical_tracks.canonical_artist_id'),
                        $descending ? 'desc' : 'asc',
                    );
                }),
            )
            ->paginate($request->integer('per_page', 50))
            ->appends($request->query());
    }
}
