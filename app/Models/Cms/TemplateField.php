<?php

namespace App\Models\Cms;

use App\Data\Cms\TemplateFieldSettings;
use App\Enums\Cms\TemplateFieldType;
use Database\Factories\Cms\TemplateFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One field of a template. Content is keyed by the field's slug, which is unique within the template.
 *
 * @property int $id
 * @property int $template_id
 * @property TemplateFieldType $type
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_required
 * @property int $order
 * @property TemplateFieldSettings|null $settings
 * @property-read Template $template
 */
class TemplateField extends Model
{
    /** @use HasFactory<TemplateFieldFactory> */
    use HasFactory;

    protected $table = 'cms_template_fields';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'type',
        'name',
        'slug',
        'description',
        'is_required',
        'order',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TemplateFieldType::class,
            'is_required' => 'boolean',
            'order' => 'integer',
            'settings' => TemplateFieldSettings::class,
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
