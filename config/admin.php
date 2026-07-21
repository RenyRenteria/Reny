<?php

// Private-by-default admin path. Overridable per environment via ADMIN_PATH so the
// secret can be rotated without a code deploy. Kept off the predictable /admin prefix
// to avoid automated /admin scanners; the login + access middleware remain the real lock.
$defaultAdminPath = '7YDX5h38a6Q2sfrsW2pRv9CoU59RA5YWD2R7K3AuMA';
$adminPath = trim(trim((string) env('ADMIN_PATH', $defaultAdminPath)), '/');
$cmsEnabled = env('ADMIN_CMS_ENABLED');

return [
    'path' => $adminPath !== '' ? $adminPath : $defaultAdminPath,
    'cms_enabled' => $cmsEnabled === null || $cmsEnabled === ''
        ? true
        : filter_var($cmsEnabled, FILTER_VALIDATE_BOOLEAN),
    'session_lifetime_minutes' => (int) env('ADMIN_SESSION_LIFETIME', env('SESSION_LIFETIME', 120)),
    'publishing_timezone' => env('ADMIN_PUBLISHING_TIMEZONE', 'America/Panama'),
    'community_editor_email' => env('ADMIN_COMMUNITY_EDITOR_EMAIL', 'reny@portierstrategy.com'),
];
