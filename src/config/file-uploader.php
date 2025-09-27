<?php

return [
    'storage' => [
        'disk' => 'public',
        'path' => 'files',
        'url_path' => 'storage/files',
    ],
    
    'validation' => [
        'file' => [
            'max_size' => 5120, // 5MB in KB, default
            'mimes' => [
                'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
                'video' => ['mp4', 'mkv', 'avi', 'mov'],
                'pdf' => ['pdf'],
                'document' => ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
                'excel' => ['xls', 'xlsx', 'csv'],
                'any' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'mp4', 'mkv', 'avi', 'mov', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv']
            ],
        ],
        'url' => [
            'timeout' => 30,
            'max_size' => 5120, // 5MB in KB, default
            'allowed_mimes' => [
                'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
                'video' => ['mp4', 'mkv', 'avi', 'mov'],
                'pdf' => ['pdf'],
                'document' => ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
                'excel' => ['xls', 'xlsx', 'csv'],
                'any' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'mp4', 'mkv', 'avi', 'mov', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv']
            ],
            'user_agent' => 'Laravel File Uploader/1.0',
        ],
    ],
    
    'database' => [
        'table' => 'files',
        'model' => \StalkArtisan\LaravelFileUploader\Models\File::class,
    ],
    
    'naming' => [
        'strategy' => 'random', // random, original, timestamp
        'length' => 40,
    ],
];