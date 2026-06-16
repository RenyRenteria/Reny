<?php

return [
    'session_lifetime_minutes' => (int) env('ADMIN_SESSION_LIFETIME', env('SESSION_LIFETIME', 120)),
    'publishing_timezone' => env('ADMIN_PUBLISHING_TIMEZONE', 'America/Panama'),
];
