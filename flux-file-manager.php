<?php

return [
    'disk' => env('FLUX_FILE_MANAGER_DISK', 'filemanager'),

    'folders' => [
        'file' => [
            'folder_name' => 'files',
            'max_size' => 50000, // size in KB
            'valid_mimes' => [
                'application/pdf',
                'text/plain',
            ],
        ],
        'image' => [
            'folder_name' => 'images',
            'max_size' => 50000, // size in KB
            'valid_mimes' => [
                'jpeg',
                'jpg',
                'png',
                'gif',
            ],
        ],
    ],
];
