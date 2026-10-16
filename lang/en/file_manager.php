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
    'picker' => [
        'title' => 'Choose a file',
        'title_image' => 'Choose an image',
        'choose' => 'Choose',
        'change' => 'Change',
        'remove' => 'Remove',
        'none' => 'No file chosen',
        'none_image' => 'No image chosen',
        'empty' => 'Nothing to choose here yet.',
        'empty_images' => 'No images here yet.',
        'upload' => 'Upload here',
        'missing' => 'This file no longer exists.',
        'error' => 'The files could not be loaded.',
    ],
    'files' => [
        'empty' => 'This folder is empty.',
        'upload' => 'Upload',
        'upload_title' => 'Upload files',
        'upload_description' => 'Files will be added to :folder.',
        'upload_hint' => 'Up to :count files, :size MB each. Allowed: :types.',
        'upload_submit' => '{0} Upload|{1} Upload :count file|[2,*] Upload :count files',
        'details' => 'File details',
        'preview' => 'Preview',
        'type' => 'Type',
        'size' => 'Size',
        'added' => 'Added',
        'location' => 'Location',
        'url' => 'URL',
        'copy_url' => 'Copy URL',
        'copied' => 'URL copied',
        'open' => 'Open file',
        'alt_text' => 'Alt text',
        'alt_text_help' => 'Describes the image for people using screen readers. Used wherever the image is shown.',
        'save' => 'Save',
        'validation' => [
            'type' => 'This type of file can\'t be uploaded. Allowed: :types.',
            'size' => 'This file is too large. The limit is :size MB.',
        ],
        'flash' => [
            'uploaded' => '{1} File uploaded.|[2,*] :count files uploaded.',
            'updated' => 'File details saved.',
        ],
        'columns' => [
            'name' => 'Name',
            'type' => 'Type',
            'size' => 'Size',
            'added' => 'Added',
        ],
    ],
];
