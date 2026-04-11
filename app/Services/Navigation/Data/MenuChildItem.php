<?php

namespace App\Services\Navigation\Data;

use App\Data\Transformers\RouteTransformer;
use App\Data\Transformers\SortCollectionTransformer;
use App\Data\Transformers\StringReplaceTransformer;
use App\Services\Navigation\Traits\HasChildren;
use App\Services\Navigation\Traits\HasVisibility;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

/**
 * A MenuChildItem is a PHP representation of Nuxt UI's NavigationMenuChildItem
 * with some 'internal' properties allowing for building and sorting menus
 *
 * @see https://ui.nuxt.com/docs/components/navigation-menu
 */
class MenuChildItem extends Data
{
    use HasChildren;
    use HasVisibility;

    /**
     * @param  Collection<int, MenuChildItem>|null  $children
     */
    public function __construct(
        // Internal properties
        #[WithTransformer(StringReplaceTransformer::class, search: '.', replace: '')]
        public string $key,
        public int $sortOrder,

        // Properties for Nuxt UI's NavigationMenuChildItem
        public ?string $label = null,
        #[WithTransformer(RouteTransformer::class)]
        public ?string $to = null,
        public ?string $icon = null,
        public ?string $description = null,
        #[DataCollectionOf(MenuChildItem::class)]
        #[WithTransformer(SortCollectionTransformer::class, sortBy: 'sortOrder')]
        public ?Collection $children = null,
    ) {
        if ($children === null) {
            $this->children = collect();
        }
    }
}
