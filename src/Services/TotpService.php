<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

/**
 * TOTP (RFC 6238) + base32 (RFC 4648) sin dependencias externas.
 *
 * Genera secretos compatibles con Google Authenticator / Authy / 1Password.
 * Validación con ventana ±1 step para tolerar drift de reloj.
 */
final class TotpService
{
    private const DIGITS = 6;
    private const PERIOD = 30;
    private const ALGORITHM = 'sha1';
    private const VENTANA = 1; // permite N steps antes/después

    /** Genera un secreto base32 de 20 bytes (160 bits). */
    public function generarSecreto(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /** Genera 10 códigos de backup (8 chars cada uno, en base32). */
    /** @return list<string> */
    public function generarBackupCodes(int $cuantos = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $cuantos; $i++) {
            $codes[] = $this->base32Encode(random_bytes(5)); // 8 chars en base32
        }

        return $codes;
    }

    /**
     * URI otpauth:// para QR code (escaneable por la app authenticator).
     * Formato: otpauth://totp/Issuer:Account?secret=BASE32&issuer=Issuer
     */
    public function otpauthUri(string $secreto, string $cuenta, string $issuer = 'Mister Co. Reports'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($cuenta);
        $params = http_build_query([
            'secret' => $secreto,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /** Verifica el código contra el secreto con ventana ±1 step. */
    public function verificar(string $secreto, string $codigo): bool
    {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';
        if (strlen($codigo) !== self::DIGITS) {
            return false;
        }

        $key = $this->base32Decode($secreto);
        if ($key === false) {
            return false;
        }

        $tNow = (int) (time() / self::PERIOD);
        for ($offset = -self::VENTANA; $offset <= self::VENTANA; $offset++) {
            if (hash_equals($this->generarCodigo($key, $tNow + $offset), $codigo)) {
                return true;
            }
        }

        return false;
    }

    /** Genera el código TOTP de N dígitos para un counter dado (raw secret bytes). */
    private function generarCodigo(string $key, int $counter): string
    {
        $packed = pack('J', $counter);
        $hmac = hash_hmac(self::ALGORITHM, $packed, $key, true);
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $binary =
            ((ord($hmac[$offset]) & 0x7F) << 24)
            | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
            | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
            | (ord($hmac[$offset + 3]) & 0xFF);
        $code = $binary % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $buf = 0;
        $bits = 0;
        foreach (str_split($data) as $byte) {
            $buf = ($buf << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= $alphabet[($buf >> $bits) & 0x1F];
            }
        }
        if ($bits > 0) {
            $out .= $alphabet[($buf << (5 - $bits)) & 0x1F];
        }

        return $out;
    }

    /** @return string|false */
    private function base32Decode(string $b32): string|false
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
        if ($b32 === '') {
            return false;
        }
        $out = '';
        $buf = 0;
        $bits = 0;
        foreach (str_split($b32) as $ch) {
            $v = strpos($alphabet, $ch);
            if ($v === false) {
                return false;
            }
            $buf = ($buf << 5) | $v;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buf >> $bits) & 0xFF);
            }
        }

        return $out;
    }
}
