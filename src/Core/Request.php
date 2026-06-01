<?php

declare(strict_types=1);

namespace MisterCo\Reports\Core;

final class Request
{
    /** @var array<string, mixed> */
    public array $attributes = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        /** @var array<string, mixed> */
        public readonly array $query,
        /** @var array<string, mixed> */
        public readonly array $post,
        /** @var array<string, string> */
        public readonly array $headers,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }

    public static function fromGlobals(string $pathPrefix = ''): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Si la app vive bajo un prefijo (ej. /reports), lo desmontamos antes de
        // matchear rutas para que el router siga viendo /login, /admin, etc.
        if ($pathPrefix !== '' && str_starts_with($path, $pathPrefix)) {
            $path = substr($path, strlen($pathPrefix));
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . $path;
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        return new self(
            method: $method,
            path: rtrim($path, '/') ?: '/',
            query: $_GET,
            post: $_POST,
            headers: $headers,
            ip: $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            userAgent: $_SERVER['HTTP_USER_AGENT'] ?? '',
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function expectsJson(): bool
    {
        $accept = $this->header('accept') ?? '';

        return str_contains($accept, 'application/json');
    }
}
