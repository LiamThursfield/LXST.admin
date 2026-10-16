<?php

namespace App\Models\Concerns;

use App\Models\Cms\Content;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For models whose content is defined by a CMS template (layouts, and pages in #51). Each of the
 * template's fields has at most one Content row, and content is read and written keyed by the
 * field's slug.
 *
 * @property int|null $template_id
 * @property-read Template $template
 */
trait HasContent
{
    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return MorphMany<Content, $this>
     */
    public function content(): MorphMany
    {
        return $this->morphMany(Content::class, 'contentable');
    }

    /**
     * The saved values, keyed by the template field's slug. Fields with nothing saved are null.
     *
     * @return array<string, mixed>
     */
    public function contentValues(): array
    {
        $this->loadMissing('template.fields', 'content');

        $saved = $this->content->keyBy('template_field_id');

        return $this->template->fields
            ->mapWithKeys(fn (TemplateField $field) => [$field->slug => $saved->get($field->id)?->data])
            ->all();
    }

    /**
     * Saves the values (keyed by field slug) for the current template's fields, and removes any
     * content that doesn't belong to its fields, e.g. after the template was changed
     *
     * @param  array<string, mixed>  $values
     */
    public function syncContent(array $values): void
    {
        $fields = $this->template()->firstOrFail()->fields()->get();

        $this->content()->whereNotIn('template_field_id', $fields->pluck('id'))->delete();

        foreach ($fields as $field) {
            $value = $values[$field->slug] ?? null;

            if ($value === null || $value === '') {
                $this->content()->where('template_field_id', $field->id)->delete();

                continue;
            }

            $this->content()->updateOrCreate(
                ['template_field_id' => $field->id],
                ['template_field_type' => $field->type, 'data' => $value],
            );
        }

        $this->unsetRelation('content');
    }
}
