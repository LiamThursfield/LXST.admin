<?php

namespace App\Http\Controllers\Tenant\Admin\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\FileManager\FileManagerIndexRequest;
use App\Http\Resources\Tenant\Admin\FileManager\FileResource;
use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class FileManagerController extends Controller
{
    /**
     * Browse a folder (or the top level). Moving between folders only reloads the folder's contents,
     * not the folder tree.
     */
    public function index(FileManagerIndexRequest $request): Response
    {
        $folder = $request->folder();

        return Inertia::render('admin/file-manager/Index', [
            'folderTree' => fn () => $this->folderTree(Folder::query()->orderBy('name')->get()),
            'currentFolder' => $folder?->only(['id', 'name', 'path']),
            'breadcrumbs' => $folder?->lineage()->map->only(['id', 'name'])->values() ?? [],
            'subfolders' => Folder::query()
                ->where('parent_id', $folder?->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'files' => FileResource::collection(
                File::query()->where('folder_id', $folder?->id)->orderBy('name')->paginate(50)->withQueryString()
            ),
            'canCreateFolders' => $request->user()?->can('create', Folder::class) ?? false,
        ]);
    }

    /**
     * Nests the folders under their parents
     *
     * @param  Collection<int, Folder>  $folders
     * @return list<array{id: int, name: string, children: list<mixed>}>
     */
    protected function folderTree(Collection $folders, ?int $parentId = null): array
    {
        return array_values($folders
            ->where('parent_id', $parentId)
            ->map(fn (Folder $folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'children' => $this->folderTree($folders, $folder->id),
            ])
            ->all());
    }
}
