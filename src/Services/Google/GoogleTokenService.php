<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Google;

use MisterCo\Reports\Repositories\ConfiguracionRepository;

/**
 * Guarda el refresh token de Google (cifrado en `configuracion`) y construye
 * el GoogleApiClient con un access token fresco bajo demanda.
 */
final class GoogleTokenService
{
    private const CLAVE_REFRESH_TOKEN = 'google.refresh_token';
    private const CLAVE_EMAIL = 'google.cuenta_email';

    public function __construct(
        private readonly ConfiguracionRepository $config,
        private readonly GoogleOAuthService $oauth,
        private readonly string $developerToken,
        private readonly string $adsApiVersion,
    ) {
    }

    public function tieneToken(): bool
    {
        return $this->config->get(self::CLAVE_REFRESH_TOKEN) !== null;
    }

    public function emailConectado(): ?string
    {
        return $this->config->get(self::CLAVE_EMAIL);
    }

    /** Google Ads exige además un developer token aprobado (GOOGLE_ADS_DEVELOPER_TOKEN). */
    public function adsDisponible(): bool
    {
        return $this->developerToken !== '';
    }

    public function guardarToken(string $refreshToken, ?string $email, int $usuarioId): void
    {
        $this->config->set(self::CLAVE_REFRESH_TOKEN, $refreshToken, $usuarioId);
        if ($email !== null) {
            $this->config->set(self::CLAVE_EMAIL, $email, $usuarioId);
        } else {
            $this->config->delete(self::CLAVE_EMAIL);
        }
    }

    public function borrarToken(): void
    {
        $this->config->delete(self::CLAVE_REFRESH_TOKEN);
        $this->config->delete(self::CLAVE_EMAIL);
    }

    /** @throws GoogleApiException */
    public function cliente(): GoogleApiClient
    {
        $refresh = $this->config->get(self::CLAVE_REFRESH_TOKEN);
        if ($refresh === null) {
            throw new GoogleApiException('No hay una cuenta de Google conectada. Conectala primero.');
        }

        return $this->clienteCon($refresh);
    }

    /** Cliente con un refresh token arbitrario (para validar antes de guardar). */
    public function clienteCon(string $refreshToken): GoogleApiClient
    {
        $token = $this->oauth->accessToken($refreshToken);

        return new GoogleApiClient($token['token'], $this->developerToken, $this->adsApiVersion);
    }
}
