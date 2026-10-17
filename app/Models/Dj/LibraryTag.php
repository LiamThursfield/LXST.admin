<?php

namespace App\Models\Dj;

use Carbon\CarbonImmutable;
use Database\Factories\Dj\LibraryTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A Rekordbox My Tag in one library, e.g. "Deep House" in the "Genre" category
 *
 * @property int $id
 * @property int $library_id
 * @property string $source_tag_id The tag's ID in the source, unique per library
 * @property string $name
 * @property string|null $category
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Library $library
 */
class LibraryTag extends Model
{
    /** @use HasFactory<LibraryTagFactory> */
    use HasFactory;

    protected $table = 'dj_library_tags';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'library_id',
        'source_tag_id',
        'name',
        'category',
    ];

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * @return BelongsToMany<LibraryTrack, $this>
     */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(LibraryTrack::class, 'dj_library_tag_track');
    }
}
