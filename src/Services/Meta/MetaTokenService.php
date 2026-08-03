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
    private const CLAVE_VENCIMIENTO = 'meta.token_expira_en';

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
        // Un token pegado a mano (System User) no vence: limpiamos cualquier
        // vencimiento previo de una conexión OAuth.
        $this->config->delete(self::CLAVE_VENCIMIENTO);
    }

    /** Guarda un token OAuth de larga duración junto con su fecha de vencimiento. */
    public function guardarTokenConVencimiento(string $token, int $usuarioId, ?\DateTimeImmutable $expiraEn): void
    {
        $this->config->set(self::CLAVE_TOKEN, $token, $usuarioId);
        if ($expiraEn !== null) {
            $this->config->set(self::CLAVE_VENCIMIENTO, $expiraEn->format('Y-m-d H:i:s'), $usuarioId);
        } else {
            $this->config->delete(self::CLAVE_VENCIMIENTO);
        }
    }

    /** Fecha de vencimiento del token (solo conexiones OAuth), null si no vence. */
    public function venceEn(): ?\DateTimeImmutable
    {
        $valor = $this->config->get(self::CLAVE_VENCIMIENTO);
        if ($valor === null) {
            return null;
        }
        $fecha = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $valor);

        return $fecha === false ? null : $fecha;
    }

    /** Días que faltan para el vencimiento; null si el token no vence. Negativo = vencido. */
    public function diasHastaVencimiento(): ?int
    {
        $vence = $this->venceEn();
        if ($vence === null) {
            return null;
        }

        return (int) floor(($vence->getTimestamp() - time()) / 86400);
    }

    public function borrarToken(): void
    {
        $this->config->delete(self::CLAVE_TOKEN);
        $this->config->delete(self::CLAVE_VENCIMIENTO);
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
