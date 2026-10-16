<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File Manager
    |--------------------------------------------------------------------------
    |
    | The File Manager stores each tenant's files on the `file_manager` disk
    | (config/filesystems.php) and keeps a database record of every folder and
    | file, so content can refer to files by id.
    |
    | Uploading and creating folders can be switched off, e.g. on a demo site.
    |
    */

    'disk' => 'file_manager',

    'uploads_enabled' => (bool) env('FILE_MANAGER_UPLOADS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | The file extensions that can be uploaded, and the largest file allowed
    | (in kilobytes). Files served from the tenant's domain are sandboxed (see
    | TenancyServiceProvider), so scripts inside e.g. an SVG can't run.
    |
    */

    'allowed_extensions' => [
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        // Documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
        // Audio and video
        'mp3', 'mp4',
    ],

    'max_size_kb' => (int) env('FILE_MANAGER_MAX_SIZE_KB', 20 * 1024),

    // The most files that can be uploaded at once
    'max_files_per_upload' => 20,

];
