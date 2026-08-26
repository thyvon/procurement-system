<?php

return [
    'name' => 'Auth',

    'access_ttl_minutes' => (int) env('AUTH_ACCESS_TTL_MINUTES', 15),
    'refresh_ttl_days' => (int) env('AUTH_REFRESH_TTL_DAYS', 14),
];
