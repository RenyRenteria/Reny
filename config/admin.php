<?php

$defaultAdminPath = '7YDX5h38a6Q2sfrsW2pRv9CoU59RA5YWD2R7K3AuMA';
$adminPath = trim(trim((string) env('ADMIN_PATH', $defaultAdminPath)), '/');

return [
    'path' => $adminPath !== '' ? $adminPath : $defaultAdminPath,
    'cms_enabled' => filter_var(env('ADMIN_CMS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'session_lifetime_minutes' => (int) env('ADMIN_SESSION_LIFETIME', env('SESSION_LIFETIME', 120)),
    'publishing_timezone' => env('ADMIN_PUBLISHING_TIMEZONE', 'America/Panama'),
];
