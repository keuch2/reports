<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Core\Database;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Domain\Usuario;

final class AuthService
{
    private const MAX_INTENTOS = 5;
    private const VENTANA_INTENTOS_MIN = 15;
    private const BLOQUEO_MINUTOS = 60;

    private const ARGON_OPTIONS = [
        'memory_cost' => 65536, // 64 MB
        'time_cost' => 4,
        'threads' => 2,
    ];

    public function __construct(
        private readonly Database $db,
        private readonly Session $session,
        private readonly ?AuditService $audit = null,
    ) {
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::ARGON_OPTIONS);
    }

    /**
     * Intenta autenticar y devuelve el usuario en éxito, o null en fallo.
     * Bloquea silenciosamente si el correo o la IP excedió intentos en la ventana.
     */
    public function intentarLogin(string $correo, string $password, string $ip, string $userAgent): ?Usuario
    {
        if ($this->estaBloqueado($correo, $ip)) {
            $this->registrarIntento($correo, $ip, $userAgent, false);
            $this->audit?->registrar('auth.login_bloqueado', null, $ip, $userAgent, 'usuario', $correo);

            return null;
        }

        $row = $this->db->selectOne(
            'SELECT id, correo, password_hash, nombre_completo, rol, cliente_id, activo
               FROM usuarios WHERE correo = :correo LIMIT 1',
            ['correo' => $correo]
        );

        if ($row === null || (int) $row['activo'] !== 1) {
            $this->registrarIntento($correo, $ip, $userAgent, false);
            $this->audit?->registrar('auth.login_fallido', null, $ip, $userAgent, 'usuario', $correo,
                ['motivo' => $row === null ? 'no_existe' : 'inactivo']);

            return null;
        }

        if (!password_verify($password, (string) $row['password_hash'])) {
            $this->registrarIntento($correo, $ip, $userAgent, false);
            $this->audit?->registrar('auth.login_fallido', null, $ip, $userAgent, 'usuario', $correo,
                ['motivo' => 'password_incorrecto']);

            return null;
        }

        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_ARGON2ID, self::ARGON_OPTIONS)) {
            $this->db->execute(
                'UPDATE usuarios SET password_hash = :h WHERE id = :id',
                ['h' => $this->hash($password), 'id' => (int) $row['id']]
            );
        }

        $this->db->execute(
            'UPDATE usuarios SET ultimo_acceso_en = NOW() WHERE id = :id',
            ['id' => (int) $row['id']]
        );

        $this->registrarIntento($correo, $ip, $userAgent, true);

        $usuario = Usuario::fromRow($row);
        $this->session->regenerate();
        $this->session->set('usuario_id', $usuario->id);
        $this->session->set('usuario_rol', $usuario->rol);
        $this->session->set('usuario_cliente_id', $usuario->clienteId);

        $this->audit?->registrar('auth.login_ok', $usuario, $ip, $userAgent, 'usuario', (string) $usuario->id);

        return $usuario;
    }

    /**
     * Establece la sesión autenticada para un usuario verificado por otro mecanismo (ej. 2FA post-login).
     * No registra intento ni audita login_ok (eso ya pasó en intentarLogin).
     */
    public function forzarLoginPorId(int $usuarioId): ?Usuario
    {
        $row = $this->db->selectOne(
            'SELECT id, correo, nombre_completo, rol, cliente_id, activo
               FROM usuarios WHERE id = :id LIMIT 1',
            ['id' => $usuarioId]
        );
        if ($row === null || (int) $row['activo'] !== 1) {
            return null;
        }

        $usuario = Usuario::fromRow($row);
        $this->session->regenerate();
        $this->session->set('usuario_id', $usuario->id);
        $this->session->set('usuario_rol', $usuario->rol);
        $this->session->set('usuario_cliente_id', $usuario->clienteId);
        $this->audit?->registrar('auth.2fa_ok', $usuario, null, null, 'usuario', (string) $usuario->id);

        return $usuario;
    }

    public function usuarioActual(): ?Usuario
    {
        $id = $this->session->get('usuario_id');
        if (!is_int($id)) {
            return null;
        }

        $row = $this->db->selectOne(
            'SELECT id, correo, nombre_completo, rol, cliente_id, activo
               FROM usuarios WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        if ($row === null || (int) $row['activo'] !== 1) {
            $this->logout();

            return null;
        }

        return Usuario::fromRow($row);
    }

    public function logout(): void
    {
        $usuario = $this->usuarioActual();
        if ($usuario !== null) {
            $this->audit?->registrar('auth.logout', $usuario);
        }
        $this->session->destroy();
    }

    private function estaBloqueado(string $correo, string $ip): bool
    {
        $ventana = self::VENTANA_INTENTOS_MIN;
        $bloqueo = self::BLOQUEO_MINUTOS;

        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS n FROM intentos_login
              WHERE exito = 0
                AND (correo = :correo OR ip = :ip)
                AND intentado_en >= (NOW() - INTERVAL {$bloqueo} MINUTE)
                AND intentado_en >= (
                    SELECT COALESCE(MAX(intentado_en), '1970-01-01') FROM intentos_login
                     WHERE exito = 1 AND (correo = :correo2 OR ip = :ip2)
                       AND intentado_en >= (NOW() - INTERVAL {$bloqueo} MINUTE)
                )",
            ['correo' => $correo, 'ip' => $ip, 'correo2' => $correo, 'ip2' => $ip]
        );

        return (int) ($row['n'] ?? 0) >= self::MAX_INTENTOS;
    }

    private function registrarIntento(string $correo, string $ip, string $userAgent, bool $exito): void
    {
        $this->db->execute(
            'INSERT INTO intentos_login (correo, ip, user_agent, exito) VALUES (:correo, :ip, :ua, :exito)',
            [
                'correo' => $correo,
                'ip' => $ip,
                'ua' => substr($userAgent, 0, 500),
                'exito' => $exito ? 1 : 0,
            ]
        );
    }
}
