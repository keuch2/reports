<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Core\Database;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Recuperación de contraseña vía token de un solo uso.
 *
 * Flujo:
 * 1. Usuario solicita reset → se genera token (32 bytes), se guarda HASH (sha256)
 *    en `password_resets`, se envía el plaintext por correo.
 * 2. Usuario abre el link → validamos token vs hash, verificamos no expirado y no usado.
 * 3. Usuario setea nueva contraseña (sujeta a PasswordPolicyService) → invalidamos
 *    todos los tokens activos del usuario.
 *
 * No revelamos si un correo existe (siempre respondemos OK).
 */
final class PasswordResetService
{
    private const TTL_MINUTOS = 60;

    public function __construct(
        private readonly Database $db,
        private readonly PasswordPolicyService $policy,
        private readonly AuditService $audit,
        /** @var array{host?:string,port?:int,username?:string,password?:string,from_address?:string,from_name?:string} */
        private readonly array $smtpConfig,
        private readonly string $appUrl,
    ) {
    }

    /**
     * Solicita un reset. Si el correo existe, envía email con token.
     * Siempre retorna true (no leak de existencia).
     */
    public function solicitar(string $correo, ?string $ip = null): bool
    {
        $usuario = $this->db->selectOne(
            'SELECT id, correo, nombre_completo FROM usuarios WHERE correo = :c AND activo = 1 LIMIT 1',
            ['c' => $correo]
        );

        if ($usuario === null) {
            return true;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $this->db->execute(
            'INSERT INTO password_resets (usuario_id, token_hash, expira_en, ip_solicitud)
             VALUES (:uid, :h, DATE_ADD(NOW(), INTERVAL :min MINUTE), :ip)',
            ['uid' => (int) $usuario['id'], 'h' => $hash, 'min' => self::TTL_MINUTOS, 'ip' => $ip]
        );

        $url = rtrim($this->appUrl, '/') . '/password/reset?token=' . $token;
        $this->enviarCorreo((string) $usuario['correo'], (string) $usuario['nombre_completo'], $url);

        $this->audit->registrar('auth.password_reset_solicitado', null, $ip, null,
            'usuario', (string) $usuario['id']);

        return true;
    }

    /**
     * Verifica el token y devuelve el usuario asociado, o null si inválido/expirado/usado.
     *
     * @return array<string,mixed>|null
     */
    public function verificarToken(string $token): ?array
    {
        if ($token === '' || strlen($token) !== 64) {
            return null;
        }
        $hash = hash('sha256', $token);

        $row = $this->db->selectOne(
            'SELECT pr.id AS reset_id, u.id AS usuario_id, u.correo, u.nombre_completo
               FROM password_resets pr
               JOIN usuarios u ON u.id = pr.usuario_id AND u.activo = 1
              WHERE pr.token_hash = :h
                AND pr.usado_en IS NULL
                AND pr.expira_en > NOW()
              LIMIT 1',
            ['h' => $hash]
        );

        return $row;
    }

    /**
     * Aplica la nueva contraseña si el token es válido y cumple la política.
     *
     * @return list<string> Errores en lenguaje natural; vacío = éxito
     */
    public function consumirYActualizar(string $token, string $passwordNuevo, ?string $ip = null): array
    {
        $errores = $this->policy->validar($passwordNuevo);
        if ($errores !== []) {
            return $errores;
        }

        $row = $this->verificarToken($token);
        if ($row === null) {
            return ['El enlace es inválido o expiró. Solicitá uno nuevo.'];
        }

        $hash = password_hash($passwordNuevo, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2,
        ]);

        $this->db->beginTransaction();
        try {
            $this->db->execute('UPDATE usuarios SET password_hash = :h WHERE id = :id',
                ['h' => $hash, 'id' => (int) $row['usuario_id']]);
            // Invalidar todos los tokens activos del usuario.
            $this->db->execute('UPDATE password_resets SET usado_en = NOW()
                                 WHERE usuario_id = :uid AND usado_en IS NULL',
                ['uid' => (int) $row['usuario_id']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->audit->registrar('auth.password_recuperada', null, $ip, null,
            'usuario', (string) $row['usuario_id']);

        return [];
    }

    private function enviarCorreo(string $correo, string $nombre, string $url): void
    {
        // En desarrollo escribimos el correo a un log y salimos.
        $host = (string) ($this->smtpConfig['host'] ?? '');
        if ($host === '') {
            error_log("[PASSWORD-RESET-DEV] Para: {$correo} | URL: {$url}");
            return;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) ($this->smtpConfig['port'] ?? 587);
        $mail->SMTPAuth = !empty($this->smtpConfig['username']);
        $mail->Username = (string) ($this->smtpConfig['username'] ?? '');
        $mail->Password = (string) ($this->smtpConfig['password'] ?? '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';

        $from = (string) ($this->smtpConfig['from_address'] ?? 'noreply@misterco.test');
        $fromName = (string) ($this->smtpConfig['from_name'] ?? 'Mister Co. Reports');
        $mail->setFrom($from, $fromName);
        $mail->addAddress($correo, $nombre);
        $mail->isHTML(true);
        $mail->Subject = 'Recuperá tu contraseña — Mister Co. Reports';
        $mail->Body = sprintf(
            '<p>Hola %s,</p>'
            . '<p>Recibimos un pedido para restablecer tu contraseña. El enlace vence en %d minutos:</p>'
            . '<p><a href="%s">Restablecer contraseña</a></p>'
            . '<p>Si no fuiste vos, ignorá este correo.</p>'
            . '<p>— Mister Co.</p>',
            htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'),
            self::TTL_MINUTOS,
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
        );
        $mail->AltBody = "Restablecé tu contraseña: {$url}\n(Vence en " . self::TTL_MINUTOS . ' minutos)';

        try {
            $mail->send();
        } catch (\Throwable $e) {
            error_log('[PASSWORD-RESET] Falló envío SMTP: ' . $e->getMessage());
        }
    }
}
