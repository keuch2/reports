<?php

declare(strict_types=1);

namespace MisterCo\Reports\Core;

/**
 * Wrapper de sesión nativa de PHP con configuración segura.
 */
final class Session
{
    public function __construct(int $lifetimeMinutes, bool $secureCookie)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => $lifetimeMinutes * 60,
            'path' => '/',
            'domain' => '',
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('misterco_session');
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }

        session_destroy();
    }

    public function flash(string $key, mixed $value): void
    {
        $flashes = $_SESSION['_flash'] ?? [];
        $flashes[$key] = $value;
        $_SESSION['_flash'] = $flashes;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $flashes = $_SESSION['_flash'] ?? [];
        $value = $flashes[$key] ?? $default;
        unset($flashes[$key]);
        $_SESSION['_flash'] = $flashes;

        return $value;
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }
}
