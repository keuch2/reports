<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Core\Database;
use RuntimeException;

/**
 * Consultas de métricas con validación de permisos del cliente.
 *
 * NUEVO MODELO (desde migración 0010):
 * - El cliente NO está asignado a cuentas; está asignado a CAMPAÑAS individuales.
 * - permisos_cliente_campania.cliente_id + campania_id = campaña asignada y visible.
 * - permisos_cliente_anuncio.visible = 0 sigue valiendo para ocultar anuncios
 *   específicos dentro de una campaña asignada (caso uso: ocultar creativos en testing).
 */
final class DashboardService
{
    public function __construct(
        private readonly Database $db,
        private readonly PermisosService $permisos,
    ) {
    }

    /**
     * Campañas asignadas al cliente. Cada fila incluye datos de la cuenta a la que
     * pertenece (sirve para mostrar contexto: "Black Friday — Cuenta Foo").
     *
     * @return list<array<string,mixed>>
     */
    public function campaniasDelCliente(int $clienteId): array
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

    public function clienteTieneAccesoACampania(int $clienteId, int $campaniaId): bool
    {
        $row = $this->db->selectOne(
            'SELECT 1 FROM permisos_cliente_campania
              WHERE cliente_id = :c AND campania_id = :ca LIMIT 1',
            ['c' => $clienteId, 'ca' => $campaniaId]
        );

        return $row !== null;
    }

    /**
     * Totales agregados sobre todas las campañas asignadas (descontando anuncios ocultos).
     *
     * @return array<string, float|int|null>
     */
    public function totalesGlobales(int $clienteId, string $desde, string $hasta): array
    {
        $idsCampanias = $this->idsCampaniasAsignadas($clienteId);
        if ($idsCampanias === []) {
            return $this->cerosTotal();
        }
        [$camsPlaceholders, $camsParams] = $this->placeholders($idsCampanias, 'cam');
        [$exclAnunciosSql, $anunciosParams] = $this->fragmentoAnunciosOcultosGlobal($clienteId);

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
             WHERE cs.campania_id IN ({$camsPlaceholders})
               AND ms.fecha BETWEEN :desde AND :hasta
               {$exclAnunciosSql}",
            array_merge(['desde' => $desde, 'hasta' => $hasta], $camsParams, $anunciosParams)
        );

