<?php

declare(strict_types=1);

namespace MisterCo\Reports\Core;

final class Response
{
    /** Prefijo de URL bajo el que vive la app (ej. "/reports"). Application lo setea al bootstrap. */
    private static string $pathPrefix = '';

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public static function setPathPrefix(string $prefix): void
    {
        self::$pathPrefix = rtrim($prefix, '/');
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<mixed>|object $data */
    public static function json(array|object $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return new self($body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /**
     * Redirect. Si $url empieza con "/" se prepende automáticamente APP_PATH_PREFIX.
     * URLs absolutas (http://...) no se modifican.
     */
    public static function redirect(string $url, int $status = 302): self
    {
        if (self::$pathPrefix !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = self::$pathPrefix . $url;
        }

        return new self('', $status, ['Location' => $url]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        // Cabeceras de seguridad globales para todas las respuestas HTML/JSON.
        // (No se agregan a PDFs/archivos porque el Content-Type ya viene seteado y
        //  la CSP no tiene efecto sobre application/pdf.)
        $contentType = $this->headers['Content-Type'] ?? '';
        $esHtmlOJson = str_starts_with($contentType, 'text/html') || str_starts_with($contentType, 'application/json');
        if ($esHtmlOJson || $contentType === '') {
            $defaults = self::cabecerasSeguridadDefault();
            foreach ($defaults as $name => $value) {
                if (!isset($this->headers[$name])) {
                    header("{$name}: {$value}");
                }
            }
        }

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }

    /** @return array<string, string> */
    public static function cabecerasSeguridadDefault(): array
    {
        $appEnv = $_ENV['APP_ENV'] ?? 'production';
        $forzarHsts = $appEnv === 'production';

        // CSP permite Chart.js (jsdelivr), QR Server (qrserver.com) y estilos inline mínimos
        // usados en algunas vistas. Scripts inline necesarios para gráficos: 'unsafe-inline'.
        $csp = "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: https://api.qrserver.com; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "frame-ancestors 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self'";

        $cabeceras = [
            'Content-Security-Policy' => $csp,
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];
        if ($forzarHsts) {
            $cabeceras['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $cabeceras;
    }
}
