<?php

namespace App\Models\Cms;

use App\Models\Concerns\HasContent;
use Database\Factories\Cms\SavedComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A named set of a component's data that's edited in one place, e.g. a Standard pricing price card.
 * Its fields come from a component template.
 *
 * @property int $id
 * @property int $template_id
 * @property string $name
 * @property string $slug
 * @property-read Collection<int, Content> $content
 */
class SavedComponent extends Model
{
    use HasContent;

    /** @use HasFactory<SavedComponentFactory> */
    use HasFactory;

    protected $table = 'cms_saved_components';

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
