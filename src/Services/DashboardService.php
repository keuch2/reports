<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Core\Database;
use RuntimeException;

/**
 * Consultas de métricas con validación de permisos del cliente.
 * Cada método valida que la cuenta solicitada pertenezca al cliente.
 */
final class DashboardService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string,mixed>> Cuentas a las que el cliente tiene acceso */
    public function cuentasDelCliente(int $clienteId): array
    {
        return $this->db->select(
            'SELECT cp.id, cp.meta_account_id, cp.nombre, cp.moneda, cp.ultima_sincronizacion_en
               FROM permisos_cliente_cuenta pcc
               JOIN cuentas_publicitarias cp ON cp.id = pcc.cuenta_publicitaria_id
              WHERE pcc.cliente_id = :cid
           ORDER BY cp.nombre',
            ['cid' => $clienteId]
        );
    }

    public function clienteTieneAccesoACuenta(int $clienteId, int $cuentaId): bool
    {
        $row = $this->db->selectOne(
            'SELECT 1 FROM permisos_cliente_cuenta
              WHERE cliente_id = :c AND cuenta_publicitaria_id = :a LIMIT 1',
            ['c' => $clienteId, 'a' => $cuentaId]
        );

        return $row !== null;
    }

    /**
     * Totales agregados de la cuenta en el rango.
     *
     * @return array<string, float|int|null>
     */
    public function totalesPorCuenta(int $clienteId, int $cuentaId, string $desde, string $hasta): array
    {
        if (!$this->clienteTieneAccesoACuenta($clienteId, $cuentaId)) {
            throw new RuntimeException('Sin permisos para esa cuenta.');
        }

        $row = $this->db->selectOne(
            'SELECT
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.alcance), 0) AS alcance,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks_totales,
                COALESCE(SUM(ms.clicks_enlace), 0) AS clicks_enlace,
                CASE WHEN SUM(ms.impresiones) > 0
                     THEN SUM(ms.clicks_totales) / SUM(ms.impresiones) * 100
                     ELSE NULL END AS ctr,
                CASE WHEN SUM(ms.clicks_totales) > 0
                     THEN SUM(ms.gasto) / SUM(ms.clicks_totales)
                     ELSE NULL END AS cpc,
                CASE WHEN SUM(ms.impresiones) > 0
                     THEN SUM(ms.gasto) / SUM(ms.impresiones) * 1000
                     ELSE NULL END AS cpm
              FROM metricas_snapshots ms
              JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = \'ad\'
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              JOIN campanias c ON c.id = cs.campania_id
             WHERE c.cuenta_publicitaria_id = :cuenta
               AND ms.fecha BETWEEN :desde AND :hasta',
            ['cuenta' => $cuentaId, 'desde' => $desde, 'hasta' => $hasta]
        );

        return $row ?? [];
    }

    /** @return list<array<string,mixed>> Una fila por campaña con totales del rango */
    public function porCampania(int $clienteId, int $cuentaId, string $desde, string $hasta): array
    {
        if (!$this->clienteTieneAccesoACuenta($clienteId, $cuentaId)) {
            throw new RuntimeException('Sin permisos para esa cuenta.');
        }

        return $this->db->select(
            'SELECT
                c.id AS campania_id, c.nombre AS campania, c.objetivo, c.estado,
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks,
                CASE WHEN SUM(ms.impresiones) > 0
                     THEN SUM(ms.clicks_totales) / SUM(ms.impresiones) * 100
                     ELSE NULL END AS ctr,
                CASE WHEN SUM(ms.clicks_totales) > 0
                     THEN SUM(ms.gasto) / SUM(ms.clicks_totales)
                     ELSE NULL END AS cpc
              FROM campanias c
         LEFT JOIN conjuntos_anuncios cs ON cs.campania_id = c.id
         LEFT JOIN anuncios a ON a.conjunto_anuncios_id = cs.id
         LEFT JOIN metricas_snapshots ms ON ms.entidad_id = a.id AND ms.nivel = \'ad\'
                                         AND ms.fecha BETWEEN :desde AND :hasta
             WHERE c.cuenta_publicitaria_id = :cuenta
          GROUP BY c.id, c.nombre, c.objetivo, c.estado
          ORDER BY gasto DESC',
            ['cuenta' => $cuentaId, 'desde' => $desde, 'hasta' => $hasta]
        );
    }

    /** @return list<array<string,mixed>> Serie temporal por día */
    public function evolucionDiaria(int $clienteId, int $cuentaId, string $desde, string $hasta): array
    {
        if (!$this->clienteTieneAccesoACuenta($clienteId, $cuentaId)) {
            throw new RuntimeException('Sin permisos para esa cuenta.');
        }

        return $this->db->select(
            'SELECT
                ms.fecha,
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks
              FROM metricas_snapshots ms
              JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = \'ad\'
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              JOIN campanias c ON c.id = cs.campania_id
             WHERE c.cuenta_publicitaria_id = :cuenta
               AND ms.fecha BETWEEN :desde AND :hasta
          GROUP BY ms.fecha
          ORDER BY ms.fecha',
            ['cuenta' => $cuentaId, 'desde' => $desde, 'hasta' => $hasta]
        );
    }
}
