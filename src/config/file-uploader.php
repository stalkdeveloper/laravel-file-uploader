<?php

return [
    'storage' => [
        'disk' => 'public',
        'path' => 'files',
        'url_path' => 'storage/files',
    ],
    
    'defaults' => [
        'max_size' => 5120, // 5MB in KB
        'file_type' => 'any',
    ],
    
    'file_types' => [
        'image' => [
            'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
            'max_size' => 5120, // 5MB
        ],
        'video' => [
            'mimes' => ['mp4', 'mkv', 'avi', 'mov', 'webm'],
            'max_size' => 51200, // 50MB
        ],
        'pdf' => [
            'mimes' => ['pdf'],
            'max_size' => 10240, // 10MB
        ],
        'document' => [
            'mimes' => ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'],
            'max_size' => 20480, // 20MB
        ],
        'excel' => [
            'mimes' => ['xls', 'xlsx', 'csv'],
            'max_size' => 10240, // 10MB
        ],
        'audio' => [
            'mimes' => ['mp3', 'wav', 'ogg', 'aac', 'flac'],
            'max_size' => 20480, // 20MB
        ],
        'archive' => [
            'mimes' => ['zip', 'rar', '7z', 'tar', 'gz'],
            'max_size' => 51200, // 50MB
        ],
        'any' => [
            'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'mp4', 'mkv', 'avi', 'mov', 'webm', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv', 'mp3', 'wav', 'zip', 'rar'],
            'max_size' => 5120, // 5MB
        ],
    ],
    
    'validation' => [
        'url' => [
            'timeout' => 60,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
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