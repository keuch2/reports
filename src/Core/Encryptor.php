<?php

declare(strict_types=1);

namespace MisterCo\Reports\Core;

use RuntimeException;

/**
 * AES-256-GCM con APP_KEY derivada (32 bytes raw via SHA-256 del APP_KEY config).
 * El payload se serializa como "v1." + base64(iv || tag || ciphertext).
 */
final class Encryptor
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'v1.';

    private readonly string $key;

    public function __construct(string $appKey)
    {
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY no configurado. Generá uno con: php bin/generate-key.php');
        }
        // Derivamos 32 bytes deterministas a partir del APP_KEY.
        $this->key = hash('sha256', $appKey, true);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Fallo al cifrar.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new RuntimeException('Formato de payload cifrado inválido.');
        }

        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 12 + 16 + 1) {
            throw new RuntimeException('Payload cifrado corrupto.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Fallo al descifrar (clave incorrecta o payload alterado).');
        }

        return $plaintext;
    }
}
