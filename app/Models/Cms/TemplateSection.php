<?php

namespace App\Models\Cms;

use Database\Factories\Cms\TemplateSectionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named group of a template's fields, shown as one collapsible card in the content editor.
 * Content is keyed by field slug, so sections never change how content is stored or output.
 *
 * @property int $id
 * @property int $template_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_collapsed
 * @property int $order
 * @property-read Template $template
 * @property-read Collection<int, TemplateField> $fields
 */
class TemplateSection extends Model
{
    /** @use HasFactory<TemplateSectionFactory> */
    use HasFactory;

    protected $table = 'cms_template_sections';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'name',
        'description',
        'is_collapsed',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_collapsed' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return HasMany<TemplateField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class, 'section_id')->orderBy('order');
    }
}
