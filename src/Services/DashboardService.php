<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Core\Database;
use RuntimeException;

/**
 * Consultas de métricas con validación de permisos del cliente.
 * Cada método valida que la cuenta solicitada pertenezca al cliente y
 * descarta campañas/anuncios marcados como ocultos por el admin.
 */
final class DashboardService
{
    public function __construct(
        private readonly Database $db,
        private readonly PermisosService $permisos,
    ) {
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
     * Totales agregados de la cuenta en el rango, descontando entidades ocultas.
     *
     * @return array<string, float|int|null>
     */
    public function totalesPorCuenta(int $clienteId, int $cuentaId, string $desde, string $hasta): array
    {
        $this->asegurarAcceso($clienteId, $cuentaId);
        [$exclCampaniasSql, $exclAnunciosSql, $params] = $this->fragmentosExclusion($clienteId, $cuentaId);

        $row = $this->db->selectOne(
            "SELECT
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
              JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              JOIN campanias c ON c.id = cs.campania_id
             WHERE c.cuenta_publicitaria_id = :cuenta
               AND ms.fecha BETWEEN :desde AND :hasta
               {$exclCampaniasSql}
               {$exclAnunciosSql}",
            array_merge(['cuenta' => $cuentaId, 'desde' => $desde, 'hasta' => $hasta], $params)
        );

        return $row ?? [];
    }

    /** @return list<array<string,mixed>> Una fila por campaña visible con totales del rango */
    public function porCampania(int $clienteId, int $cuentaId, string $desde, string $hasta): array
    {
        $this->asegurarAcceso($clienteId, $cuentaId);
        [$exclCampaniasSql, $exclAnunciosSql, $params] = $this->fragmentosExclusion($clienteId, $cuentaId);

        $sql = "SELECT
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
         LEFT JOIN anuncios a ON a.conjunto_anuncios_id = cs.id {$exclAnunciosSql}
         LEFT JOIN metricas_snapshots ms ON ms.entidad_id = a.id AND ms.nivel = 'ad'
                                         AND ms.fecha BETWEEN :desde AND :hasta
             WHERE c.cuenta_publicitaria_id = :cuenta
               {$exclCampaniasSql}
          GROUP BY c.id, c.nombre, c.objetivo, c.estado
          ORDER BY gasto DESC";

        return $this->db->select(
            $sql,
            array_merge(['cuenta' => $cuentaId, 'desde' => $desde, 'hasta' => $hasta], $params)
        );
    }

    /** @return list<array<string,mixed>> Serie temporal por día */
    public function evolucionDiaria(int $clienteId, int $cuentaId, string $desde, string $hasta): array
    {
        $this->asegurarAcceso($clienteId, $cuentaId);
        [$exclCampaniasSql, $exclAnunciosSql, $params] = $this->fragmentosExclusion($clienteId, $cuentaId);

        return $this->db->select(
            "SELECT
                ms.fecha,
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks
              FROM metricas_snapshots ms
              JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              JOIN campanias c ON c.id = cs.campania_id
             WHERE c.cuenta_publicitaria_id = :cuenta
               AND ms.fecha BETWEEN :desde AND :hasta
               {$exclCampaniasSql}
               {$exclAnunciosSql}
          GROUP BY ms.fecha
          ORDER BY ms.fecha",
            array_merge(['cuenta' => $cuentaId, 'desde' => $desde, 'hasta' => $hasta], $params)
        );
    }

    /**
     * Totales agregados de una campaña específica para un cliente.
     *
     * @return array<string, mixed>
     */
    public function totalesCampania(int $clienteId, int $campaniaId, string $desde, string $hasta): array
    {
        $params = ['cam' => $campaniaId, 'desde' => $desde, 'hasta' => $hasta];
        $exclAnunciosSql = '';
        $ocultos = $this->permisos->anunciosOcultosDeCampania($clienteId, $campaniaId);
        if ($ocultos !== []) {
            $placeholders = [];
            foreach ($ocultos as $i => $id) {
                $key = "exc_a_{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $id;
            }
            $exclAnunciosSql = 'AND a.id NOT IN (' . implode(',', $placeholders) . ')';
        }

        $row = $this->db->selectOne(
            "SELECT
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.alcance), 0) AS alcance,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks,
                CASE WHEN SUM(ms.impresiones) > 0
                     THEN SUM(ms.clicks_totales) / SUM(ms.impresiones) * 100
                     ELSE NULL END AS ctr,
                CASE WHEN SUM(ms.clicks_totales) > 0
                     THEN SUM(ms.gasto) / SUM(ms.clicks_totales)
                     ELSE NULL END AS cpc
              FROM metricas_snapshots ms
              JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
             WHERE cs.campania_id = :cam
               AND ms.fecha BETWEEN :desde AND :hasta
               {$exclAnunciosSql}",
            $params
        );

