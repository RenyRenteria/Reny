<?php

$defaultAdminPath = 'admin';
$adminPath = trim(trim((string) env('ADMIN_PATH', $defaultAdminPath)), '/');
$cmsEnabled = env('ADMIN_CMS_ENABLED');

return [
    'path' => $adminPath !== '' ? $adminPath : $defaultAdminPath,
    'cms_enabled' => $cmsEnabled === null || $cmsEnabled === ''
        ? true
        : filter_var($cmsEnabled, FILTER_VALIDATE_BOOLEAN),
    'session_lifetime_minutes' => (int) env('ADMIN_SESSION_LIFETIME', env('SESSION_LIFETIME', 120)),
    'publishing_timezone' => env('ADMIN_PUBLISHING_TIMEZONE', 'America/Panama'),
];
