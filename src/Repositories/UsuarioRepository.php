<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

final class UsuarioRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT id, correo, nombre_completo, rol, cliente_id, activo, ultimo_acceso_en, creado_en
               FROM usuarios WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarPorCorreo(string $correo): ?array
    {
        return $this->db->selectOne(
            'SELECT id, correo, nombre_completo, rol, cliente_id, activo
               FROM usuarios WHERE correo = :c',
            ['c' => $correo]
        );
    }

    /** @return list<array<string,mixed>> Todos los admins (activos e inactivos) */
    public function listarAdmins(): array
    {
        return $this->db->select(
            "SELECT id, correo, nombre_completo, activo, ultimo_acceso_en, creado_en,
                    twofa_habilitado
               FROM usuarios WHERE rol = 'admin'
           ORDER BY activo DESC, nombre_completo"
        );
    }

    public function cuentaAdminsActivos(): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS n FROM usuarios WHERE rol = 'admin' AND activo = 1"
        );

        return (int) ($row['n'] ?? 0);
    }

    public function crearAdmin(string $correo, string $nombreCompleto, string $passwordHash): int
    {
        $this->db->execute(
            'INSERT INTO usuarios (correo, password_hash, nombre_completo, rol)
                  VALUES (:c, :h, :n, \'admin\')',
            ['c' => $correo, 'h' => $passwordHash, 'n' => $nombreCompleto]
        );

        return $this->db->lastInsertId();
    }

    public function actualizarPerfil(int $id, string $nombreCompleto, string $correo): void
    {
        $this->db->execute(
            'UPDATE usuarios SET nombre_completo = :n, correo = :c WHERE id = :id',
            ['n' => $nombreCompleto, 'c' => $correo, 'id' => $id]
        );
    }

    public function actualizarPassword(int $id, string $passwordHash): void
    {
        $this->db->execute(
            'UPDATE usuarios SET password_hash = :h WHERE id = :id',
            ['h' => $passwordHash, 'id' => $id]
        );
    }

    public function setActivo(int $id, bool $activo): void
    {
        $this->db->execute(
            'UPDATE usuarios SET activo = :a WHERE id = :id',
            ['a' => $activo ? 1 : 0, 'id' => $id]
        );
    }

    public function verificarPasswordActual(int $id, string $passwordActual): bool
    {
        $row = $this->db->selectOne(
            'SELECT password_hash FROM usuarios WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        if ($row === null) {
            return false;
        }

        return password_verify($passwordActual, (string) $row['password_hash']);
    }

    public function correoExiste(string $correo, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT 1 FROM usuarios WHERE correo = :c';
        $params = ['c' => $correo];
        if ($exceptoId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptoId;
        }
        $sql .= ' LIMIT 1';

        return $this->db->selectOne($sql, $params) !== null;
    }
}