        return $row ?? $this->cerosTotal();
    }

    /**
     * Una fila por campaña asignada con sus totales del rango.
     *
     * @return list<array<string,mixed>>
     */
    public function porCampania(int $clienteId, string $desde, string $hasta): array
    {
        $idsCampanias = $this->idsCampaniasAsignadas($clienteId);
        if ($idsCampanias === []) {
            return [];
        }
        [$camsPlaceholders, $camsParams] = $this->placeholders($idsCampanias, 'cam');
        [$exclAnunciosSql, $anunciosParams] = $this->fragmentoAnunciosOcultosGlobal($clienteId);

        return $this->db->select(
            "SELECT
                c.id AS campania_id, c.nombre AS campania, c.objetivo, c.estado,
                cp.nombre AS cuenta_nombre, cp.moneda,
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
              JOIN cuentas_publicitarias cp ON cp.id = c.cuenta_publicitaria_id
         LEFT JOIN conjuntos_anuncios cs ON cs.campania_id = c.id
         LEFT JOIN anuncios a ON a.conjunto_anuncios_id = cs.id {$exclAnunciosSql}
         LEFT JOIN metricas_snapshots ms ON ms.entidad_id = a.id AND ms.nivel = 'ad'
                                         AND ms.fecha BETWEEN :desde AND :hasta
             WHERE c.id IN ({$camsPlaceholders})
          GROUP BY c.id, c.nombre, c.objetivo, c.estado, cp.nombre, cp.moneda
          ORDER BY gasto DESC",
            array_merge(['desde' => $desde, 'hasta' => $hasta], $camsParams, $anunciosParams)
        );
    }

    /**
     * Serie temporal diaria agregada sobre las campañas asignadas.
     *
     * @return list<array<string,mixed>>
     */
    public function evolucionDiaria(int $clienteId, string $desde, string $hasta): array
    {
        $idsCampanias = $this->idsCampaniasAsignadas($clienteId);
        if ($idsCampanias === []) {
            return [];
        }
        [$camsPlaceholders, $camsParams] = $this->placeholders($idsCampanias, 'cam');
        [$exclAnunciosSql, $anunciosParams] = $this->fragmentoAnunciosOcultosGlobal($clienteId);

        return $this->db->select(
            "SELECT
                ms.fecha,
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks
              FROM metricas_snapshots ms
              JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
             WHERE cs.campania_id IN ({$camsPlaceholders})
               AND ms.fecha BETWEEN :desde AND :hasta
               {$exclAnunciosSql}
          GROUP BY ms.fecha
          ORDER BY ms.fecha",
            array_merge(['desde' => $desde, 'hasta' => $hasta], $camsParams, $anunciosParams)
        );
    }

    /**
     * Totales de una campaña específica (sirve para detalle).
     *
     * @return array<string, mixed>
     */
    public function totalesCampania(int $clienteId, int $campaniaId, string $desde, string $hasta): array
    {
        $this->asegurarAccesoCampania($clienteId, $campaniaId);
        $params = ['cam' => $campaniaId, 'desde' => $desde, 'hasta' => $hasta];
        $exclAnunciosSql = '';
        $ocultos = $this->permisos->anunciosOcultosDeCampania($clienteId, $campaniaId);
        if ($ocultos !== []) {
            [$ph, $exclParams] = $this->placeholders($ocultos, 'exc_a');
            $exclAnunciosSql = "AND a.id NOT IN ({$ph})";
            $params = array_merge($params, $exclParams);
        }

        $row = $this->db->selectOne(
            "SELECT
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.alcance), 0) AS alcance,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks,
                COALESCE(SUM(ms.clicks_enlace), 0) AS clicks_enlace,
                COALESCE(SUM(ms.conversaciones), 0) AS conversaciones,
                COALESCE(SUM(ms.landing_page_views), 0) AS landing_page_views,
                COALESCE(SUM(ms.leads), 0) AS leads,
                CASE
                    WHEN SUM(ms.resultados) > 0 THEN SUM(ms.resultados)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN SUM(ms.leads)
                    WHEN c.objetivo IN ('MESSAGES','OUTCOME_ENGAGEMENT') THEN SUM(ms.conversaciones)
                    ELSE 0
                END AS resultados,
                CASE WHEN SUM(ms.impresiones) > 0
                     THEN SUM(ms.clicks_totales) / SUM(ms.impresiones) * 100
                     ELSE NULL END AS ctr,
                CASE WHEN SUM(ms.clicks_totales) > 0
                     THEN SUM(ms.gasto) / SUM(ms.clicks_totales)
                     ELSE NULL END AS cpc,
                CASE WHEN SUM(ms.impresiones) > 0
                     THEN SUM(ms.gasto) / SUM(ms.impresiones) * 1000
                     ELSE NULL END AS cpm,
                CASE
                    WHEN SUM(ms.resultados) > 0 THEN SUM(ms.gasto) / SUM(ms.resultados)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') AND SUM(ms.leads) > 0
                        THEN SUM(ms.gasto) / SUM(ms.leads)
                    WHEN c.objetivo IN ('MESSAGES','OUTCOME_ENGAGEMENT') AND SUM(ms.conversaciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                    ELSE NULL
                END AS costo_por_resultado,
                CASE WHEN SUM(ms.conversaciones) > 0
                     THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                     ELSE NULL END AS costo_por_conversacion
              FROM metricas_snapshots ms
              JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              JOIN campanias c ON c.id = cs.campania_id
             WHERE cs.campania_id = :cam
               AND ms.fecha BETWEEN :desde AND :hasta
               {$exclAnunciosSql}
             GROUP BY c.objetivo",
            $params
        );

        return $row ?? $this->cerosCampania();
    }

    /**
     * Adsets visibles de una campaña con métricas agregadas (suma de sus anuncios).
     *
     * @return list<array<string,mixed>>
     */
    public function adsetsDeCampaniaConMetricas(int $clienteId, int $campaniaId, string $desde, string $hasta): array
    {
        $this->asegurarAccesoCampania($clienteId, $campaniaId);
        $params = ['cam' => $campaniaId, 'desde' => $desde, 'hasta' => $hasta];
        $exclAnunciosSql = '';
        $ocultos = $this->permisos->anunciosOcultosDeCampania($clienteId, $campaniaId);
        if ($ocultos !== []) {
            [$ph, $exclParams] = $this->placeholders($ocultos, 'exc_a');
            $exclAnunciosSql = "AND a.id NOT IN ({$ph})";
            $params = array_merge($params, $exclParams);
        }

        return $this->db->select(
            "SELECT
                cs.id, cs.nombre AS adset_nombre, cs.estado, cs.optimization_goal,
                c.objetivo AS objetivo_campania,
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.alcance), 0) AS alcance,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks,
                COALESCE(SUM(ms.conversaciones), 0) AS conversaciones,
                COALESCE(SUM(ms.landing_page_views), 0) AS landing_page_views,
                COALESCE(SUM(ms.leads), 0) AS leads,
                -- Resultados: mismo criterio que Meta Ads Manager. La columna
                -- de Resultados refleja el optimization_goal del adset (es lo que
                -- realmente se optimiza). Si no esta seteado, cae al objetivo de
                -- la campaña.
                CASE
                    WHEN cs.optimization_goal IN ('CONVERSATIONS','REPLIES') THEN SUM(ms.conversaciones)
                    WHEN cs.optimization_goal IN ('LEAD_GENERATION','QUALITY_LEAD','LEAD') THEN SUM(ms.leads)
                    WHEN cs.optimization_goal = 'LANDING_PAGE_VIEWS' THEN SUM(ms.landing_page_views)
                    WHEN cs.optimization_goal = 'LINK_CLICKS' THEN SUM(ms.clicks_totales)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN SUM(ms.leads)
                    WHEN c.objetivo IN ('MESSAGES','OUTCOME_ENGAGEMENT') THEN SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_TRAFFIC','LINK_CLICKS') THEN SUM(ms.landing_page_views)
                    WHEN SUM(ms.resultados) > 0 THEN SUM(ms.resultados)
                    ELSE 0
                END AS resultados,
                CASE WHEN SUM(ms.impresiones) > 0
                     THEN SUM(ms.clicks_totales) / SUM(ms.impresiones) * 100
                     ELSE NULL END AS ctr,
                CASE WHEN SUM(ms.clicks_totales) > 0
                     THEN SUM(ms.gasto) / SUM(ms.clicks_totales)
                     ELSE NULL END AS cpc,
                CASE
                    WHEN cs.optimization_goal IN ('CONVERSATIONS','REPLIES') AND SUM(ms.conversaciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                    WHEN cs.optimization_goal IN ('LEAD_GENERATION','QUALITY_LEAD','LEAD') AND SUM(ms.leads) > 0
                        THEN SUM(ms.gasto) / SUM(ms.leads)
                    WHEN cs.optimization_goal = 'LANDING_PAGE_VIEWS' AND SUM(ms.landing_page_views) > 0
                        THEN SUM(ms.gasto) / SUM(ms.landing_page_views)
                    WHEN cs.optimization_goal = 'LINK_CLICKS' AND SUM(ms.clicks_totales) > 0
                        THEN SUM(ms.gasto) / SUM(ms.clicks_totales)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') AND SUM(ms.leads) > 0
                        THEN SUM(ms.gasto) / SUM(ms.leads)
                    WHEN c.objetivo IN ('MESSAGES','OUTCOME_ENGAGEMENT') AND SUM(ms.conversaciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_TRAFFIC','LINK_CLICKS') AND SUM(ms.landing_page_views) > 0
                        THEN SUM(ms.gasto) / SUM(ms.landing_page_views)
                    WHEN SUM(ms.resultados) > 0 THEN SUM(ms.gasto) / SUM(ms.resultados)
                    ELSE NULL
                END AS costo_por_resultado,
                CASE WHEN SUM(ms.conversaciones) > 0
                     THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                     ELSE NULL END AS costo_por_conversacion
              FROM conjuntos_anuncios cs
              JOIN campanias c ON c.id = cs.campania_id
         LEFT JOIN anuncios a ON a.conjunto_anuncios_id = cs.id {$exclAnunciosSql}
         LEFT JOIN metricas_snapshots ms ON ms.entidad_id = a.id AND ms.nivel = 'ad'
                                         AND ms.fecha BETWEEN :desde AND :hasta
             WHERE cs.campania_id = :cam
          GROUP BY cs.id, cs.nombre, cs.estado, cs.optimization_goal, c.objetivo
          HAVING SUM(ms.gasto) > 0 OR SUM(ms.impresiones) > 0
          ORDER BY gasto DESC",
            $params
        );
    }

    /**
     * Anuncios visibles de una campaña con métricas + datos del creative.
     *
     * @return list<array<string,mixed>>
     */
    public function anunciosDeCampaniaConMetricas(int $clienteId, int $campaniaId, string $desde, string $hasta): array
    {
        $this->asegurarAccesoCampania($clienteId, $campaniaId);
        $params = ['cam' => $campaniaId, 'desde' => $desde, 'hasta' => $hasta];
        $exclAnunciosSql = '';
        $ocultos = $this->permisos->anunciosOcultosDeCampania($clienteId, $campaniaId);
        if ($ocultos !== []) {
            [$ph, $exclParams] = $this->placeholders($ocultos, 'exc_a');
            $exclAnunciosSql = "AND a.id NOT IN ({$ph})";
            $params = array_merge($params, $exclParams);
        }

        return $this->db->select(
            "SELECT
                a.id, a.nombre, a.tipo, a.thumbnail_url, a.image_url,
                a.cuerpo, a.titulo, a.link_url, a.call_to_action, a.permalink_url, a.estado,
                cs.id AS adset_id, cs.nombre AS adset_nombre, cs.optimization_goal,
                c.objetivo AS objetivo_campania,
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                COALESCE(SUM(ms.impresiones), 0) AS impresiones,
                COALESCE(SUM(ms.clicks_totales), 0) AS clicks,
                COALESCE(SUM(ms.clicks_enlace), 0) AS clicks_enlace,
                COALESCE(SUM(ms.conversaciones), 0) AS conversaciones,
                COALESCE(SUM(ms.landing_page_views), 0) AS landing_page_views,
                COALESCE(SUM(ms.leads), 0) AS leads,
                -- Mismo criterio que Meta Ads Manager: optimization_goal del adset
                -- gana sobre objective de campaña. Campañas LEADS con adsets que
                -- optimizan CONVERSATIONS reportan conversaciones, no leads.
                CASE
                    WHEN cs.optimization_goal IN ('CONVERSATIONS','REPLIES') THEN SUM(ms.conversaciones)
                    WHEN cs.optimization_goal IN ('LEAD_GENERATION','QUALITY_LEAD','LEAD') THEN SUM(ms.leads)
                    WHEN cs.optimization_goal = 'LANDING_PAGE_VIEWS' THEN SUM(ms.landing_page_views)
                    WHEN cs.optimization_goal = 'LINK_CLICKS' THEN SUM(ms.clicks_totales)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN SUM(ms.leads)
                    WHEN c.objetivo IN ('MESSAGES','OUTCOME_ENGAGEMENT') THEN SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_TRAFFIC','LINK_CLICKS') THEN SUM(ms.landing_page_views)
                    WHEN SUM(ms.resultados) > 0 THEN SUM(ms.resultados)
                    ELSE 0
                END AS resultados,
                CASE WHEN SUM(ms.impresiones) > 0
                     THEN SUM(ms.clicks_totales) / SUM(ms.impresiones) * 100
                     ELSE NULL END AS ctr,
                CASE WHEN SUM(ms.clicks_totales) > 0
                     THEN SUM(ms.gasto) / SUM(ms.clicks_totales)
                     ELSE NULL END AS cpc,
                CASE
                    WHEN cs.optimization_goal IN ('CONVERSATIONS','REPLIES') AND SUM(ms.conversaciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                    WHEN cs.optimization_goal IN ('LEAD_GENERATION','QUALITY_LEAD','LEAD') AND SUM(ms.leads) > 0
                        THEN SUM(ms.gasto) / SUM(ms.leads)
                    WHEN cs.optimization_goal = 'LANDING_PAGE_VIEWS' AND SUM(ms.landing_page_views) > 0
                        THEN SUM(ms.gasto) / SUM(ms.landing_page_views)
                    WHEN cs.optimization_goal = 'LINK_CLICKS' AND SUM(ms.clicks_totales) > 0
                        THEN SUM(ms.gasto) / SUM(ms.clicks_totales)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') AND SUM(ms.leads) > 0
                        THEN SUM(ms.gasto) / SUM(ms.leads)
                    WHEN c.objetivo IN ('MESSAGES','OUTCOME_ENGAGEMENT') AND SUM(ms.conversaciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_TRAFFIC','LINK_CLICKS') AND SUM(ms.landing_page_views) > 0
                        THEN SUM(ms.gasto) / SUM(ms.landing_page_views)
                    WHEN SUM(ms.resultados) > 0 THEN SUM(ms.gasto) / SUM(ms.resultados)
                    ELSE NULL
                END AS costo_por_resultado,
                CASE WHEN SUM(ms.conversaciones) > 0
                     THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                     ELSE NULL END AS costo_por_conversacion
              FROM anuncios a
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              JOIN campanias c ON c.id = cs.campania_id
         LEFT JOIN metricas_snapshots ms ON ms.entidad_id = a.id AND ms.nivel = 'ad'
                                         AND ms.fecha BETWEEN :desde AND :hasta
             WHERE cs.campania_id = :cam
               {$exclAnunciosSql}
          GROUP BY a.id, a.nombre, a.tipo, a.thumbnail_url, a.image_url,
                   a.cuerpo, a.titulo, a.link_url, a.call_to_action, a.permalink_url, a.estado,
                   cs.id, cs.nombre, cs.optimization_goal, c.objetivo
          HAVING SUM(ms.gasto) > 0 OR SUM(ms.impresiones) > 0
          ORDER BY gasto DESC",
            $params
        );
    }

    // ─── Helpers ───

    /** @return list<int> */
    private function idsCampaniasAsignadas(int $clienteId): array
    {
        $rows = $this->db->select(
            'SELECT campania_id FROM permisos_cliente_campania WHERE cliente_id = :c',
            ['c' => $clienteId]
        );

        return array_map(static fn ($r) => (int) $r['campania_id'], $rows);
    }

    /**
     * Devuelve fragmento SQL "AND a.id NOT IN (:p1,:p2,...)" para excluir anuncios
     * ocultos para el cliente entre todas sus campañas asignadas.
     *
     * @return array{0:string, 1:array<string,int>}
     */
    private function fragmentoAnunciosOcultosGlobal(int $clienteId): array
    {
        $rows = $this->db->select(
            'SELECT pa.anuncio_id
               FROM permisos_cliente_anuncio pa
              WHERE pa.cliente_id = :c AND pa.visible = 0',
            ['c' => $clienteId]
        );
        if ($rows === []) {
            return ['', []];
        }
        $ids = array_map(static fn ($r) => (int) $r['anuncio_id'], $rows);
        [$ph, $params] = $this->placeholders($ids, 'exc_a');

        return ["AND a.id NOT IN ({$ph})", $params];
    }

    /**
     * @param list<int> $ids
     * @return array{0:string, 1:array<string,int>}
     */
    private function placeholders(array $ids, string $prefijo): array
    {
        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = "{$prefijo}_{$i}";
            $placeholders[] = ":{$key}";
            $params[$key] = (int) $id;
        }

        return [implode(',', $placeholders), $params];
    }

    private function asegurarAccesoCampania(int $clienteId, int $campaniaId): void
    {
        if (!$this->clienteTieneAccesoACampania($clienteId, $campaniaId)) {
            throw new RuntimeException('Sin permisos para esa campaña.');
        }
    }

    /** @return array<string, int|float|null> */
    private function cerosTotal(): array
    {
        return [
            'gasto' => 0, 'impresiones' => 0, 'alcance' => 0,
            'clicks_totales' => 0, 'clicks_enlace' => 0,
            'ctr' => null, 'cpc' => null, 'cpm' => null,
        ];
    }

    /** @return array<string, int|float|null> */
    private function cerosCampania(): array
    {
        return [
            'gasto' => 0, 'impresiones' => 0, 'alcance' => 0,
            'clicks' => 0, 'clicks_enlace' => 0,
            'conversaciones' => 0, 'landing_page_views' => 0,
            'leads' => 0, 'resultados' => 0,
            'ctr' => null, 'cpc' => null, 'cpm' => null,
            'costo_por_resultado' => null, 'costo_por_conversacion' => null,
        ];
    }
}
