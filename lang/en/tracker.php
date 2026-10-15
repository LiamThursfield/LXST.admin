<?php

/*
|--------------------------------------------------------------------------
| Tracker Language Lines
|--------------------------------------------------------------------------
|
| Strings for the Tracker module (tasks, categories, holidays and the Today page).
| Enum labels (periods, units, statuses etc.) live in enums.php.
|
*/

return [
    'save_changes' => 'Save Changes',

    'flash' => [
        'entry_logged' => 'Progress logged.',
        'entry_removed' => 'Entry removed.',
    ],

    'validation' => [
        'not_scheduled' => 'This task is not scheduled for that date.',
    ],

    'tasks' => [
        'columns' => [
            'title' => 'Title',
            'period' => 'Period',
            'status' => 'Status',
        ],
        'fields' => [
            'title' => 'Title',
            'description' => 'Description',
            'category' => 'Category',
            'status' => 'Status',
            'measure' => 'Measure',
            'unit' => 'Unit',
            'comparator' => 'Comparator',
            'aggregation' => 'Aggregation',
            'target_min' => 'Target (min)',
            'target_max' => 'Target (max)',
            'stepped' => 'Stepped entries',
            'counts_during_holiday' => 'Counts during holiday',
            'period' => 'Period',
            'active_from' => 'Start date',
            'active_to' => 'End date',
            'days_of_week' => 'Days of week',
            'months_of_year' => 'Months of year',
        ],
        'sections' => [
            'measurement' => 'Measurement',
            'schedule' => 'Schedule',
        ],
        'index' => [
            'title' => 'Tasks',
            'create' => 'New task',
        ],
        'create' => [
            'title' => 'Create Task',
            'submit' => 'Create Task',
        ],
        'edit' => [
            'title' => 'Edit Task: :title',
        ],
        'show' => [
            'title' => 'Task: :title',
        ],
        'validation' => [
            'title_required' => 'Title is required',
            'period_required' => 'Period is required',
            'measure_required' => 'Measure is required',
            'unit_required' => 'Unit is required',
            'comparator_required' => 'Comparator is required',
            'aggregation_required' => 'Aggregation is required',
            'active_from_required' => 'Start date is required',
            'status_required' => 'Status is required',
        ],
        'confirm_delete' => 'Are you sure you want to delete task ":title"?',
        'flash' => [
            'created' => 'Task created successfully.',
            'updated' => 'Task updated successfully.',
            'deleted' => 'Task deleted successfully.',
        ],
    ],

    'categories' => [
        'columns' => [
            'name' => 'Name',
            'is_public' => 'Visibility',
        ],
        'fields' => [
            'name' => 'Name',
            'colour' => 'Colour',
            'icon' => 'Icon',
            'is_public' => 'Public',
            'is_public_description' => 'Public categories are visible to everyone.',
        ],
        'visibility' => [
            'public' => 'Public',
            'private' => 'Private',
        ],
        'index' => [
            'title' => 'Categories',
            'create' => 'New category',
        ],
        'create' => [
            'title' => 'Create Category',
            'submit' => 'Create Category',
        ],
        'edit' => [
            'title' => 'Edit Category: :name',
        ],
        'show' => [
            'title' => 'Category: :name',
        ],
        'validation' => [
            'name_required' => 'Name is required',
        ],
        'confirm_delete' => 'Are you sure you want to delete category :name?',
        'flash' => [
            'created' => 'Category created successfully.',
            'updated' => 'Category updated successfully.',
            'deleted' => 'Category deleted successfully.',
        ],
    ],

    'holidays' => [
        'columns' => [
            'label' => 'Label',
            'starts_on' => 'Starts',
            'ends_on' => 'Ends',
        ],
        'fields' => [
            'label' => 'Label',
            'label_placeholder' => 'e.g. Italy trip',
            'starts_on' => 'Start date',
            'ends_on' => 'End date',
        ],
        'index' => [
            'title' => 'Holidays',
            'create' => 'New holiday',
        ],
        'create' => [
            'title' => 'Create Holiday',
            'submit' => 'Create Holiday',
        ],
        'edit' => [
            'title' => 'Edit Holiday',
        ],
        'show' => [
            'title' => 'Holiday',
        ],
        'validation' => [
            'starts_on_required' => 'Start date is required',
            'ends_on_required' => 'End date is required',
        ],
        'confirm_delete' => 'Are you sure you want to delete this holiday?',
        'flash' => [
            'created' => 'Holiday created successfully.',
            'updated' => 'Holiday updated successfully.',
            'deleted' => 'Holiday deleted successfully.',
        ],
    ],

    'today' => [
        'title' => 'Today',
        'previous_day' => 'Previous day',
        'next_day' => 'Next day',
        'summary' => ':met of :total met',
        'empty_title' => 'Nothing scheduled',
        'empty_description' => 'No tasks are due on this day.',
        'holiday' => 'Holiday',
        'mark_done' => 'Mark done',
        'undo' => 'Undo',
        'add_amount' => 'Add amount',
        'value' => 'Value',
        'add' => 'Add',
        'remove_entry' => 'Remove entry',
        'periods' => [
            'weekly' => 'This week',
            'monthly' => 'This month',
            'yearly' => 'This year',
            'lifetime' => 'Lifetime',
            'one-off' => 'One-off',
        ],
        'progress' => [
            'min' => ':current of :min',
            'max' => ':current · max :max',
            'between' => ':current · target :min – :max',
        ],
    ],

    'occurrence_statuses' => [
        'met' => 'Met',
        'missed' => 'Missed',
        'on-track' => 'On track',
        'pending' => 'Pending',
        'skipped' => 'Skipped',
    ],

    'units' => [
        'symbols' => [
            'kg' => 'kg',
            'lb' => 'lb',
            'l' => 'L',
            'ml' => 'ml',
            'minutes' => 'min',
            'hours' => 'h',
            'kcal' => 'kcal',
        ],
    ],

    'days_of_week' => [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
    ],

    'months_of_year' => [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Aug',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dec',
    ],
];
