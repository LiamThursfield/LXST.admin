<?php

namespace App\Services\Catalogue;

use App\Enums\Catalogue\TagKind;
use App\Models\Catalogue\Tag;
use App\Models\Tenant;

/**
 * The built-in tag groups from config/catalogue.php, such as the 14 allergens, and adding them to
 * the tenant's own tags
 */
class CatalogueTags
{
    /**
     * Every built-in group, with whether the tenant's industry suggests it and how many of its tags
     * the tenant still doesn't have
     *
     * @return list<array{key: string, label: string, kind: string, suggested: bool, missing: int}>
     */
    public function groups(): array
    {
        $tenant = tenant();
        $suggested = $tenant instanceof Tenant ? $tenant->industryProfile()->catalogueTags : [];
        $existing = Tag::query()->whereNotNull('preset')->pluck('preset')->all();
        $groups = [];

        foreach ($this->config() as $key => $group) {
            $groups[] = [
                'key' => $key,
                'label' => (string) __("catalogue.presets.groups.{$key}"),
                'kind' => $group['kind']->value,
                'suggested' => in_array($key, $suggested, true),
                'missing' => count(array_diff(array_keys($group['tags']), $existing)),
            ];
        }

        return $groups;
    }

    /**
     * Adds the group's tags that the tenant doesn't have yet, after its others, and returns how many
     * it added. A built-in tag the tenant renamed is left as it is.
     */
    public function add(string $groupKey): int
    {
        $group = $this->config()[$groupKey] ?? null;

        if ($group === null) {
            return 0;
        }

        $order = (int) Tag::query()->max('order');
        $added = 0;

        foreach ($group['tags'] as $preset => $icon) {
            $tag = Tag::query()->firstOrCreate(['preset' => $preset], [
                'kind' => $group['kind'],
                'name' => (string) __("catalogue.presets.tags.{$preset}"),
                'icon' => $icon,
                'order' => ++$order,
            ]);

            $added += $tag->wasRecentlyCreated ? 1 : 0;
        }

        return $added;
    }

    /**
     * @return array<string, array{kind: TagKind, tags: array<string, string|null>}>
     */
    protected function config(): array
    {
        /** @var array<string, array{kind: TagKind, tags: array<string, string|null>}> */
        return config('catalogue.tag_presets');
    }
}
