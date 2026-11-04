<?php

/*
|--------------------------------------------------------------------------
| Bookings Language Lines
|--------------------------------------------------------------------------
|
| Strings for the Bookings module. Words such as :service are the tenant's own: see
| lang/en/industries.php. Enum labels (booking types, price types etc.) live in enums.php.
|
*/

return [
    'services' => [
        'index' => [
            'title' => ':Services',
            'create' => 'New :service',
            'empty' => 'No :services yet',
        ],
        'create' => [
            'title' => 'New :service',
            'submit' => 'Create :service',
        ],
        'edit' => [
            'title' => 'Edit :service',
        ],
        'show' => [
            'title' => 'View :service',
        ],
        'columns' => [
            'name' => 'Name',
            'category' => 'Category',
            'booking_type' => 'How it\'s booked',
            'price' => 'Price',
            'online' => 'Online',
        ],
        'sections' => [
            'details' => 'Details',
            'booking' => 'How it\'s booked',
            'price' => 'Price',
            'options' => 'Variations and add-ons',
            'options_description' => ':Customers choose one variation, and any add-ons.',
            'availability' => 'Where it\'s offered',
        ],
        'fields' => [
            'name' => 'Name',
            'slug' => 'Slug',
            'slug_help' => 'Leave blank to make one from the name.',
            'description' => 'Description',
            'category' => 'Category',
            'no_category' => 'No category',
            'image' => 'Image',
            'booking_type' => 'Booking type',
            'price_type' => 'Pricing',
            'price' => 'Price (:currency)',
            'price_per_unit' => 'Price per unit (:currency)',
            'duration_minutes' => 'Length (minutes)',
            'buffer_before_minutes' => 'Time before (minutes)',
            'buffer_after_minutes' => 'Time after (minutes)',
            'buffers_help' => 'Kept free around each :booking, e.g. to set up or clear away.',
            'capacity' => 'Places',
            'min_party_size' => 'Smallest party',
            'max_party_size' => 'Largest party',
            'hire_unit' => 'Hired',
            'min_hire_units' => 'Shortest hire',
            'max_hire_units' => 'Longest hire',
            'max_hire_units_help' => 'Leave blank for no limit.',
            'travel_minutes' => 'Travel time (minutes)',
            'travel_help' => 'Allowed for getting to each :booking.',
            'is_bookable_online' => 'Can be booked online',
            'is_bookable_online_help' => 'Turn off to take these only in person or by phone.',
            'locations' => ':Locations',
            'locations_help' => 'Leave blank to offer at every :location.',
        ],
        'options' => [
            'add' => 'Add a variation or add-on',
            'remove' => 'Remove',
            'empty' => 'None yet',
            'type' => 'Type',
            'name' => 'Name',
            'price_adjustment' => 'Price change (:currency)',
            'duration_adjustment' => 'Extra minutes',
            'adjustment_help' => 'Added to the :service. Use a minus number to take off.',
        ],
        'price_display' => [
            'from' => 'From :price',
            'on_quote' => 'On quote',
        ],
        'online' => [
            'yes' => 'Yes',
            'no' => 'No',
        ],
        'confirm_delete' => 'Delete :name?',
        'flash' => [
            'created' => ':Service created.',
            'updated' => ':Service updated.',
            'deleted' => ':Service deleted.',
        ],
    ],

    'categories' => [
        'index' => [
            'title' => 'Categories',
            'create' => 'New category',
            'empty' => 'No categories yet',
        ],
        'create' => [
            'title' => 'New category',
            'submit' => 'Create category',
        ],
        'edit' => [
            'title' => 'Edit category',
        ],
        'columns' => [
            'name' => 'Name',
            'services' => ':Services',
            'order' => 'Order',
        ],
        'fields' => [
            'name' => 'Name',
            'order' => 'Order',
            'order_help' => 'Lower numbers come first.',
        ],
        'confirm_delete' => 'Delete :name? Its :services will stay, without a category.',
        'flash' => [
            'created' => 'Category created.',
            'updated' => 'Category updated.',
            'deleted' => 'Category deleted.',
        ],
    ],
];
