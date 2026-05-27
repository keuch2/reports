<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

final class CuentaPublicitariaRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listarTodas(): array
    {
        return $this->db->select(
            'SELECT id, meta_account_id, nombre, business_manager_id, estado, moneda, zona_horaria,
                    accesible_con_token, ultima_sincronizacion_en
               FROM cuentas_publicitarias ORDER BY nombre'
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT id, meta_account_id, nombre, business_manager_id, estado, moneda, zona_horaria,
                    accesible_con_token, ultima_sincronizacion_en
               FROM cuentas_publicitarias WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPorMetaId(string $metaAccountId): ?array
    {
        return $this->db->selectOne(
            'SELECT id, meta_account_id, nombre FROM cuentas_publicitarias WHERE meta_account_id = :id',
            ['id' => $metaAccountId]
        );
    }

    public function upsert(
        string $metaAccountId,
        string $nombre,
        ?string $businessManagerId,
        ?string $estado,
        ?string $moneda,
        ?string $zonaHoraria,
    ): int {
        $this->db->execute(
            'INSERT INTO cuentas_publicitarias (meta_account_id, nombre, business_manager_id, estado, moneda, zona_horaria, accesible_con_token)
                  VALUES (:mid, :n, :bm, :e, :m, :tz, 1)
                  ON DUPLICATE KEY UPDATE nombre = VALUES(nombre),
                                          business_manager_id = VALUES(business_manager_id),
                                          estado = VALUES(estado),
                                          moneda = VALUES(moneda),
                                          zona_horaria = VALUES(zona_horaria),
                                          accesible_con_token = 1',
            [
                'mid' => $metaAccountId,
                'n' => $nombre,
                'bm' => $businessManagerId,
                'e' => $estado,
                'm' => $moneda,
                'tz' => $zonaHoraria,
            ]
        );

        $row = $this->buscarPorMetaId($metaAccountId);

        return (int) ($row['id'] ?? 0);
    }

    public function marcarSincronizada(int $id): void
    {
        $this->db->execute(
            'UPDATE cuentas_publicitarias SET ultima_sincronizacion_en = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }
}
