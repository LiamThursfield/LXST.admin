<?php

namespace App\Models\Cms;

use App\Models\Concerns\HasContent;
use Database\Factories\Cms\LayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Content shared by many pages, e.g. a header and footer. Its fields come from a layout template.
 *
 * @property int $id
 * @property int $template_id
 * @property string $name
 * @property string $slug
 * @property-read Collection<int, Content> $content
 */
class Layout extends Model
{
    use HasContent;

    /** @use HasFactory<LayoutFactory> */
    use HasFactory;

    protected $table = 'cms_layouts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'name',
        'slug',
    ];
}
