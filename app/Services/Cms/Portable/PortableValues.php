<?php

namespace App\Services\Cms\Portable;

use App\Enums\Cms\TemplateFieldType;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\Url;
use App\Models\Crm\Form;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;

/**
 * Turns stored content into what can move between environments, and back. Ids become natural keys:
 * files by path, pages by URL, and menus, forms, organisation units and saved components by slug.
 * Each type is handled with no default case, so a new field type can't be forgotten.
 *
 * Going back, only what's expected is read, so ids in a paste go no further. What it points to might
 * not be here, which is reported rather than failing. The values then go through the same rules as
 * the admin, which rebuild rich text from its JSON.
 *
 * @phpstan-type MissingReference array{field: string, required: bool, kind: string, reference: string}
 */
class PortableValues
{
    /**
     * @param  array<string, mixed>  $values  Stored values, keyed by field slug
     * @return array<string, mixed>
     */
    public function export(Template $template, array $values): array
    {
        return $template->fields
            ->mapWithKeys(fn (TemplateField $field) => [$field->slug => $this->exportValue($field, $values[$field->slug] ?? null)])
            ->all();
    }

    protected function exportValue(TemplateField $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            TemplateFieldType::Text,
            TemplateFieldType::Textarea,
            TemplateFieldType::Number,
            TemplateFieldType::Select => $value,
            TemplateFieldType::Wysiwyg => is_array($value) ? ['json' => $value['json'] ?? null] : null,
            TemplateFieldType::Image => $this->exportImage($value),
            TemplateFieldType::Link => $this->exportLink($value),
            TemplateFieldType::Component => $this->exportComponent($field, $value),
            TemplateFieldType::Repeater => $this->exportRows($field, $value),
            TemplateFieldType::CmsMenu => $this->slugReference('menu', Menu::query()->whereKey($value)->value('slug')),
            TemplateFieldType::CrmForm => $this->slugReference('form', Form::query()->whereKey($value)->value('slug')),
            TemplateFieldType::CrmOrganisationUnit, TemplateFieldType::CrmOpeningHours => $this->slugReference('organisation_unit', OrganisationUnit::query()->whereKey($value)->value('slug')),
        };
    }

    /**
     * @return array{file: string, alt: mixed}|null
     */
    protected function exportImage(mixed $value): ?array
    {
        $path = is_array($value) ? File::query()->whereKey($value['file_id'] ?? null)->value('path') : null;

        return $path === null ? null : ['file' => $path, 'alt' => $value['alt'] ?? null];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function exportLink(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (($value['link_type'] ?? null) !== 'page') {
            return ['link_type' => 'url', 'url' => $value['url'] ?? null, 'target' => $value['target'] ?? null];
        }

        $url = Url::query()->where('urlable_type', Page::class)->where('urlable_id', $value['page_id'] ?? null)->value('url_full');

        return $url === null ? null : ['link_type' => 'page', 'page' => $url, 'target' => $value['target'] ?? null];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function exportComponent(TemplateField $field, mixed $value): ?array
    {
        $savedComponentId = SavedComponent::referencedId($value);

        if ($savedComponentId !== null) {
            return $this->slugReference('saved_component', SavedComponent::query()->whereKey($savedComponentId)->value('slug'));
        }

        $template = $this->nestedTemplate($field);

        return $template !== null && is_array($value) ? $this->export($template, $value) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function exportRows(TemplateField $field, mixed $value): array
    {
        $template = $this->nestedTemplate($field);

        if ($template === null || ! is_array($value)) {
            return [];
        }

        return array_values(array_map(fn (mixed $row) => $this->export($template, is_array($row) ? $row : []), $value));
    }

    /**
     * @return array<string, string>|null
     */
    protected function slugReference(string $kind, mixed $slug): ?array
    {
        return is_string($slug) ? [$kind => $slug] : null;
    }

    /**
     * @param  array<mixed>  $portable  Values from the JSON, keyed by field slug
     * @param  list<MissingReference>  $missing  Filled in with what isn't here
     * @return array<string, mixed> Values as the admin's form would send them
     */
    public function import(Template $template, array $portable, array &$missing): array
    {
        $values = [];

        foreach ($template->fields as $field) {
            $values[$field->slug] = $this->importValue($field, $portable[$field->slug] ?? null, $missing);
        }

        return $values;
    }

    /**
     * @param  list<MissingReference>  $missing
     */
    protected function importValue(TemplateField $field, mixed $value, array &$missing): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            TemplateFieldType::Text,
            TemplateFieldType::Textarea,
            TemplateFieldType::Number,
            TemplateFieldType::Select => $value,
            TemplateFieldType::Wysiwyg => is_array($value) ? ['json' => $value['json'] ?? null] : $value,
            TemplateFieldType::Image => $this->importImage($field, $value, $missing),
            TemplateFieldType::Link => $this->importLink($field, $value, $missing),
            TemplateFieldType::Component => $this->importComponent($field, $value, $missing),
            TemplateFieldType::Repeater => $this->importRows($field, $value, $missing),
            TemplateFieldType::CmsMenu => $this->importSlug($field, $value, 'menu', Menu::class, $missing),
            TemplateFieldType::CrmForm => $this->importSlug($field, $value, 'form', Form::class, $missing),
            TemplateFieldType::CrmOrganisationUnit, TemplateFieldType::CrmOpeningHours => $this->importSlug($field, $value, 'organisation_unit', OrganisationUnit::class, $missing),
        };
    }

    /**
     * @param  list<MissingReference>  $missing
     */
    protected function importImage(TemplateField $field, mixed $value, array &$missing): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $path = $value['file'] ?? null;

        if (! is_string($path)) {
            return null;
        }

        $fileId = File::query()->where('path', $path)->value('id');

        if ($fileId === null) {
            $missing[] = $this->missing($field, 'file', $path);

            return null;
        }

        return ['file_id' => $fileId, 'alt' => $value['alt'] ?? null];
    }

    /**
     * @param  list<MissingReference>  $missing
     */
    protected function importLink(TemplateField $field, mixed $value, array &$missing): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (($value['link_type'] ?? null) !== 'page') {
            return ['link_type' => $value['link_type'] ?? null, 'url' => $value['url'] ?? null, 'target' => $value['target'] ?? null];
        }

        $url = $value['page'] ?? null;

        if (! is_string($url)) {
            return null;
        }

        $pageId = Url::query()->where('urlable_type', Page::class)->where('url_full', $url)->value('urlable_id');

        if ($pageId === null) {
            $missing[] = $this->missing($field, 'page', $url);

            return null;
        }

        return ['link_type' => 'page', 'page_id' => $pageId, 'target' => $value['target'] ?? null];
    }

    /**
     * @param  list<MissingReference>  $missing
     */
    protected function importComponent(TemplateField $field, mixed $value, array &$missing): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_key_exists('saved_component', $value)) {
            return $this->importSavedComponent($field, $value['saved_component'], $missing);
        }

        $template = $this->nestedTemplate($field);

        return $template === null ? null : $this->import($template, $value, $missing);
    }

    /**
     * @param  list<MissingReference>  $missing
     */
    protected function importSavedComponent(TemplateField $field, mixed $slug, array &$missing): mixed
    {
        if (! is_string($slug)) {
            return null;
        }

        $savedComponent = SavedComponent::query()->where('slug', $slug)->first();

        if ($savedComponent === null) {
            $missing[] = $this->missing($field, 'saved_component', $slug);

            return null;
        }

        return $savedComponent->reference();
    }

    /**
     * @param  list<MissingReference>  $missing
     */
    protected function importRows(TemplateField $field, mixed $value, array &$missing): mixed
    {
        $template = $this->nestedTemplate($field);

        if ($template === null || ! is_array($value) || ! array_is_list($value)) {
            return $value;
        }

        return array_map(fn (mixed $row) => is_array($row) ? $this->import($template, $row, $missing) : $row, $value);
    }

    /**
     * @param  class-string<Menu|Form|OrganisationUnit>  $model
     * @param  list<MissingReference>  $missing
     */
    protected function importSlug(TemplateField $field, mixed $value, string $kind, string $model, array &$missing): ?int
    {
        $slug = is_array($value) ? ($value[$kind] ?? null) : null;

        if (! is_string($slug)) {
            return null;
        }

        $id = $model::query()->where('slug', $slug)->value('id');

        if ($id === null) {
            $missing[] = $this->missing($field, $kind, $slug);
        }

        return $id;
    }

    /**
     * @return MissingReference
     */
    protected function missing(TemplateField $field, string $kind, string $reference): array
    {
        return ['field' => $field->name, 'required' => $field->is_required, 'kind' => $kind, 'reference' => $reference];
    }

    protected function nestedTemplate(TemplateField $field): ?Template
    {
        $id = $field->settings?->template_id;

        return $id === null ? null : Template::query()->with('fields')->find($id);
    }
}
