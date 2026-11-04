<?php

namespace App\Services\Cms\Api;

use App\Data\Cms\MenuItemData;
use App\Enums\Cms\PublishStatus;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Http\Resources\Tenant\Api\V1\Crm\FormDefinitionResource;
use App\Http\Resources\Tenant\Api\V1\Crm\LocationResource;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\Url;
use App\Models\Crm\Address;
use App\Models\Crm\Form;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use App\Services\Cms\ContentRules;
use App\Services\FileManager\FileManagerStorage;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Loaded the first time a component uses one, so many cards don't mean many queries. Starts
     * afresh with each present(), as a presenter can outlive the request it was made for.
     *
     * @var Collection<int, SavedComponent>|null
     */
    protected ?Collection $savedComponents = null;

    /**
     * Published pages' URLs by page id, loaded the first time a link needs them, as with saved components
     *
     * @var array<int, string>|null
     */
    protected ?array $pageUrls = null;

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
        $this->savedComponents = null;
        $this->pageUrls = null;

        return $this->fields($template, $values, $format);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function fields(Template $template, array $values, string $format): array
    {
        return $template->fields
            ->mapWithKeys(fn (TemplateField $field) => [$field->slug => $this->value($field, $values[$field->slug] ?? null, $format)])
            ->all();
    }

    protected function value(TemplateField $field, mixed $value, string $format): mixed
    {
        if ($value === null && $field->type !== TemplateFieldType::Select) {
            return $field->type === TemplateFieldType::Repeater ? [] : null;
        }

        return match ($field->type) {
            TemplateFieldType::Text, TemplateFieldType::Textarea, TemplateFieldType::Number => $value,
            TemplateFieldType::Wysiwyg => $this->richText($value, $format),
            TemplateFieldType::Image => $this->image($value),
            TemplateFieldType::Select => $this->select($field, $value),
            TemplateFieldType::Link => $this->link($value),
            TemplateFieldType::Component => $this->component($field, $value, $format),
            TemplateFieldType::Repeater => $this->repeater($field, $value, $format),
            TemplateFieldType::CmsMenu => $this->menu($value),
            TemplateFieldType::CrmForm => $this->form($value),
            TemplateFieldType::CrmOrganisationUnit => $this->organisationUnit($value),
            TemplateFieldType::CrmOpeningHours => $this->openingHours($value),
        };
    }

    /**
     * The chosen value, or the default if it's blank or its option has since been removed
     */
    protected function select(TemplateField $field, mixed $value): ?string
    {
        $settings = $field->settings;

        return in_array($value, array_column($settings->options ?? [], 'value'), true) ? $value : $settings?->default;
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
     * Page links use the page's current URL, and links to pages that aren't published are null
     *
     * @return array{url: string, target: string|null}|null
     */
    protected function link(mixed $value): ?array
    {
        $destination = $this->rules->linkDestination($value);

        if (is_int($destination)) {
            $this->pageUrls ??= Url::query()
                ->withStatus(PublishStatus::Published)
                ->where('urlable_type', Page::class)
                ->pluck('url_full', 'urlable_id')
                ->all();

            $destination = $this->pageUrls[$destination] ?? null;
        }

        /** @var array<string, mixed> $value */
        return $destination === null ? null : ['url' => $destination, 'target' => $value['target'] ?? null];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function component(TemplateField $field, mixed $value, string $format): ?array
    {
        $template = $this->rules->nestedTemplate($field);

        if ($template === null || ! is_array($value)) {
            return null;
        }

        return SavedComponent::isReference($value)
            ? $this->savedComponent($template, $value, $format)
            : $this->fields($template, $value, $format);
    }

    /**
     * The saved component's content, in the same shape as a custom component's, or null if it's
     * gone or has a different template because the field's template was changed
     *
     * @param  array<mixed>  $reference
     * @return array<string, mixed>|null
     */
    protected function savedComponent(Template $template, array $reference, string $format): ?array
    {
        $this->savedComponents ??= SavedComponent::query()->with(['template.fields', 'content'])->get()->keyBy('id');

        $savedComponent = $this->savedComponents->get(SavedComponent::referencedId($reference));

        return $savedComponent?->template_id === $template->id
            ? $this->fields($template, $savedComponent->contentValues(), $format)
            : null;
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
            fn (mixed $row) => $this->fields($template, is_array($row) ? $row : [], $format),
            $value,
        ));
    }

    /**
     * @return array{slug: string, name: string, items: list<array<string, mixed>>}|null
     */
    protected function menu(mixed $value): ?array
    {
        $menu = is_numeric($value) ? Menu::query()->find((int) $value) : null;

        return $menu === null ? null : $this->presentMenu($menu);
    }

    /**
     * @return array{slug: string, name: string, items: list<array<string, mixed>>}
     */
    public function presentMenu(Menu $menu): array
    {
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

    /**
     * The location and its hours, without whether it's open now: cached pages would hand every
     * visitor the same old answer, so sites fetch a fresh one from `api_url`
     *
     * @return array<string, mixed>|null
     */
    protected function openingHours(mixed $value): ?array
    {
        $location = $this->crmEnabled() && is_numeric($value)
            ? OrganisationUnit::query()
                ->where('type', OrganisationUnitType::Location)
                ->with(['address', 'openingHours', 'openingHourExceptions'])
                ->find((int) $value)
            : null;

        if ($location === null) {
            return null;
        }

        /** @var array<string, mixed> $details */
        $details = (new LocationResource($location))->resolve();
        unset($details['status']);

        return [...$details, 'api_url' => route('api.v1.crm.locations.show', $location->slug)];
    }

    protected function crmEnabled(): bool
    {
        return tenant()?->hasModule(TenantModule::Crm) ?? false;
    }
}
