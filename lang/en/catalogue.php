<?php

/*
|--------------------------------------------------------------------------
| Catalogue Language Lines
|--------------------------------------------------------------------------
|
| Menus, price lists and products (#183). :catalogue and :catalogues are the tenant's own word,
| e.g. "menu" for a restaurant or "price list" for a salon: see lang/en/industries.php.
|
*/

return [
    'catalogues' => [
        'index' => [
            'title' => ':Catalogues',
            'create' => 'New :catalogue',
            'empty' => 'No :catalogues yet',
            'search' => 'Search by name',
        ],
        'create' => [
            'title' => 'New :catalogue',
            'submit' => 'Create :catalogue',
        ],
        'edit' => [
            'title' => 'Edit :catalogue',
        ],
        'columns' => [
            'name' => 'Name',
            'contents' => 'Contents',
            'schedule' => 'When',
            'status' => 'Status',
        ],
        'cards' => [
            'details' => 'Details',
            'schedule' => 'When it applies',
            'schedule_help' => 'Leave these blank for a :catalogue that always applies. Websites show the :catalogues that apply at the time.',
            'contents' => 'Sections and items',
            'contents_help' => 'Drag sections and items to reorder them. Items can be dragged into another section.',
        ],
        'fields' => [
            'name' => 'Name',
            'slug' => 'Web address name',
            'slug_help' => 'Used in its link. Made from the name if left blank.',
            'description' => 'Description',
            'description_help' => 'Shown under the name, e.g. "Served from noon until 3pm".',
            'is_published' => 'Published',
            'is_published_help' => 'Only published :catalogues are shown on the website.',
            'available_days' => 'Days',
            'available_days_help' => 'None chosen is every day.',
            'available_from' => 'From',
            'available_until' => 'Until',
            'times_help' => 'An end before the start runs past midnight, e.g. 22:00 to 02:00.',
            'starts_on' => 'First day',
            'ends_on' => 'Last day',
            'dates_help' => 'For a seasonal :catalogue. Either can be left blank.',
        ],
        'status' => [
            'published' => 'Published',
            'draft' => 'Draft',
            'applies_now' => 'Applies now',
            'not_now' => 'Not now',
        ],
        'sections_count' => '{1} :count section|[0,*] :count sections',
        'items_count' => '{1} :count item|[0,*] :count items',
        'always' => 'Always',
        'edit_button' => 'Edit',
        'confirm_delete' => 'Delete the :catalogue ":name" and everything in it? This can\'t be undone.',
        'flash' => [
            'created' => ':Catalogue created.',
            'updated' => ':Catalogue updated.',
            'deleted' => ':Catalogue deleted.',
        ],
    ],

    'sections' => [
        'add' => 'Add section',
        'name' => 'Section name',
        'description' => 'Section description',
        'remove' => 'Remove section',
        'empty' => 'No sections yet. Add one to start adding items.',
        'no_items' => 'No items in this section yet.',
        'drag' => 'Drag to reorder',
        'new' => 'New section',
    ],

    'items' => [
        'add' => 'Add item',
        'edit' => 'Edit item',
        'new' => 'New item',
        'remove' => 'Remove item',
        'done' => 'Done',
        'fields' => [
            'name' => 'Name',
            'description' => 'Description',
            'service' => ':Service',
            'service_help' => 'Show one of your :services. Its price is used, and it can be booked from here once online booking is on.',
            'no_service' => 'None',
            'price' => 'Price (:currency)',
            'price_help' => 'Leave blank to show no price.',
            'image' => 'Image',
            'tags' => 'Tags',
            'tags_help' => 'Allergens, dietary labels and your own. Manage them under Tags.',
            'is_available' => 'Available',
            'is_available_help' => 'Turn off when it\'s sold out for now.',
            'tracks_stock' => 'Count stock',
            'tracks_stock_help' => 'Shows as out of stock when none are left.',
            'stock' => 'In stock',
            'variations' => 'Variations',
            'variations_help' => 'Sizes or options with their own prices, e.g. small and large, or a glass and a bottle.',
            'variation_name' => 'Name',
            'variation_price' => 'Price (:currency)',
            'variation_stock' => 'In stock',
        ],
        'add_variation' => 'Add variation',
        'remove_variation' => 'Remove variation',
        'price_from' => 'from :price',
        'service_price' => ':Service price',
        'no_price' => 'No price',
        'sold_out' => 'Sold out',
        'out_of_stock' => 'Out of stock',
        'stock_left' => ':count left',
    ],

    'stock' => [
        'available' => 'Available',
        'stock' => 'In stock',
        'flash' => [
            'updated' => ':name updated.',
        ],
    ],

    'tags' => [
        'index' => [
            'title' => 'Tags',
            'create' => 'New tag',
            'empty' => 'No tags yet',
            'search' => 'Search by name',
        ],
        'create' => [
            'title' => 'New tag',
            'submit' => 'Create tag',
        ],
        'edit' => [
            'title' => 'Edit tag',
        ],
        'columns' => [
            'name' => 'Name',
            'kind' => 'Kind',
            'items' => 'Items',
        ],
        'fields' => [
            'name' => 'Name',
            'kind' => 'Kind',
            'kind_help' => 'Allergens and dietary labels are shown apart from other tags.',
            'icon' => 'Icon',
            'no_icon' => 'No icon',
        ],
        'presets' => [
            'add' => 'Add built-in tags',
            'suggested' => 'Suggested',
            'all_added' => 'All added',
            'missing' => ':count to add',
        ],
        'confirm_delete' => 'Delete the tag ":name"? It\'s taken off every item.',
        'flash' => [
            'created' => 'Tag created.',
            'updated' => 'Tag updated.',
            'deleted' => 'Tag deleted.',
            'presets_added' => '{0} Every tag in :group was already added.|{1} 1 tag added from :group.|[2,*] :count tags added from :group.',
        ],
    ],

    'schedule' => [
        'every_day' => 'Every day',
        'days' => [
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
            7 => 'Sun',
        ],
        'from_time' => 'From :time',
        'until_time' => 'Until :time',
        'from_date' => 'From :date',
        'until_date' => 'Until :date',
        'between_dates' => ':start to :end',
    ],

    'presets' => [
        'groups' => [
            'allergens' => 'Allergens',
            'dietary' => 'Dietary',
        ],
        'tags' => [
            'celery' => 'Celery',
            'gluten' => 'Gluten',
            'crustaceans' => 'Crustaceans',
            'eggs' => 'Eggs',
            'fish' => 'Fish',
            'lupin' => 'Lupin',
            'milk' => 'Milk',
            'molluscs' => 'Molluscs',
            'mustard' => 'Mustard',
            'tree_nuts' => 'Nuts',
            'peanuts' => 'Peanuts',
            'sesame' => 'Sesame',
            'soya' => 'Soya',
            'sulphites' => 'Sulphites',
            'vegan' => 'Vegan',
            'vegetarian' => 'Vegetarian',
            'gluten_free' => 'Gluten-free',
            'dairy_free' => 'Dairy-free',
            'nut_free' => 'Nut-free',
            'halal' => 'Halal',
            'kosher' => 'Kosher',
            'spicy' => 'Spicy',
        ],
    ],

    'validation' => [
        'section_not_found' => 'A section was removed by someone else. Reload the page and try again.',
        'item_not_found' => 'An item was removed by someone else. Reload the page and try again.',
    ],

    'attributes' => [
        'sections.*.name' => 'section name',
        'sections.*.items.*.name' => 'item name',
        'sections.*.items.*.price' => 'price',
        'sections.*.items.*.stock' => 'stock',
        'sections.*.items.*.service_id' => 'service',
        'sections.*.items.*.variations.*.name' => 'variation name',
        'sections.*.items.*.variations.*.price' => 'variation price',
        'sections.*.items.*.variations.*.stock' => 'variation stock',
    ],
];
