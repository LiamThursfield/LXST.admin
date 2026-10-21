<?php

namespace App\Models\Cms;

use App\Enums\Cms\TemplateFieldType;
use Database\Factories\Cms\ContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One template field's value for a layout or page (see App\Models\Concerns\HasContent).
 * Deleting the field from its template deletes its content.
 *
 * @property int $id
 * @property string $contentable_type
 * @property int $contentable_id
 * @property int $template_field_id
 * @property TemplateFieldType $template_field_type The field's type when the value was saved
 * @property mixed $data
 * @property-read TemplateField $templateField
 */
class Content extends Model
{
    /** @use HasFactory<ContentFactory> */
    use HasFactory;

    protected $table = 'cms_content';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_field_id',
        'template_field_type',
        'data',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_field_type' => TemplateFieldType::class,
            'data' => 'json',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function contentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<TemplateField, $this>
     */
    public function templateField(): BelongsTo
    {
        return $this->belongsTo(TemplateField::class);
    }
}
