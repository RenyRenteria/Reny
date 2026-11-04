<?php

return [
    'google' => [
        'enabled' => env('GOOGLE_ANALYTICS_ENABLED', env('APP_ENV') === 'production'),
        'measurement_id' => env('GOOGLE_ANALYTICS_MEASUREMENT_ID', 'G-VZW6QP0YSW'),
    ],
];
