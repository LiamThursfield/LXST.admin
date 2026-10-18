<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Dj\LibraryArtistIndexQuery;
use App\Http\Requests\Tenant\Admin\Dj\LibraryArtistIndexRequest;
use App\Http\Resources\Tenant\Admin\Dj\LibraryArtistResource;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\Library;
use Inertia\Inertia;
use Inertia\Response;

class LibraryArtistController extends Controller
{
    public function index(LibraryArtistIndexRequest $request, LibraryArtistIndexQuery $query): Response
    {
        /** @var array<string, mixed> $filter */
        $filter = $request->validated('filter') ?? [];

        return Inertia::render('admin/dj/artist/Index', [
            'filter' => array_filter($filter, fn (mixed $value) => $value !== null && $value !== ''),
            'sort' => $request->validated('sort') ?? 'name',
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('dj.artists.columns.name')],
                ['accessorKey' => 'tracks_count', 'header' => __('dj.artists.columns.tracks')],
                ['accessorKey' => 'library', 'header' => __('dj.artists.columns.library')],
            ],
            'artists' => LibraryArtistResource::collection($query->handle($request)),
            'libraries' => Library::query()->orderBy('name')->get()
                ->map(fn (Library $library) => ['value' => (string) $library->id, 'label' => $library->name])
                ->values(),
            'canManageMatching' => $request->user()?->can('update', CanonicalArtist::class) ?? false,
        ]);
    }
}
