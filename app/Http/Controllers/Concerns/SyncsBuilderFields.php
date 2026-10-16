<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Saves the fields from a field builder (CRM forms, CMS templates), where each field has a slug
 * that's unique within its parent
 */
trait SyncsBuilderFields
{
    /**
     * Makes the parent's fields match the submitted list: existing fields (by id) are updated,
     * new ones created and any not in the list removed
     *
     * @template TField of Model
     *
     * @param  HasMany<TField, covariant Model>  $relation
     * @param  list<array<string, mixed>>  $fields
     */
    protected function syncBuilderFields(HasMany $relation, array $fields): void
    {
        $keptIds = collect($fields)->pluck('id')->filter()->all();

        (clone $relation)->whereNotIn('id', $keptIds)->delete();

        // Free up the kept fields' slugs first, so fields can swap slugs without hitting the unique index
        (clone $relation)->whereIn('id', $keptIds)->update(['slug' => DB::raw("CONCAT('__', id)")]);

        foreach ($fields as $field) {
            $attributes = Arr::except($field, 'id');

            if ($field['id'] !== null) {
                (clone $relation)->whereKey($field['id'])->firstOrFail()->update($attributes);
            } else {
                (clone $relation)->create($attributes);
            }
        }
    }
}
