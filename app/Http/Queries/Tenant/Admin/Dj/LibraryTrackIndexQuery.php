<?php

namespace App\Http\Queries\Tenant\Admin\Dj;

use App\Enums\Dj\MusicalKey;
use App\Http\Requests\Tenant\Admin\Dj\LibraryTrackIndexRequest;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTrack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class LibraryTrackIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, LibraryTrack>
     */
    public function handle(LibraryTrackIndexRequest $request): LengthAwarePaginator
    {
        $query = LibraryTrack::query()->with(['artist', 'library', 'canonicalTrack.artist']);

        // Missing tracks are hidden unless the filter asks for them
        if (! $request->boolean('filter.missing')) {
            $query->present();
        }

        return QueryBuilder::for($query, $request)
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, string $value): void {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('title', "%{$value}%")
                        ->orWhereHas('artist', fn (Builder $artist) => $artist->whereLike('name', "%{$value}%"))
                    );
                }),
                AllowedFilter::exact('library_id'),
                AllowedFilter::exact('artist_id', 'library_artist_id'),
                AllowedFilter::exact('genre'),
                AllowedFilter::callback('key', function (Builder $query, string $value) use ($request): void {
                    $key = MusicalKey::from($value);
                    $keys = $request->boolean('filter.compatible') ? $key->compatible() : [$key];

                    $query->whereIn('key', array_map(fn (MusicalKey $key) => $key->value, $keys));
                }),
                // Read alongside the key filter
                AllowedFilter::callback('compatible', fn () => null),
                AllowedFilter::callback('bpm_min', fn (Builder $query, string $value) => $query->where('bpm', '>=', (float) $value)),
                AllowedFilter::callback('bpm_max', fn (Builder $query, string $value) => $query->where('bpm', '<=', (float) $value)),
                AllowedFilter::callback('duration_min', fn (Builder $query, string $value) => $query->where('duration', '>=', (int) round((float) $value * 60))),
                AllowedFilter::callback('duration_max', fn (Builder $query, string $value) => $query->where('duration', '<=', (int) round((float) $value * 60))),
                // Applied above
                AllowedFilter::callback('missing', fn () => null),
                AllowedFilter::callback('hidden', function (Builder $query, mixed $value): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereHas('canonicalTrack', function (Builder $song): void {
                            /** @var Builder<CanonicalTrack> $song */
                            $song->hiddenFromRequests();
                        });
                    }
                }),
            )
            ->defaultSort('title')
            ->allowedSorts(
                'title',
                'bpm',
                'duration',
                'key',
                'date_added',
                'play_count',
                AllowedSort::callback('artist', function (Builder $query, bool $descending): void {
                    $query->orderBy(
                        LibraryArtist::query()->select('name')->whereColumn('dj_library_artists.id', 'dj_library_tracks.library_artist_id'),
                        $descending ? 'desc' : 'asc',
                    );
                }),
            )
            ->paginate($request->integer('per_page', 50))
            ->appends($request->query());
    }
}
