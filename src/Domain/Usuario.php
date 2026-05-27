<?php

declare(strict_types=1);

namespace MisterCo\Reports\Domain;

final class Usuario
{
    public function __construct(
        public readonly int $id,
        public readonly string $correo,
        public readonly string $nombreCompleto,
        public readonly string $rol,
        public readonly ?int $clienteId,
        public readonly bool $activo,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            correo: (string) $row['correo'],
            nombreCompleto: (string) $row['nombre_completo'],
            rol: (string) $row['rol'],
            clienteId: $row['cliente_id'] !== null ? (int) $row['cliente_id'] : null,
            activo: (bool) $row['activo'],
        );
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    public function esCliente(): bool
    {
        return $this->rol === 'cliente';
    }
}
