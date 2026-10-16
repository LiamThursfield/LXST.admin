<?php

namespace App\Models\Cms;

use App\Enums\Cms\TemplateType;
use Database\Factories\Cms\TemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Defines the fields that a page, layout, component or repeater has
 *
 * @property int $id
 * @property TemplateType $type
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property-read Collection<int, TemplateField> $fields
 * @property-read int|null $fields_count
 */
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    protected $table = 'cms_templates';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'slug',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TemplateType::class,
        ];
    }

    /**
     * @return HasMany<TemplateField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class)->orderBy('order');
    }

    /**
     * The fields (in other templates) that use this template for a component or repeater
     *
     * @return Builder<TemplateField>
     */
    public function usedByFields(): Builder
    {
        return TemplateField::query()->where('settings->template_id', $this->id);
    }

    /**
     * @return HasMany<Layout, $this>
     */
    public function layouts(): HasMany
    {
        return $this->hasMany(Layout::class);
    }

    /**
     * Whether anything uses this template, so it can't be deleted or change type.
     * Pages will also count once they exist (#51).
     */
    public function isInUse(): bool
    {
        return $this->usedByFields()->exists() || $this->layouts()->exists();
    }
}
