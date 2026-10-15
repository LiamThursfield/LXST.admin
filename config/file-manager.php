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

];
