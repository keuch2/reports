<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Mister Co. Reports',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'key' => $_ENV['APP_KEY'] ?? '',
    'session_lifetime_minutes' => (int) ($_ENV['SESSION_LIFETIME_MINUTES'] ?? 120),
    'session_secure_cookie' => filter_var($_ENV['SESSION_SECURE_COOKIE'] ?? false, FILTER_VALIDATE_BOOLEAN),
];
