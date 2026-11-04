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
            'resources' => ':Resources who can do this',
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

    'schedule' => [
        'this_date' => 'This date',
        'validation' => [
            'periods_overlap' => ':when has hours that overlap.',
            'same_time' => ':when has hours that start and end at the same time. Use 00:00 to 00:00 for the whole day.',
        ],
    ],

    'resources' => [
        'index' => [
            'title' => ':Resources',
            'create' => 'New :resource',
            'empty' => 'No :resources yet',
            'all_types' => 'All types',
        ],
        'create' => [
            'title' => 'New :resource',
            'submit' => 'Create :resource',
        ],
        'edit' => [
            'title' => 'Edit :resource',
        ],
        'show' => [
            'title' => 'View :resource',
        ],
        'columns' => [
            'name' => 'Name',
            'type' => 'Type',
            'location' => ':Location',
            'services' => ':Services',
            'active' => 'Active',
        ],
        'sections' => [
            'details' => 'Details',
            'working_hours' => 'Usual working hours',
            'working_hours_description' => 'Add a second period for a split day. An end before the start runs into the next day, and 00:00 to 00:00 is the whole day. The rota can change any single date.',
            'services' => ':Services',
            'services_description' => 'What this can do. Leave a price or length blank to use the :service\'s own.',
            'visibility' => 'Availability',
        ],
        'fields' => [
            'type' => 'Type',
            'name' => 'Name',
            'slug' => 'Slug',
            'slug_help' => 'Leave blank to make one from the name.',
            'description' => 'Description',
            'image' => 'Image',
            'job_title' => 'Job title',
            'user' => 'Signs in as',
            'user_help' => 'Lets them ask for their own time off.',
            'no_user' => 'Nobody',
            'capacity' => 'Holds',
            'capacity_help' => 'How many people it holds, e.g. the seats at a table.',
            'location' => ':Location',
            'no_location' => 'Not tied to one',
            'is_active' => 'Active',
            'is_active_help' => 'Turn off to leave it off the rota and out of :bookings.',
            'is_public' => 'Show on the site',
            'starts_at' => 'Starts',
            'ends_at' => 'Ends',
            'no_hours' => 'Not working',
            'add_hours' => 'Add hours',
            'remove_hours' => 'Remove hours',
            'copy_to_all' => 'Copy to every day',
        ],
        'services' => [
            'empty' => 'No :services to choose from yet.',
            'price' => 'Own price (:currency)',
            'duration_minutes' => 'Own length (minutes)',
        ],
        'active' => [
            'yes' => 'Yes',
            'no' => 'No',
        ],
        'confirm_delete' => 'Delete :name?',
        'flash' => [
            'created' => ':Resource created.',
            'updated' => ':Resource updated.',
            'deleted' => ':Resource deleted.',
        ],
    ],

    'rota' => [
        'title' => 'Rota',
        'empty' => 'No active :resources yet.',
        'resource' => ':Resource',
        'previous_week' => 'Previous week',
        'next_week' => 'Next week',
        'this_week' => 'This week',
        'off' => 'Off',
        'not_working' => '-',
        'changed' => 'Changed for this date',
        'edit_title' => ':name, :date',
        'edit_description' => 'These replace the usual working hours for this date. Remove them all to go back to the usual hours.',
        'add_shift' => 'Add a shift',
        'remove_shift' => 'Remove shift',
        'no_shifts' => 'Using the usual working hours.',
        'fields' => [
            'note' => 'Note',
        ],
        'flash' => [
            'updated' => 'Rota updated.',
        ],
    ],

    'time_off' => [
        'index' => [
            'title' => 'Time off',
            'create' => 'Add time off',
            'empty' => 'No time off yet',
            'all_statuses' => 'All statuses',
        ],
        'create' => [
            'title' => 'Add time off',
            'request_submit' => 'Ask for time off',
            'record_submit' => 'Add time off',
            'request_help' => 'This goes to a manager to approve.',
            'record_help' => 'This is approved straight away.',
            'none_linked' => 'Your sign in isn\'t linked to a :staff_member yet, so ask a manager to link it first.',
        ],
        'columns' => [
            'resource' => ':Resource',
            'type' => 'Type',
            'starts_on' => 'From',
            'ends_on' => 'To',
            'status' => 'Status',
            'reason' => 'Reason',
        ],
        'fields' => [
            'resource' => ':Resource',
            'type' => 'Type',
            'starts_on' => 'From',
            'ends_on' => 'To',
            'ends_on_help' => 'Leave blank for one day.',
            'reason' => 'Reason',
        ],
        'actions' => [
            'approve' => 'Approve',
            'decline' => 'Decline',
        ],
        'confirm_delete' => 'Delete this time off?',
        'validation' => [
            'not_yours' => 'You can only ask for your own time off.',
            'overlap' => 'There is already time off on these dates.',
        ],
        'flash' => [
            'requested' => 'Time off requested.',
            'recorded' => 'Time off added.',
            'approved' => 'Time off approved.',
            'declined' => 'Time off declined.',
            'deleted' => 'Time off deleted.',
        ],
    ],

    'staff_hours' => [
        'title' => ':Staff_member hours',
        'description' => 'Scheduled hours from working hours and the rota, less approved time off.',
        'empty' => 'No :staff_members yet.',
        'from' => 'From',
        'to' => 'To',
        'name' => 'Name',
        'job_title' => 'Job title',
        'hours' => 'Hours',
        'days_off' => 'Days off',
    ],
];
