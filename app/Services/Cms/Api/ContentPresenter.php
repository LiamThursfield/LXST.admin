<?php

namespace App\Services\Cms\Api;

use App\Data\Cms\MenuItemData;
use App\Enums\Cms\PublishStatus;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\TenantModule;
use App\Http\Resources\Tenant\Api\V1\Crm\FormDefinitionResource;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\Url;
use App\Models\Crm\Address;
use App\Models\Crm\Form;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use App\Services\Cms\ContentRules;
use App\Services\FileManager\FileManagerStorage;

/**
 * Turns stored content into what a headless site needs: nested components and repeaters
 * resolved, rich text in the asked-for format, and ids (files, menus, forms, organisation units)
 * replaced by what they point to. Anything that's gone (or switched off) becomes null.
 */
class ContentPresenter
{
    /** Both of rich text's formats, as {json, html} */
    public const string FORMAT_BOTH = 'both';

    public const string FORMAT_HTML = 'html';

    public const string FORMAT_JSON = 'json';

    public function __construct(
        protected ContentRules $rules,
        protected FileManagerStorage $files,
    ) {}

    /**
     * @param  array<string, mixed>  $values  Stored values, keyed by field slug
     * @return array<string, mixed>
     */
    public function present(Template $template, array $values, string $format = self::FORMAT_BOTH): array
    {
        return $template->fields
            ->mapWithKeys(fn (TemplateField $field) => [$field->slug => $this->value($field, $values[$field->slug] ?? null, $format)])
            ->all();
    }

    protected function value(TemplateField $field, mixed $value, string $format): mixed
    {
        if ($value === null) {
            return $field->type === TemplateFieldType::Repeater ? [] : null;
        }

        return match ($field->type) {
            TemplateFieldType::Text, TemplateFieldType::Textarea, TemplateFieldType::Number => $value,
            TemplateFieldType::Wysiwyg => $this->richText($value, $format),
            TemplateFieldType::Image => $this->image($value),
            TemplateFieldType::Component => $this->component($field, $value, $format),
            TemplateFieldType::Repeater => $this->repeater($field, $value, $format),
            TemplateFieldType::CmsMenu => $this->menu($value),
            TemplateFieldType::CrmForm => $this->form($value),
            TemplateFieldType::CrmOrganisationUnit => $this->organisationUnit($value),
        };
    }

    protected function richText(mixed $value, string $format): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        return match ($format) {
            self::FORMAT_HTML => $value['html'] ?? null,
            self::FORMAT_JSON => $value['json'] ?? null,
            default => ['json' => $value['json'] ?? null, 'html' => $value['html'] ?? null],
        };
    }

    /**
     * @return array{url: string, alt: string|null, name: string, mime_type: string|null}|null
     */
    public function image(mixed $value): ?array
    {
        if (! is_array($value) || ! is_numeric($value['file_id'] ?? null)) {
            return null;
        }

        $file = File::query()->whereKey((int) $value['file_id'])->first();

        return $file === null ? null : [
            'url' => $this->files->url($file),
            'alt' => is_string($value['alt'] ?? null) ? $value['alt'] : $file->alt_text,
            'name' => $file->name,
            'mime_type' => $file->mime_type,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function component(TemplateField $field, mixed $value, string $format): ?array
    {
        $template = $this->rules->nestedTemplate($field);

        return $template === null || ! is_array($value) ? null : $this->present($template, $value, $format);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function repeater(TemplateField $field, mixed $value, string $format): array
    {
        $template = $this->rules->nestedTemplate($field);

        if ($template === null || ! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $row) => $this->present($template, is_array($row) ? $row : [], $format),
            $value,
        ));
    }

    /**
     * @return array{slug: string, name: string, items: list<array<string, mixed>>}|null
     */
    protected function menu(mixed $value): ?array
    {
        $menu = is_numeric($value) ? Menu::query()->find((int) $value) : null;

        if ($menu === null) {
            return null;
        }

        // Page links use the page's current URL; links to pages that aren't published are left out
        $pageUrls = Url::query()
            ->withStatus(PublishStatus::Published)
            ->where('urlable_type', Page::class)
            ->whereIn('urlable_id', $menu->pageIds())
            ->pluck('url_full', 'urlable_id');

        return [
            'slug' => $menu->slug,
            'name' => $menu->name,
            'items' => $this->menuItems($menu->menu_items ?? [], $pageUrls->all()),
        ];
    }

    /**
     * @param  iterable<MenuItemData>  $items
     * @param  array<int, string>  $pageUrls
     * @return list<array<string, mixed>>
     */
    protected function menuItems(iterable $items, array $pageUrls): array
    {
        $presented = [];

        foreach ($items as $item) {
            $url = $item->link_type === MenuItemData::LINK_PAGE
                ? ($item->page_id === null ? null : ($pageUrls[$item->page_id] ?? null))
                : $item->url;

            if ($url === null) {
                continue;
            }

            $presented[] = [
                'label' => $item->label,
                'url' => $url,
                'target' => $item->target,
                'rel' => $item->rel,
                'class' => $item->custom_class,
                'children' => $this->menuItems($item->children, $pageUrls),
            ];
        }

        return $presented;
    }

    /**
     * The form's definition, without a spam protection token: cached pages would hand every
     * visitor the same old token, so sites fetch a fresh one from `api_url` when showing the form
     *
     * @return array<string, mixed>|null
     */
    protected function form(mixed $value): ?array
    {
        $form = $this->crmEnabled() && is_numeric($value)
            ? Form::query()->with('fields')->find((int) $value)
            : null;

        if ($form === null) {
            return null;
        }

        /** @var array<string, mixed> $definition */
        $definition = (new FormDefinitionResource($form))->resolve();
        unset($definition['spam_protection']);

        return [
            ...$definition,
            'api_url' => route('api.v1.crm.forms.show', $form->slug),
            'submit_url' => route('api.v1.crm.forms.submissions.store', $form->slug),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function organisationUnit(mixed $value): ?array
    {
        $unit = $this->crmEnabled() && is_numeric($value)
            ? OrganisationUnit::query()->with('address')->find((int) $value)
            : null;

        return $unit === null ? null : [
            'slug' => $unit->slug,
            'name' => $unit->name,
            'type' => $unit->type->value,
            'email' => $unit->email,
            'telephone' => $unit->telephone,
            'mobile_phone' => $unit->mobile_phone,
            'socials' => (object) ($unit->socials ?? []),
            'address' => $unit->address?->only(Address::FIELDS),
        ];
    }

    protected function crmEnabled(): bool
    {
        return tenant()?->hasModule(TenantModule::Crm) ?? false;
    }
}
