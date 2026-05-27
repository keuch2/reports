<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;
use MisterCo\Reports\Core\Encryptor;

final class ConfiguracionRepository
{
    public function __construct(
        private readonly Database $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function get(string $clave): ?string
    {
        $row = $this->db->selectOne(
            'SELECT valor_cifrado FROM configuracion WHERE clave = :clave',
            ['clave' => $clave]
        );

        if ($row === null) {
            return null;
        }

        return $this->encryptor->decrypt((string) $row['valor_cifrado']);
    }

    public function set(string $clave, string $valor, ?int $usuarioId = null): void
    {
        $this->db->execute(
            'INSERT INTO configuracion (clave, valor_cifrado, actualizado_por)
                  VALUES (:clave, :valor, :uid)
                  ON DUPLICATE KEY UPDATE valor_cifrado = VALUES(valor_cifrado),
                                          actualizado_por = VALUES(actualizado_por)',
            ['clave' => $clave, 'valor' => $this->encryptor->encrypt($valor), 'uid' => $usuarioId]
        );
    }

    public function delete(string $clave): void
    {
        $this->db->execute('DELETE FROM configuracion WHERE clave = :clave', ['clave' => $clave]);
    }
}
