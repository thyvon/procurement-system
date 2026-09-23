<?php

return [
    'name' => 'EPurchase',

    'base_url' => env('EPURCHASE_BASE_URL', 'http://esign.mjqe.com.kh/api'),
    'login_path' => env('EPURCHASE_LOGIN_PATH', '/default_user_access/login'),
    'timeout' => (int) env('EPURCHASE_TIMEOUT', 10),
    'default_entity' => env('EPURCHASE_DEFAULT_ENTITY'),
];