        return $row ?? [];
    }

    /** @return list<array<string,mixed>> Anuncios visibles de una campaña con sus métricas agregadas */
    public function anunciosDeCampaniaConMetricas(int $clienteId, int $campaniaId, string $desde, string $hasta): array
    {
        $params = ['cam' => $campaniaId, 'desde' => $desde, 'hasta' => $hasta];
        $exclAnunciosSql = '';
        $ocultos = $this->permisos->anunciosOcultosDeCampania($clienteId, $campaniaId);
        if ($ocultos !== []) {
            $placeholders = [];
            foreach ($ocultos as $i => $id) {
                $key = "exc_a_{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $id;
            }
            $exclAnunciosSql = 'AND a.id NOT IN (' . implode(',', $placeholders) . ')';
        }

        return $this->db->select(
            "SELECT
                a.id, a.nombre, a.tipo, a.thumbnail_url, a.estado,
                cs.nombre AS adset_nombre,
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks,
                CASE WHEN SUM(ms.impresiones) > 0
                     THEN SUM(ms.clicks_totales) / SUM(ms.impresiones) * 100
                     ELSE NULL END AS ctr
              FROM anuncios a
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
         LEFT JOIN metricas_snapshots ms ON ms.entidad_id = a.id AND ms.nivel = 'ad'
                                         AND ms.fecha BETWEEN :desde AND :hasta
             WHERE cs.campania_id = :cam
               {$exclAnunciosSql}
          GROUP BY a.id, a.nombre, a.tipo, a.thumbnail_url, a.estado, cs.nombre
          ORDER BY gasto DESC",
            $params
        );
    }

    private function asegurarAcceso(int $clienteId, int $cuentaId): void
    {
        if (!$this->clienteTieneAccesoACuenta($clienteId, $cuentaId)) {
            throw new RuntimeException('Sin permisos para esa cuenta.');
        }
    }

    /**
     * Devuelve fragmentos SQL y params para excluir campañas/anuncios ocultos.
     *
     * @return array{0:string, 1:string, 2:array<string, int>}
     */
    private function fragmentosExclusion(int $clienteId, int $cuentaId): array
    {
        $params = [];
        $exclCampaniasSql = '';
        $exclAnunciosSql = '';

        $camsOcultas = $this->permisos->campaniasOcultas($clienteId, $cuentaId);
        if ($camsOcultas !== []) {
            $placeholders = [];
            foreach ($camsOcultas as $i => $id) {
                $key = "exc_c_{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $id;
            }
            $exclCampaniasSql = 'AND c.id NOT IN (' . implode(',', $placeholders) . ')';
        }

        // Anuncios ocultos a nivel cliente: traemos los IDs explícitos.
        $anunciosOcultos = $this->db->select(
            'SELECT a.id
               FROM permisos_cliente_anuncio p
               JOIN anuncios a ON a.id = p.anuncio_id
               JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
               JOIN campanias c ON c.id = cs.campania_id
              WHERE p.cliente_id = :cli AND p.visible = 0
                AND c.cuenta_publicitaria_id = :cuenta',
            ['cli' => $clienteId, 'cuenta' => $cuentaId]
        );
        if ($anunciosOcultos !== []) {
            $placeholders = [];
            foreach ($anunciosOcultos as $i => $r) {
                $key = "exc_a_{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = (int) $r['id'];
            }
            $exclAnunciosSql = 'AND a.id NOT IN (' . implode(',', $placeholders) . ')';
        }

        return [$exclCampaniasSql, $exclAnunciosSql, $params];
    }
}
