<?php

namespace App\Data\Cms;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * One item in a CMS menu. It links either to a page (by id, so the link follows the page if its
 * URL changes) or to a custom URL, and can have its own child items.
 */
class MenuItemData extends Data
{
    public const string LINK_PAGE = 'page';

    public const string LINK_URL = 'url';

    /**
     * How many levels deep items can go (top-level items are level 1)
     */
    public const int MAX_DEPTH = 3;

    /**
     * @param  list<MenuItemData>  $children
     */
    public function __construct(
        public string $label,
        /** LINK_PAGE or LINK_URL */
        public string $link_type = self::LINK_URL,
        public ?int $page_id = null,
        public ?string $url = null,
        /** _self or _blank */
        public ?string $target = null,
        public ?string $rel = null,
        public ?string $custom_class = null,
        #[DataCollectionOf(MenuItemData::class)]
        public array $children = [],
    ) {}
}
