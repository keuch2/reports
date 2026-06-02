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

    public function actualizar(
        int $id,
        string $nombreComercial,
        ?string $correoContacto,
        ?string $contactoPrincipal,
        ?string $telefono,
    ): void {
        $this->db->execute(
            'UPDATE clientes SET nombre_comercial = :n, correo_contacto = :c,
                                  contacto_principal = :cp, telefono = :t
              WHERE id = :id',
            ['id' => $id, 'n' => $nombreComercial, 'c' => $correoContacto,
             'cp' => $contactoPrincipal, 't' => $telefono]
        );
    }

    /**
     * Primer usuario rol=cliente asignado a un cliente (típicamente el único).
     *
     * @return array<string, mixed>|null
     */
    public function buscarUsuarioPrimario(int $clienteId): ?array
    {
        return $this->db->selectOne(
            'SELECT id, correo, nombre_completo, activo
               FROM usuarios
              WHERE cliente_id = :cid AND rol = \'cliente\'
              ORDER BY id ASC LIMIT 1',
            ['cid' => $clienteId]
        );
    }

    public function actualizarUsuarioPrimario(int $clienteId, string $correo, string $nombre): void
    {
        $this->db->execute(
            'UPDATE usuarios SET correo = :c, nombre_completo = :n
              WHERE cliente_id = :cid AND rol = \'cliente\'
              ORDER BY id ASC LIMIT 1',
            ['cid' => $clienteId, 'c' => $correo, 'n' => $nombre]
        );
    }

    public function actualizarPasswordUsuarioPrimario(int $clienteId, string $passwordHash): void
    {
        $this->db->execute(
            'UPDATE usuarios SET password_hash = :h
              WHERE cliente_id = :cid AND rol = \'cliente\'
              ORDER BY id ASC LIMIT 1',
            ['cid' => $clienteId, 'h' => $passwordHash]
        );
    }

    /**
     * Campañas asignadas a un cliente con su cuenta y moneda.
     *
     * @return list<array<string, mixed>>
     */
    public function campaniasAsignadas(int $clienteId): array
    {
        return $this->db->select(
            'SELECT c.id, c.meta_campaign_id, c.nombre AS campania, c.objetivo, c.estado,
                    c.fecha_inicio, c.fecha_fin,
                    cp.id AS cuenta_id, cp.nombre AS cuenta_nombre, cp.moneda
               FROM permisos_cliente_campania pccam
               JOIN campanias c ON c.id = pccam.campania_id
               JOIN cuentas_publicitarias cp ON cp.id = c.cuenta_publicitaria_id
              WHERE pccam.cliente_id = :cid
           ORDER BY cp.nombre, c.nombre',
            ['cid' => $clienteId]
        );
    }

    /** @return list<int> IDs de campañas asignadas a un cliente */
    public function idsCampaniasAsignadas(int $clienteId): array
    {
        $rows = $this->db->select(
            'SELECT campania_id FROM permisos_cliente_campania WHERE cliente_id = :cid',
            ['cid' => $clienteId]
        );

        return array_map(static fn ($r) => (int) $r['campania_id'], $rows);
    }

    /**
     * Reemplaza el set de campañas asignadas a un cliente.
     *
     * @param list<int> $campaniasIds
     */
    public function reemplazarCampaniasAsignadas(int $clienteId, array $campaniasIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'DELETE FROM permisos_cliente_campania WHERE cliente_id = :cid',
                ['cid' => $clienteId]
            );
            foreach ($campaniasIds as $cid) {
                $this->db->execute(
                    'INSERT INTO permisos_cliente_campania (cliente_id, campania_id, visible)
                          VALUES (:cl, :ca, 1)',
                    ['cl' => $clienteId, 'ca' => (int) $cid]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
