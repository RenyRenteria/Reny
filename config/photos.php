<?php

return [
    'public_disk' => env('PHOTOS_PUBLIC_DISK', env('MEDIA_PUBLIC_DISK', 'public')),
    'private_disk' => env('PHOTOS_PRIVATE_DISK', env('MEDIA_PRIVATE_DISK', 'local')),

    'max_file_kb' => env('PHOTOS_MAX_FILE_KB', 20 * 1024),
    'max_batch_files' => env('PHOTOS_MAX_BATCH_FILES', 100),
    'large_batch_threshold' => env('PHOTOS_LARGE_BATCH_THRESHOLD', 15),

    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],

    'variants' => [
        'optimized_max_width' => env('PHOTOS_OPTIMIZED_MAX_WIDTH', 1800),
        'thumbnail_max_width' => env('PHOTOS_THUMBNAIL_MAX_WIDTH', 480),
        'blur_max_width' => env('PHOTOS_BLUR_MAX_WIDTH', 900),
        'blur_downsample_width' => env('PHOTOS_BLUR_DOWNSAMPLE_WIDTH', 180),
        'jpeg_quality' => env('PHOTOS_JPEG_QUALITY', 82),
        'thumbnail_quality' => env('PHOTOS_THUMBNAIL_QUALITY', 76),
        'blur_quality' => env('PHOTOS_BLUR_QUALITY', 74),
        'blur_passes' => env('PHOTOS_BLUR_PASSES', 4),
    ],
];
