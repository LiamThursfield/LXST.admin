<?php

namespace App\Models\FileManager;

use Database\Factories\FileManager\FolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A folder in the File Manager, mirroring a directory on the file manager disk
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $path
 * @property-read Folder|null $parent
 * @property-read Collection<int, Folder> $children
 * @property-read Collection<int, File> $files
 */
class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory;

    protected $table = 'file_manager_folders';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'path',
    ];

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('name');
    }

    /**
     * @return HasMany<File, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class)->orderBy('name');
    }

    /**
     * The folder and its parents, from the top level down, for breadcrumbs
     *
     * @return Collection<int, Folder>
     */
    public function lineage(): Collection
    {
        $lineage = collect([$this]);
        $parent = $this->parent;

        while ($parent !== null) {
            $lineage->prepend($parent);
            $parent = $parent->parent;
        }

        return $lineage;
    }
}
