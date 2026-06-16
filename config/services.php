<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'paypal' => [
        'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.paypal.com'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    'mux' => [
        'base_url' => env('MUX_BASE_URL', 'https://api.mux.com'),
        'token_id' => env('MUX_TOKEN_ID'),
        'token_secret' => env('MUX_TOKEN_SECRET'),
        'webhook_secret' => env('MUX_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => env('MUX_WEBHOOK_TOLERANCE_SECONDS', 300),
        'cors_origin' => env('MUX_CORS_ORIGIN'),
        'playback_policy' => env('MUX_PLAYBACK_POLICY', 'public'),
        'video_quality' => env('MUX_VIDEO_QUALITY', 'basic'),
    ],

];
