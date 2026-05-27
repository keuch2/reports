<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

final class ClienteRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listarActivos(): array
    {
        return $this->db->select(
            'SELECT c.id, c.nombre_comercial, c.correo_contacto, c.activo, c.creado_en,
                    u.id AS usuario_id, u.correo AS usuario_correo, u.nombre_completo AS usuario_nombre
               FROM clientes c
          LEFT JOIN usuarios u ON u.cliente_id = c.id AND u.rol = \'cliente\'
              WHERE c.eliminado_en IS NULL
           ORDER BY c.nombre_comercial'
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT id, nombre_comercial, razon_social, ruc, contacto_principal, correo_contacto,
                    telefono, activo, creado_en
               FROM clientes WHERE id = :id AND eliminado_en IS NULL',
            ['id' => $id]
        );
    }

    public function crear(
        string $nombreComercial,
        ?string $correoContacto,
        ?string $contactoPrincipal,
        ?string $telefono,
    ): int {
        $this->db->execute(
            'INSERT INTO clientes (nombre_comercial, correo_contacto, contacto_principal, telefono)
                  VALUES (:n, :c, :cp, :t)',
            ['n' => $nombreComercial, 'c' => $correoContacto, 'cp' => $contactoPrincipal, 't' => $telefono]
        );

        return $this->db->lastInsertId();
    }

    public function crearUsuarioPrimario(int $clienteId, string $correo, string $passwordHash, string $nombre): int
    {
        $this->db->execute(
            'INSERT INTO usuarios (correo, password_hash, nombre_completo, rol, cliente_id)
                  VALUES (:c, :h, :n, \'cliente\', :cid)',
            ['c' => $correo, 'h' => $passwordHash, 'n' => $nombre, 'cid' => $clienteId]
        );

        return $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function cuentasAsignadas(int $clienteId): array
    {
        return $this->db->select(
            'SELECT cp.id, cp.meta_account_id, cp.nombre, cp.moneda
               FROM permisos_cliente_cuenta pcc
               JOIN cuentas_publicitarias cp ON cp.id = pcc.cuenta_publicitaria_id
              WHERE pcc.cliente_id = :cid
           ORDER BY cp.nombre',
            ['cid' => $clienteId]
        );
    }

    public function asignarCuenta(int $clienteId, int $cuentaId, ?int $otorgadoPor): void
    {
        $this->db->execute(
            'INSERT IGNORE INTO permisos_cliente_cuenta (cliente_id, cuenta_publicitaria_id, otorgado_por_usuario_id)
                  VALUES (:cli, :cue, :ot)',
            ['cli' => $clienteId, 'cue' => $cuentaId, 'ot' => $otorgadoPor]
        );
    }

    public function desasignarCuenta(int $clienteId, int $cuentaId): void
    {
        $this->db->execute(
            'DELETE FROM permisos_cliente_cuenta WHERE cliente_id = :cli AND cuenta_publicitaria_id = :cue',
            ['cli' => $clienteId, 'cue' => $cuentaId]
        );
    }
}
