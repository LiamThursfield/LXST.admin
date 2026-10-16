<?php

/*
|--------------------------------------------------------------------------
| File Manager Language Lines
|--------------------------------------------------------------------------
|
| Strings for the File Manager.
|
*/

return [
    'title' => 'Files',
    'top_level' => 'All files',
    'folders' => [
        'title' => 'Folders',
        'empty' => 'No folders yet.',
        'create' => 'New folder',
        'create_title' => 'New folder',
        'create_description' => 'The folder will be created inside :folder.',
        'name' => 'Folder name',
        'submit' => 'Create folder',
        'validation' => [
            'name_characters' => 'Use only letters, numbers, spaces, dashes, underscores and dots, and don\'t start with a dot.',
            'name_taken' => 'There is already a folder with this name here.',
        ],
        'flash' => [
            'created' => 'Folder created.',
        ],
    ],
    'files' => [
        'empty' => 'This folder is empty.',
        'columns' => [
            'name' => 'Name',
            'type' => 'Type',
            'size' => 'Size',
            'added' => 'Added',
        ],
    ],
];
