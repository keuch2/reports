<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Meta;

use GuzzleHttp\Client;

/**
 * Flujo OAuth "Conectar con Facebook" para obtener el token de Meta sin que el
 * admin tenga que generar/pegar un token de System User a mano.
 *
 * Requiere una App de Meta (developers.facebook.com) con App ID + App Secret y
 * el redirect URI registrado. El token resultante es de usuario de larga
 * duración (~60 días); guardamos su vencimiento para avisar antes de que expire.
 */
final class MetaOAuthService
{
    private const GRAPH_URI = 'https://graph.facebook.com';
    private const DIALOG_URI = 'https://www.facebook.com';

    /** Permisos mínimos para leer campañas + insights de las cuentas del negocio. */
    private const SCOPES = 'ads_read,business_management';

    public function __construct(
        private readonly string $appId,
        private readonly string $appSecret,
        private readonly string $apiVersion,
        private readonly string $appUrl,
    ) {
    }

    /** ¿Está configurada la App de Meta (id + secret)? Sin eso no hay OAuth. */
    public function configurado(): bool
    {
        return $this->appId !== '' && $this->appSecret !== '';
    }

    /** URI de retorno; debe coincidir EXACTO con el registrado en la App de Meta. */
    public function redirectUri(): string
    {
        return rtrim($this->appUrl, '/') . '/admin/meta/oauth/callback';
    }

    /** URL del diálogo de autorización de Facebook. */
    public function urlAutorizacion(string $state): string
    {
        return self::DIALOG_URI . '/' . $this->apiVersion . '/dialog/oauth?' . http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => self::SCOPES,
        ]);
    }

    /**
     * Cambia el `code` del callback por un token de larga duración.
     *
     * Dos pasos contra Graph: code → token corto (~2h) → token largo (~60 días).
     *
     * @return array{token: string, expira_en: ?\DateTimeImmutable}
     * @throws MetaApiException si Meta rechaza el intercambio
     */
    public function intercambiarCodigo(string $code): array
    {
        $corto = $this->pedirToken([
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        $largo = $this->pedirToken([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => (string) $corto['access_token'],
        ]);

        $expiraEn = null;
        if (isset($largo['expires_in']) && (int) $largo['expires_in'] > 0) {
            $expiraEn = new \DateTimeImmutable('@' . (time() + (int) $largo['expires_in']));
        }

        return ['token' => (string) $largo['access_token'], 'expira_en' => $expiraEn];
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function pedirToken(array $query): array
    {
        $http = new Client(['base_uri' => self::GRAPH_URI, 'timeout' => 30, 'http_errors' => false]);
        $resp = $http->get('/' . $this->apiVersion . '/oauth/access_token', ['query' => $query]);
        $json = json_decode((string) $resp->getBody(), true);

        if (!is_array($json) || !isset($json['access_token'])) {
            $msg = is_array($json) && isset($json['error']['message'])
                ? (string) $json['error']['message']
                : 'Respuesta inesperada de Meta al intercambiar el código OAuth.';
            throw new MetaApiException($msg);
        }

        return $json;
    }
}
