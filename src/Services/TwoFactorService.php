<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Core\Database;
use MisterCo\Reports\Core\Encryptor;

/**
 * Orquesta el ciclo de vida de 2FA por usuario:
 * - Generar secreto + QR para enrolar (no habilita hasta confirmar con código)
 * - Confirmar enrolamiento (verifica primer código + persiste habilitado)
 * - Verificar código durante login (acepta TOTP o backup code)
 * - Deshabilitar
 *
 * Secretos y backup codes se almacenan cifrados (AES-GCM via Encryptor).
 */
final class TwoFactorService
{
    public function __construct(
        private readonly Database $db,
        private readonly Encryptor $encryptor,
        private readonly TotpService $totp,
        private readonly AuditService $audit,
    ) {
    }

    public function estaHabilitado(int $usuarioId): bool
    {
        $row = $this->db->selectOne(
            'SELECT twofa_habilitado FROM usuarios WHERE id = :id', ['id' => $usuarioId]
        );

        return $row !== null && (int) $row['twofa_habilitado'] === 1;
    }

    /**
     * Genera secreto + URI otpauth para que el usuario lo escanee.
     * Persiste el secreto cifrado pero NO marca habilitado hasta confirmar.
     *
     * @return array{secreto:string, otpauth_uri:string}
     */
    public function iniciarEnrolamiento(int $usuarioId, string $correo): array
    {
        $secreto = $this->totp->generarSecreto();

        $this->db->execute(
            'UPDATE usuarios SET twofa_secret_cifrado = :s, twofa_habilitado = 0,
                                  twofa_backup_codes_cifrado = NULL WHERE id = :id',
            ['s' => $this->encryptor->encrypt($secreto), 'id' => $usuarioId]
        );

        return [
            'secreto' => $secreto,
            'otpauth_uri' => $this->totp->otpauthUri($secreto, $correo),
        ];
    }

    /**
     * Confirma enrolamiento verificando el primer código.
     * Si OK: marca habilitado y genera backup codes (devuelve los plaintext, una sola vez).
     *
     * @return list<string>|null backup codes en plaintext, o null si código inválido
     */
    public function confirmarEnrolamiento(int $usuarioId, string $codigo): ?array
    {
        $secreto = $this->obtenerSecreto($usuarioId);
        if ($secreto === null || !$this->totp->verificar($secreto, $codigo)) {
            return null;
        }

        $backupCodes = $this->totp->generarBackupCodes();
        $cifrado = $this->encryptor->encrypt(json_encode($backupCodes));

        $this->db->execute(
            'UPDATE usuarios SET twofa_habilitado = 1, twofa_backup_codes_cifrado = :b WHERE id = :id',
            ['b' => $cifrado, 'id' => $usuarioId]
        );

        $this->audit->registrar('auth.2fa_habilitado', null, null, null, 'usuario', (string) $usuarioId);

        return $backupCodes;
    }

    /**
     * Verifica un código durante el login (TOTP primero, luego backup codes).
     * Si matchea un backup code, lo invalida.
     */
    public function verificarLogin(int $usuarioId, string $codigo): bool
    {
        $secreto = $this->obtenerSecreto($usuarioId);
        if ($secreto === null) {
            return false;
        }

        if ($this->totp->verificar($secreto, $codigo)) {
            return true;
        }

        // Probar backup codes.
        $codigoNormalizado = strtoupper(preg_replace('/\s+/', '', $codigo) ?? '');
        $row = $this->db->selectOne(
            'SELECT twofa_backup_codes_cifrado FROM usuarios WHERE id = :id',
            ['id' => $usuarioId]
        );
        if ($row === null || $row['twofa_backup_codes_cifrado'] === null) {
            return false;
        }

        $codigosCifrados = (string) $row['twofa_backup_codes_cifrado'];
        try {
            $codigos = json_decode($this->encryptor->decrypt($codigosCifrados), true);
        } catch (\Throwable) {
            return false;
        }
        if (!is_array($codigos)) {
            return false;
        }

        foreach ($codigos as $i => $valido) {
            if (hash_equals(strtoupper((string) $valido), $codigoNormalizado)) {
                array_splice($codigos, $i, 1);
                $this->db->execute(
                    'UPDATE usuarios SET twofa_backup_codes_cifrado = :b WHERE id = :id',
                    ['b' => $this->encryptor->encrypt(json_encode($codigos)), 'id' => $usuarioId]
                );
                $this->audit->registrar('auth.2fa_backup_code_usado', null, null, null, 'usuario', (string) $usuarioId,
                    ['codigos_restantes' => count($codigos)]);

                return true;
            }
        }

        return false;
    }

    public function deshabilitar(int $usuarioId): void
    {
        $this->db->execute(
            'UPDATE usuarios SET twofa_habilitado = 0, twofa_secret_cifrado = NULL,
                                  twofa_backup_codes_cifrado = NULL WHERE id = :id',
            ['id' => $usuarioId]
        );
        $this->audit->registrar('auth.2fa_deshabilitado', null, null, null, 'usuario', (string) $usuarioId);
    }

    private function obtenerSecreto(int $usuarioId): ?string
    {
        $row = $this->db->selectOne(
            'SELECT twofa_secret_cifrado FROM usuarios WHERE id = :id', ['id' => $usuarioId]
        );
        if ($row === null || $row['twofa_secret_cifrado'] === null) {
            return null;
        }
        try {
            return $this->encryptor->decrypt((string) $row['twofa_secret_cifrado']);
        } catch (\Throwable) {
            return null;
        }
    }
}
