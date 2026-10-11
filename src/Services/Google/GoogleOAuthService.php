<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Google;

use GuzzleHttp\Client;

/**
 * Flujo OAuth "Conectar con Google": una sola autorización da acceso de lectura
 * a Google Ads y a Google Analytics 4.
 *
 * Requiere un cliente OAuth tipo "Aplicación web" en Google Cloud Console con el
 * redirect URI registrado. Pedimos access_type=offline + prompt=consent para que
 * Google SIEMPRE devuelva un refresh token (sin vencimiento mientras la app esté
 * publicada y el usuario no revoque el acceso).
 */
final class GoogleOAuthService
{
    private const AUTH_URI = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/adwords',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $appUrl,
    ) {
    }

    public function configurado(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /** URI de retorno; debe coincidir EXACTO con el registrado en Google Cloud. */
    public function redirectUri(): string
    {
        return rtrim($this->appUrl, '/') . '/admin/google/oauth/callback';
    }

    public function urlAutorizacion(string $state): string
    {
        return self::AUTH_URI . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Canjea el `code` del callback por el refresh token y el email de la cuenta.
     *
     * @return array{refresh_token: string, email: ?string}
     * @throws GoogleApiException
     */
    public function intercambiarCodigo(string $code): array
    {
        $json = $this->pedirToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri(),
        ]);

        if (!isset($json['refresh_token'])) {
            throw new GoogleApiException('Google no devolvió un refresh token. Quitá el acceso de la app en tu cuenta de Google y volvé a conectar.');
        }

        return [
            'refresh_token' => (string) $json['refresh_token'],
            'email' => $this->emailDelIdToken((string) ($json['id_token'] ?? '')),
        ];
    }

    /**
     * Pide un access token nuevo (~1h) a partir del refresh token.
     *
     * @return array{token: string, expira: int}
     * @throws GoogleApiException
     */
    public function accessToken(string $refreshToken): array
    {
        $json = $this->pedirToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        return [
            'token' => (string) $json['access_token'],
            'expira' => time() + (int) ($json['expires_in'] ?? 3600),
        ];
    }

    /**
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    private function pedirToken(array $form): array
    {
        $http = new Client(['timeout' => 30, 'http_errors' => false]);
        $resp = $http->post(self::TOKEN_URI, ['form_params' => $form]);
        $json = json_decode((string) $resp->getBody(), true);

        if (!is_array($json) || !isset($json['access_token'])) {
            $error = is_array($json) ? (string) ($json['error'] ?? '') : '';
            $descripcion = is_array($json) ? (string) ($json['error_description'] ?? $error) : '';
            throw new GoogleApiException(
                $descripcion !== '' ? $descripcion : 'Respuesta inesperada de Google al pedir el token.',
                httpStatus: $resp->getStatusCode(),
                googleStatus: $error !== '' ? $error : null,
            );
        }

        return $json;
    }

    /** El id_token llega directo de Google por TLS: basta decodificar el payload. */
    private function emailDelIdToken(string $idToken): ?string
    {
        $partes = explode('.', $idToken);
        if (count($partes) !== 3) {
            return null;
        }
        $payload = json_decode((string) base64_decode(strtr($partes[1], '-_', '+/')), true);

        return is_array($payload) && isset($payload['email']) ? (string) $payload['email'] : null;
    }
}
