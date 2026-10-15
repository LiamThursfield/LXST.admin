<?php

namespace App\Models\FileManager;

use Database\Factories\FileManager\FileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A file in the File Manager, stored on the file manager disk at its path
 *
 * @property int $id
 * @property int|null $folder_id
 * @property string $name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property string|null $alt_text
 * @property-read Folder|null $folder
 */
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    protected $table = 'file_manager_files';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'folder_id',
        'name',
        'path',
        'mime_type',
        'size',
        'alt_text',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function isImage(): bool
    {
        return Str::startsWith((string) $this->mime_type, 'image/');
    }
}
