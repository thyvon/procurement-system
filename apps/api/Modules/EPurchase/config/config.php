<?php

return [
    'name' => 'EPurchase',

    'base_url' => env('EPURCHASE_BASE_URL', 'https://epurchase.mjqeducation.edu.kh'),
    'timeout' => (int) env('EPURCHASE_TIMEOUT', 10),
    'session_ttl' => (int) env('EPURCHASE_SESSION_TTL', 1800),
    'default_entity' => env('EPURCHASE_DEFAULT_ENTITY'),
];
