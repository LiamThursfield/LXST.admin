<?php

namespace App\Mcp\Tools\Concerns;

use App\Mcp\Support\ToolRefused;
use Illuminate\Support\Str;

/**
 * For updating a template's or form's fields through MCP: fields sent without an id are matched to
 * existing fields by slug (so the assistant can resend fields by slug), and leaving out an existing
 * field is refused, as saving would delete it and its content or answers
 */
trait KeepsBuilderFields
{
    /**
     * Fills in the ids of fields sent without one, from the existing field with the same slug. A
     * field sent without a slug gets the one the admin would make from its name.
     *
     * @param  list<mixed>  $fields
     * @param  array<string, int>  $idsBySlug
     * @return list<mixed>
     */
    protected function withExistingIds(array $fields, array $idsBySlug): array
    {
        return array_map(function (mixed $field) use ($idsBySlug) {
            if (! is_array($field) || filled($field['id'] ?? null)) {
                return $field;
            }

            $slug = match (true) {
                is_string($field['slug'] ?? null) && $field['slug'] !== '' => $field['slug'],
                is_string($field['name'] ?? null) => Str::slug($field['name'], '_'),
                default => null,
            };

            if ($slug !== null && isset($idsBySlug[$slug])) {
                $field['id'] = $idsBySlug[$slug];
                $field['slug'] = $slug;
            }

            return $field;
        }, $fields);
    }

    /**
     * @param  array<int, string>  $existing  Existing fields' slugs, keyed by id
     * @param  list<mixed>  $fields  Every field being saved
     */
    protected function refuseRemovedFields(array $existing, array $fields): void
    {
        $keptIds = collect($fields)
            ->map(fn (mixed $field) => is_array($field) && is_numeric($field['id'] ?? null) ? (int) $field['id'] : null)
            ->filter()
            ->all();

        $removed = array_diff_key($existing, array_flip($keptIds));

        if ($removed !== []) {
            throw new ToolRefused(__('mcp.errors.removes_fields', ['fields' => implode(', ', $removed)]));
        }
    }
}
