<?php

namespace App\Models\Cms;

use App\Models\FileManager\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * SEO and social sharing details for something (a page)
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $keywords
 * @property string|null $canonical
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_type
 * @property string|null $og_url
 * @property int|null $og_image_file_id A File Manager image
 * @property-read File|null $ogImage
 */
class Metadata extends Model
{
    protected $table = 'cms_metadata';

    /**
     * The fields that can be filled in
     *
     * @var list<string>
     */
    public const array FIELDS = [
        'title',
        'description',
        'keywords',
        'canonical',
        'og_title',
        'og_description',
        'og_type',
        'og_url',
        'og_image_file_id',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = self::FIELDS;

    /**
     * @return MorphTo<Model, $this>
     */
    public function metadatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(File::class, 'og_image_file_id');
    }
}
