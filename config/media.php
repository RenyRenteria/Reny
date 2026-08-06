<?php

use App\Enums\MediaAssetType;

return [
    'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),

    'batch_limit_bytes' => 10 * 1024 * 1024 * 1024,
    'short_video_duration_seconds' => 20 * 60,

    'types' => [
        MediaAssetType::Image->value => [
            'max_bytes' => 50 * 1024 * 1024,
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        ],
        MediaAssetType::Video->value => [
            'max_bytes' => 512 * 1024 * 1024,
            'extensions' => ['mp4', 'mov', 'webm'],
            'mime_types' => ['video/mp4', 'video/quicktime', 'video/webm'],
        ],
        MediaAssetType::Audio->value => [
            'max_bytes' => 1024 * 1024 * 1024,
            'extensions' => ['mp3', 'wav', 'm4a', 'aac', 'flac'],
            'mime_types' => ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/aac', 'audio/flac'],
        ],
        MediaAssetType::ShortVideo->value => [
            'max_bytes' => 5 * 1024 * 1024 * 1024,
            'extensions' => ['mp4', 'mov', 'webm'],
            'mime_types' => ['video/mp4', 'video/quicktime', 'video/webm'],
        ],
        MediaAssetType::Document->value => [
            'max_bytes' => 100 * 1024 * 1024,
            'extensions' => ['pdf', 'txt', 'doc', 'docx'],
            'mime_types' => [
                'application/pdf',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ],
        MediaAssetType::ProductAsset->value => [
            'max_bytes' => 250 * 1024 * 1024,
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'zip'],
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'application/zip', 'application/x-zip-compressed'],
        ],
        MediaAssetType::Thumbnail->value => [
            'max_bytes' => 20 * 1024 * 1024,
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
    ],
];
