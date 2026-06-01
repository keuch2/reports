<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Mister Co. Reports',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'key' => $_ENV['APP_KEY'] ?? '',
    // Prefijo bajo el que vive la app. Vacío = en la raíz. Ej. "/reports" si vive en http://host/reports.
    // No incluir slash al final. Si está vacío, no se aplica nada.
    'path_prefix' => rtrim((string) ($_ENV['APP_PATH_PREFIX'] ?? ''), '/'),
    'session_lifetime_minutes' => (int) ($_ENV['SESSION_LIFETIME_MINUTES'] ?? 120),
    'session_secure_cookie' => filter_var($_ENV['SESSION_SECURE_COOKIE'] ?? false, FILTER_VALIDATE_BOOLEAN),
];
