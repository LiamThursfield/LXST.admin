<?php

namespace App\Http\Requests\Tenant\Admin\Cms\Concerns;

use App\Data\Cms\MenuItemData;
use App\Models\Cms\Menu;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesMenu
{
    /**
     * Fill in a blank slug from the name
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && is_string($this->input('name'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }

    /**
     * The shared validation rules, with the item rules repeated for each level of children
     *
     * @return array<string, mixed>
     */
    protected function menuRules(?Menu $menu = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('cms_menus', 'slug')->ignore($menu?->id)],
        ];

        $prefix = 'menu_items';

        for ($depth = 1; $depth <= MenuItemData::MAX_DEPTH; $depth++) {
            $rules = [...$rules, ...$this->itemRules($prefix, $depth)];
            $prefix .= '.*.children';
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    protected function itemRules(string $prefix, int $depth): array
    {
        return [
            $prefix => ['nullable', 'array', 'list', 'max:50'],
            "{$prefix}.*" => ['array'],
            "{$prefix}.*.label" => ['required', 'string', 'max:120'],
            "{$prefix}.*.link_type" => ['required', Rule::in([MenuItemData::LINK_PAGE, MenuItemData::LINK_URL])],
            // Each link type ignores the other's field
            "{$prefix}.*.page_id" => ["exclude_if:{$prefix}.*.link_type,url", 'required', 'integer', Rule::exists('cms_pages', 'id')],
            "{$prefix}.*.url" => ["exclude_if:{$prefix}.*.link_type,page", 'required', 'string', 'max:255', $this->safeUrlRule()],
            "{$prefix}.*.target" => ['nullable', Rule::in(['_self', '_blank'])],
            "{$prefix}.*.rel" => ['nullable', 'string', 'max:100'],
            "{$prefix}.*.custom_class" => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\- ]*$/'],
            // The deepest level can't have children
            "{$prefix}.*.children" => $depth < MenuItemData::MAX_DEPTH ? ['nullable', 'array'] : ['prohibited'],
        ];
    }

    /**
     * The item prefixes for each level, e.g. `menu_items.*` and `menu_items.*.children.*`
     *
     * @return list<string>
     */
    protected function itemPrefixes(): array
    {
        $prefixes = [];
        $prefix = 'menu_items.*';

        for ($depth = 1; $depth <= MenuItemData::MAX_DEPTH; $depth++) {
            $prefixes[] = $prefix;
            $prefix .= '.children.*';
        }

        return $prefixes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = ['slug.unique' => __('cms.menus.validation.slug_taken')];

        foreach ($this->itemPrefixes() as $prefix) {
            $messages["{$prefix}.children.prohibited"] = __('cms.menus.validation.too_deep', ['depth' => MenuItemData::MAX_DEPTH]);
            $messages["{$prefix}.custom_class.regex"] = __('cms.menus.validation.custom_class');
        }

        return $messages;
    }

    /**
     * Item fields named as in the item dialog, e.g. "The Label field is required"
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->itemPrefixes() as $prefix) {
            foreach (['label', 'page_id' => 'page', 'url', 'rel', 'custom_class'] as $field => $key) {
                $field = is_int($field) ? $key : $field;
                $attributes["{$prefix}.{$field}"] = __("cms.menus.item.{$key}");
            }
        }

        return $attributes;
    }

    /**
     * Custom links must be http(s), mailto, tel, a path on the site or an anchor
     */
    protected function safeUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $url = trim((string) $value);
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

            $isSafe = (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
                || str_starts_with($url, '#')
                || in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);

            if (! $isSafe) {
                $fail(__('cms.menus.validation.url'));
            }
        };
    }

    /**
     * The validated menu, with each item keeping only the link it uses (anything else sent, e.g.
     * the editor's keys, is dropped)
     *
     * @return array<string, mixed>
     */
    public function menuAttributes(): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = $this->validated('menu_items') ?? [];

        return [
            'name' => $this->validated('name'),
            'slug' => $this->validated('slug'),
            'menu_items' => $this->items($items),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function items(array $items): array
    {
        return array_map(function (array $item) {
            $isPage = $item['link_type'] === MenuItemData::LINK_PAGE;

            return [
                'label' => (string) $item['label'],
                'link_type' => (string) $item['link_type'],
                'page_id' => $isPage ? (int) $item['page_id'] : null,
                'url' => $isPage ? null : trim((string) $item['url']),
                'target' => filled($item['target'] ?? null) ? (string) $item['target'] : null,
                'rel' => filled($item['rel'] ?? null) ? (string) $item['rel'] : null,
                'custom_class' => filled($item['custom_class'] ?? null) ? (string) $item['custom_class'] : null,
                'children' => $this->items(is_array($item['children'] ?? null) ? array_values($item['children']) : []),
            ];
        }, $items);
    }
}
