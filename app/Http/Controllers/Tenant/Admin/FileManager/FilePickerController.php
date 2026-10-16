<?php

namespace App\Http\Controllers\Tenant\Admin\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\FileManager\FilePickerIndexRequest;
use App\Http\Requests\Tenant\Admin\FileManager\FileStoreRequest;
use App\Http\Resources\Tenant\Admin\FileManager\FileResource;
use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Services\FileManager\FileManagerStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * JSON endpoints for the file picker (resources/js/tenant/components/file-manager), which is used
 * inside other pages (e.g. theme settings, CMS editors) so can't rely on those pages' props
 */
class FilePickerController extends Controller
{
    /**
     * A folder's (or the top level's) subfolders and files
     */
    public function index(FilePickerIndexRequest $request): JsonResponse
    {
        $folder = $request->folder();

        $files = File::query()
            ->where('folder_id', $folder?->id)
            ->when($request->boolean('images_only'), fn ($query) => $query->where('mime_type', 'like', 'image/%'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'currentFolder' => $folder?->only(['id', 'name']),
            'breadcrumbs' => $folder?->lineage()->map->only(['id', 'name'])->values() ?? [],
            'subfolders' => Folder::query()->where('parent_id', $folder?->id)->orderBy('name')->get(['id', 'name']),
            'files' => FileResource::collection($files),
            'canUpload' => $request->user()?->can('create', File::class) ?? false,
        ]);
    }

    /**
     * One file's details, e.g. to show the file a field has already chosen
     */
    public function show(File $file): FileResource
    {
        Gate::authorize('viewAny', Folder::class);

        return new FileResource($file);
    }

    /**
     * Uploads files from inside the picker, returning them so one can be chosen straight away
     */
    public function store(FileStoreRequest $request, FileManagerStorage $storage): JsonResponse
    {
        $folder = $request->folder();

        $files = collect($request->uploads())->map(fn ($upload) => $storage->storeUpload($upload, $folder));

        return response()->json([
            'files' => FileResource::collection($files),
        ], 201);
    }
}
