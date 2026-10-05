<?php

return [
    'name'      => "I'KARANGE",
    'version'   => '1.0.0',
    'env'       => getenv('APP_ENV') ?: 'production',
    'debug'     => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'timezone'  => 'Africa/Dakar',
    'session'   => [
        'name'      => 'ikarange_sid',
        'lifetime'  => 86400 * 30,
        'secure'    => filter_var(getenv('SESSION_SECURE') ?: true, FILTER_VALIDATE_BOOLEAN),
        'httponly'   => true,
        'samesite'  => 'Strict',
    ],
    'rate_limit' => [
        'login_max'    => 5,
        'login_window' => 900,
    ],
    'upload' => [
        'max_size'     => 5 * 1024 * 1024,
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'dir'          => __DIR__ . '/../storage/uploads',
    ],
];
