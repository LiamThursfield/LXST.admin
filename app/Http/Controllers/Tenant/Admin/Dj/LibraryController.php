<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\ImportStatus;
use App\Enums\Dj\LibrarySource;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Dj\LibraryImportIndexQuery;
use App\Http\Requests\Tenant\Admin\Dj\LibraryIndexRequest;
use App\Http\Resources\Tenant\Admin\Dj\LibraryImportResource;
use App\Http\Resources\Tenant\Admin\Dj\LibraryResource;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The DJ's libraries and their import history. Browsing a library's tracks comes with #115.
 */
class LibraryController extends Controller
{
    public function index(LibraryIndexRequest $request, LibraryImportIndexQuery $query): Response
    {
        $libraries = Library::query()
            ->withCount([
                'tracks as present_tracks_count' => fn (Builder $tracks) => $tracks->whereNull('missing_since'),
                'tracks as missing_tracks_count' => fn (Builder $tracks) => $tracks->whereNotNull('missing_since'),
                'artists',
                'historySessions',
            ])
            ->withMax(['imports as last_imported_at' => fn (Builder $imports) => $imports->where('status', ImportStatus::Completed)], 'finished_at')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/dj/library/Index', [
            'libraries' => LibraryResource::collection($libraries),
            'imports' => LibraryImportResource::collection($query->handle($request)),
            // The page keeps checking while an import is still going
            'hasRunningImports' => LibraryImport::query()
                ->whereIn('status', [ImportStatus::Pending, ImportStatus::Processing])
                ->exists(),
            'importSources' => array_map(fn (LibrarySource $source) => $source->toSelectItem(), LibrarySource::uploadable()),
            'maxUploadMb' => (int) round((int) config('dj.imports.max_size_kb') / 1024),
            'canImport' => $request->user()?->can('create', Library::class) ?? false,
        ]);
    }

    /**
     * Deletes the library with its artists, tracks, tags, history and imports.
     * Canonical artists and tracks are kept.
     */
    public function destroy(Library $library): RedirectResponse
    {
        Gate::authorize('delete', $library);

        $library->delete();

        return redirect()->route('admin.dj.libraries.index')
            ->with('success', __('dj.libraries.flash.deleted'));
    }
}
