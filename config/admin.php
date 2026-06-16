<?php

return [
    'session_lifetime_minutes' => (int) env('ADMIN_SESSION_LIFETIME', env('SESSION_LIFETIME', 120)),
];
