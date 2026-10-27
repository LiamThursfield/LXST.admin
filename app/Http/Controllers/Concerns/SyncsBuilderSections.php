<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * Saves the sections from a field builder (CMS templates, and any builder that groups its fields
 * later), before its fields are saved with SyncsBuilderFields
 */
trait SyncsBuilderSections
{
    /**
     * Makes the parent's sections match the submitted list: existing sections (by id) are updated,
     * new ones created and any not in the list removed. Gives back their ids in the submitted order,
     * so the fields can be placed in them.
     *
     * @template TSection of Model
     *
     * @param  HasMany<TSection, covariant Model>  $relation
     * @param  list<array<string, mixed>>  $sections
     * @return list<int>
     */
    protected function syncBuilderSections(HasMany $relation, array $sections): array
    {
        $keptIds = collect($sections)->pluck('id')->filter()->all();

        (clone $relation)->whereNotIn('id', $keptIds)->delete();

        return array_map(function (array $section) use ($relation): int {
            $attributes = Arr::except($section, 'id');

            if ($section['id'] !== null) {
                $model = (clone $relation)->whereKey($section['id'])->firstOrFail();
                $model->update($attributes);
            } else {
                $model = (clone $relation)->create($attributes);
            }

            return (int) $model->getKey();
        }, $sections);
    }
}
