<?php

use App\Enums\Catalogue\TagKind;

/*
|--------------------------------------------------------------------------
| Catalogue
|--------------------------------------------------------------------------
|
| The built-in tags a tenant can add to its catalogue in one go, in groups, with their names in
| lang/en/catalogue.php under `presets`. Once added they're the tenant's own, to rename or remove.
| An industry suggests groups through `catalogue_tags` in config/industries.php.
|
*/

return [
    'tag_presets' => [
        // The 14 allergens UK food businesses must declare
        'allergens' => [
            'kind' => TagKind::Allergen,
            'tags' => [
                'celery' => null,
                'gluten' => 'i-lucide-wheat',
                'crustaceans' => 'i-lucide-shell',
                'eggs' => 'i-lucide-egg',
                'fish' => 'i-lucide-fish',
                'lupin' => null,
                'milk' => 'i-lucide-milk',
                'molluscs' => 'i-lucide-shell',
                'mustard' => null,
                'tree_nuts' => 'i-lucide-nut',
                'peanuts' => 'i-lucide-nut',
                'sesame' => null,
                'soya' => 'i-lucide-bean',
                'sulphites' => null,
            ],
        ],
        'dietary' => [
            'kind' => TagKind::Dietary,
            'tags' => [
                'vegan' => 'i-lucide-leaf',
                'vegetarian' => 'i-lucide-sprout',
                'gluten_free' => 'i-lucide-wheat-off',
                'dairy_free' => 'i-lucide-milk-off',
                'nut_free' => 'i-lucide-nut-off',
                'halal' => null,
                'kosher' => null,
                'spicy' => 'i-lucide-flame',
            ],
        ],
    ],

    // The icons a tenant can give its own tags
    'tag_icons' => [
        'i-lucide-star',
        'i-lucide-sparkles',
        'i-lucide-heart',
        'i-lucide-award',
        'i-lucide-thumbs-up',
        'i-lucide-flame',
        'i-lucide-snowflake',
        'i-lucide-leaf',
        'i-lucide-sprout',
        'i-lucide-recycle',
        'i-lucide-gift',
        'i-lucide-clock',
        'i-lucide-baby',
        'i-lucide-accessibility',
        'i-lucide-triangle-alert',
        'i-lucide-wheat',
        'i-lucide-wheat-off',
        'i-lucide-milk',
        'i-lucide-milk-off',
        'i-lucide-egg',
        'i-lucide-fish',
        'i-lucide-nut',
        'i-lucide-nut-off',
        'i-lucide-bean',
        'i-lucide-shell',
        'i-lucide-wine',
        'i-lucide-beer',
        'i-lucide-coffee',
    ],
];
