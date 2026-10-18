<?php

namespace App\Models\Dj;

use App\Enums\Dj\ImportStatus;
use App\Enums\Dj\LibrarySource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\LibraryImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One import into a library: who started it, its progress, what it changed and any error
 *
 * @property int $id
 * @property int $library_id
 * @property int|null $user_id
 * @property LibrarySource $source
 * @property ImportStatus $status
 * @property string $original_filename
 * @property string|null $file_path Where the upload is kept until the import finishes
 * @property int $total Tracks to import
 * @property int $processed Tracks done
 * @property array<string, int>|null $counts What the import changed, e.g. tracks_added
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Library $library
 * @property-read User|null $user
 */
class LibraryImport extends Model
{
    /** @use HasFactory<LibraryImportFactory> */
    use HasFactory;

    protected $table = 'dj_library_imports';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'library_id',
        'user_id',
        'source',
        'status',
        'original_filename',
        'file_path',
        'total',
        'processed',
        'counts',
        'error',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LibrarySource::class,
            'status' => ImportStatus::class,
            'total' => 'integer',
            'processed' => 'integer',
            'counts' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * How far through the import is, from 0 to 100
     */
    public function progress(): int
    {
        if ($this->status === ImportStatus::Completed) {
            return 100;
        }

        return $this->total > 0 ? (int) floor($this->processed / $this->total * 100) : 0;
    }
}
