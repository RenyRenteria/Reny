<?php

return [
    'enabled' => env('YOYCOL_ENABLED', false),
    'access_key' => env('YOYCOL_ACCESS_KEY'),
    'secret_key' => env('YOYCOL_SECRET_KEY'),
    'quote_minutes' => 15,
    // Region names from sku_quotes; countries verified against https://www.yoycol.com/helpcenter/4/228.
    'countries' => [
        'PA' => ['name' => 'Panama', 'region' => 'A area'],
        'MX' => ['name' => 'Mexico', 'region' => 'B area'],
        'CO' => ['name' => 'Colombia', 'region' => 'C area'],
        'CR' => ['name' => 'Costa Rica', 'region' => 'C area'],
        'ES' => ['name' => 'Spain', 'region' => 'A area'],
        'AR' => ['name' => 'Argentina', 'region' => 'C area'],
        'CL' => ['name' => 'Chile', 'region' => 'C area'],
        'PE' => ['name' => 'Peru', 'region' => 'C area'],
        'EC' => ['name' => 'Ecuador', 'region' => 'A area'],
        'DO' => ['name' => 'Dominican Republic', 'region' => 'A area'],
        'US' => ['name' => 'United States', 'region' => 'US'],
        'CA' => ['name' => 'Canada', 'region' => 'Canada'],
        'GB' => ['name' => 'United Kingdom', 'region' => 'GB/DE/FR'],
        'DE' => ['name' => 'Germany', 'region' => 'GB/DE/FR'],
        'FR' => ['name' => 'France', 'region' => 'GB/DE/FR'],
        'AU' => ['name' => 'Australia', 'region' => 'Australia'],
        'CN' => ['name' => 'China', 'region' => 'China'],
    ],
];
