<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Meta;

use MisterCo\Reports\Repositories\ConfiguracionRepository;

/**
 * Gestiona el token de System User: lectura desde BD (cifrado), validación,
 * y construcción del MetaApiClient bajo demanda.
 */
final class MetaTokenService
{
    private const CLAVE_TOKEN = 'meta.system_user_token';

    public function __construct(
        private readonly ConfiguracionRepository $config,
        private readonly string $apiVersion,
    ) {
    }

    public function tieneToken(): bool
    {
        return $this->config->get(self::CLAVE_TOKEN) !== null;
    }

    public function obtenerToken(): ?string
    {
        return $this->config->get(self::CLAVE_TOKEN);
    }

    public function guardarToken(string $token, int $usuarioId): void
    {
        $this->config->set(self::CLAVE_TOKEN, $token, $usuarioId);
    }

    public function borrarToken(): void
    {
        $this->config->delete(self::CLAVE_TOKEN);
    }

    public function cliente(): MetaApiClient
    {
        $token = $this->obtenerToken();
        if ($token === null) {
            throw new MetaApiException('No hay token de Meta configurado. Conectá la cuenta primero.');
        }

        return new MetaApiClient($token, $this->apiVersion);
    }

    /**
     * Construye un cliente con un token arbitrario (para validar antes de guardar).
     */
    public function clienteCon(string $token): MetaApiClient
    {
        return new MetaApiClient($token, $this->apiVersion);
    }
}
