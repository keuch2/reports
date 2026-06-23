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
     * Agrega los resultados por TIPO (conversaciones, leads, interacciones,
     * visitas a destino) clasificando cada adset por su optimization_goal o
     * por el objetivo de su campaña. Cada fila trae cantidad + gasto asociado
     * + costo por unidad. Permite mostrar varios KPIs cuando el cliente tiene
     * mezcla de objetivos.
     *
     * @return list<array{tipo:string, cantidad:int, gasto:float, costo:?float}>
     */
    public function resultadosPorTipoGlobal(int $clienteId, string $desde, string $hasta): array
    {
        $idsCampanias = $this->idsCampaniasAsignadas($clienteId);
        if ($idsCampanias === []) {
            return [];
        }
        [$camsPlaceholders, $camsParams] = $this->placeholders($idsCampanias, 'cam');
        [$exclAnunciosSql, $anunciosParams] = $this->fragmentoAnunciosOcultosGlobal($clienteId);

        return $this->ejecutarResultadosPorTipo(
            "cs.campania_id IN ({$camsPlaceholders}) {$exclAnunciosSql}",
            array_merge(['desde' => $desde, 'hasta' => $hasta], $camsParams, $anunciosParams)
        );
    }

    /**
     * Igual que resultadosPorTipoGlobal pero acotado a una sola campaña.
     *
     * @return list<array{tipo:string, cantidad:int, gasto:float, costo:?float}>
     */
    public function resultadosPorTipoCampania(int $clienteId, int $campaniaId, string $desde, string $hasta): array
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

        return $this->ejecutarResultadosPorTipo(
            "cs.campania_id = :cam {$exclAnunciosSql}",
            $params
        );
    }

    /**
     * Clasifica cada adset por su optimization_goal (o el objetivo de la
     * campaña si está vacío), agrega gasto y la métrica correspondiente,
     * y devuelve una fila por cada tipo con datos.
     *
     * @param array<string,mixed> $params
     * @return list<array{tipo:string, cantidad:int, gasto:float, costo:?float}>
     */
    private function ejecutarResultadosPorTipo(string $whereExtra, array $params): array
    {
        // La clasificación por tipo se hace por fila (cada adset puede tener un
        // optimization_goal distinto) en una subconsulta, y recién después se
        // agrupa por ese 'tipo' ya materializado. Agrupar por el alias de un CASE
        // directamente produce grupos no deterministas bajo MySQL sin
        // ONLY_FULL_GROUP_BY (mezclaba conversaciones de un adset con el tipo
        // 'otros' de otro), lo que descartaba resultados reales del bloque KPI.
        $rows = $this->db->select(
            "SELECT clasificado.tipo AS tipo,
                    SUM(clasificado.gasto) AS gasto,
                    SUM(clasificado.cantidad) AS cantidad
              FROM (
                SELECT
                    CASE
                        WHEN cs.optimization_goal IN ('CONVERSATIONS','REPLIES') THEN 'conversaciones'
                        WHEN cs.optimization_goal IN ('LEAD_GENERATION','QUALITY_LEAD','LEAD') THEN 'leads'
                        WHEN cs.optimization_goal IN ('POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN 'interacciones'
                        WHEN cs.optimization_goal IN ('LANDING_PAGE_VIEWS','LINK_CLICKS') THEN 'visitas'
                        WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN 'leads'
                        WHEN c.objetivo = 'MESSAGES' THEN 'conversaciones'
                        WHEN c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN 'interacciones'
                        WHEN c.objetivo IN ('OUTCOME_TRAFFIC','LINK_CLICKS') THEN 'visitas'
                        ELSE 'otros'
                    END AS tipo,
                    ms.gasto AS gasto,
                    CASE
                        WHEN cs.optimization_goal IN ('CONVERSATIONS','REPLIES') THEN ms.conversaciones
                        WHEN cs.optimization_goal IN ('LEAD_GENERATION','QUALITY_LEAD','LEAD') THEN ms.leads
                        WHEN cs.optimization_goal IN ('POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN ms.interacciones
                        WHEN cs.optimization_goal IN ('LANDING_PAGE_VIEWS','LINK_CLICKS') THEN ms.landing_page_views
                        WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN ms.leads
                        WHEN c.objetivo = 'MESSAGES' THEN ms.conversaciones
                        WHEN c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN ms.interacciones
                        WHEN c.objetivo IN ('OUTCOME_TRAFFIC','LINK_CLICKS') THEN ms.landing_page_views
                        ELSE 0
                    END AS cantidad
                  FROM metricas_snapshots ms
                  JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
                  JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
                  JOIN campanias c ON c.id = cs.campania_id
                 WHERE {$whereExtra}
                   AND ms.fecha BETWEEN :desde AND :hasta
              ) AS clasificado
          GROUP BY clasificado.tipo
            HAVING cantidad > 0 OR gasto > 0
          ORDER BY gasto DESC",
            $params
        );

        $resultado = [];
        foreach ($rows as $r) {
            $tipo = (string) $r['tipo'];
            if ($tipo === 'otros') continue;
            $cantidad = (int) ($r['cantidad'] ?? 0);
            $gasto = (float) ($r['gasto'] ?? 0);
            if ($cantidad <= 0) continue;
            $resultado[] = [
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'gasto' => $gasto,
                'costo' => $cantidad > 0 ? $gasto / $cantidad : null,
            ];
        }
        return $resultado;
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
                     ELSE NULL END AS cpc,
                -- Desglose por tipo de resultado (clasificado por optimization_goal
                -- del adset, o el objetivo de la campaña si no está).
                COALESCE(SUM(CASE
                    WHEN cs.optimization_goal IN ('CONVERSATIONS','REPLIES') THEN ms.conversaciones
                    WHEN cs.optimization_goal IS NULL AND c.objetivo = 'MESSAGES' THEN ms.conversaciones
                    ELSE 0 END), 0) AS conversaciones,
                COALESCE(SUM(CASE
                    WHEN cs.optimization_goal IN ('LEAD_GENERATION','QUALITY_LEAD','LEAD') THEN ms.leads
                    WHEN cs.optimization_goal IS NULL AND c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN ms.leads
                    ELSE 0 END), 0) AS leads,
                COALESCE(SUM(CASE
                    WHEN cs.optimization_goal IN ('POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN ms.interacciones
                    WHEN cs.optimization_goal IS NULL AND c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN ms.interacciones
                    ELSE 0 END), 0) AS interacciones,
                COALESCE(SUM(CASE
                    WHEN cs.optimization_goal IN ('LANDING_PAGE_VIEWS','LINK_CLICKS') THEN ms.landing_page_views
                    WHEN cs.optimization_goal IS NULL AND c.objetivo IN ('OUTCOME_TRAFFIC','LINK_CLICKS') THEN ms.landing_page_views
                    ELSE 0 END), 0) AS visitas
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
     * Meses (YYYY-MM) con snapshots para alguna de las campañas asignadas al
     * cliente. Sirve para el selector del dashboard home. Ordenado más reciente
     * primero.
     *
     * @return list<string>
     */
    public function mesesConDatosDelCliente(int $clienteId): array
    {
        $idsCampanias = $this->idsCampaniasAsignadas($clienteId);
        if ($idsCampanias === []) {
            return [];
        }
        [$camsPlaceholders, $camsParams] = $this->placeholders($idsCampanias, 'cam');

        $rows = $this->db->select(
            "SELECT DISTINCT DATE_FORMAT(ms.fecha, '%Y-%m') AS mes
               FROM metricas_snapshots ms
               JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
               JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              WHERE cs.campania_id IN ({$camsPlaceholders})
              ORDER BY mes DESC",
            $camsParams
        );
        return array_map(static fn ($r) => (string) $r['mes'], $rows);
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
     * Serie temporal diaria de una campaña: gasto + resultados según el
     * optimization_goal predominante de la campaña (o el objective si no hay).
     *
     * @return list<array<string,mixed>>
     */
    public function evolucionDiariaCampania(int $clienteId, int $campaniaId, string $desde, string $hasta): array
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
                ms.fecha,
                COALESCE(SUM(ms.gasto), 0) AS gasto,
                CASE
                    WHEN COALESCE((SELECT GROUP_CONCAT(DISTINCT cs2.optimization_goal)
                                   FROM conjuntos_anuncios cs2
                                  WHERE cs2.campania_id = c.id), '') LIKE '%CONVERSATIONS%' THEN SUM(ms.conversaciones)
                    WHEN COALESCE((SELECT GROUP_CONCAT(DISTINCT cs2.optimization_goal)
                                   FROM conjuntos_anuncios cs2
                                  WHERE cs2.campania_id = c.id), '') LIKE '%LEAD_GENERATION%' THEN SUM(ms.leads)
                    WHEN COALESCE((SELECT GROUP_CONCAT(DISTINCT cs2.optimization_goal)
                                   FROM conjuntos_anuncios cs2
                                  WHERE cs2.campania_id = c.id), '') REGEXP 'POST_ENGAGEMENT|PAGE_LIKES|EVENT_RESPONSES' THEN SUM(ms.interacciones)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN SUM(ms.leads)
                    WHEN c.objetivo = 'MESSAGES' THEN SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN SUM(ms.interacciones)
                    WHEN c.objetivo IN ('OUTCOME_TRAFFIC','LINK_CLICKS') THEN SUM(ms.landing_page_views)
                    WHEN SUM(ms.resultados) > 0 THEN SUM(ms.resultados)
                    ELSE 0
                END AS resultados
              FROM metricas_snapshots ms
              JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              JOIN campanias c ON c.id = cs.campania_id
             WHERE cs.campania_id = :cam
               AND ms.fecha BETWEEN :desde AND :hasta
               {$exclAnunciosSql}
          GROUP BY ms.fecha, c.id, c.objetivo
          ORDER BY ms.fecha",
            $params
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
                -- Aplica la misma jerarquía que adsetsDeCampaniaConMetricas:
                -- optimization_goal predominante del adset gana sobre objective.
                -- Como no podemos elegir un optimization_goal único en SUM, sumamos
                -- por la métrica que más usen los adsets activos: si la mayoría
                -- optimiza CONVERSATIONS sumamos conversaciones, si LEAD_GEN leads,
                -- etc. Caemos al objective si no hay un goal dominante.
                CASE
                    WHEN COALESCE((SELECT GROUP_CONCAT(DISTINCT cs2.optimization_goal)
                                   FROM conjuntos_anuncios cs2
                                  WHERE cs2.campania_id = c.id), '') LIKE '%CONVERSATIONS%' THEN SUM(ms.conversaciones)
                    WHEN COALESCE((SELECT GROUP_CONCAT(DISTINCT cs2.optimization_goal)
                                   FROM conjuntos_anuncios cs2
                                  WHERE cs2.campania_id = c.id), '') LIKE '%LEAD_GENERATION%' THEN SUM(ms.leads)
                    WHEN COALESCE((SELECT GROUP_CONCAT(DISTINCT cs2.optimization_goal)
                                   FROM conjuntos_anuncios cs2
                                  WHERE cs2.campania_id = c.id), '') REGEXP 'POST_ENGAGEMENT|PAGE_LIKES|EVENT_RESPONSES' THEN SUM(ms.interacciones)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN SUM(ms.leads)
                    WHEN c.objetivo = 'MESSAGES' THEN SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN SUM(ms.interacciones)
                    WHEN SUM(ms.resultados) > 0 THEN SUM(ms.resultados)
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
                    WHEN COALESCE((SELECT GROUP_CONCAT(DISTINCT cs2.optimization_goal)
                                   FROM conjuntos_anuncios cs2
                                  WHERE cs2.campania_id = c.id), '') LIKE '%CONVERSATIONS%' AND SUM(ms.conversaciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                    WHEN COALESCE((SELECT GROUP_CONCAT(DISTINCT cs2.optimization_goal)
                                   FROM conjuntos_anuncios cs2
                                  WHERE cs2.campania_id = c.id), '') LIKE '%LEAD_GENERATION%' AND SUM(ms.leads) > 0
                        THEN SUM(ms.gasto) / SUM(ms.leads)
                    WHEN COALESCE((SELECT GROUP_CONCAT(DISTINCT cs2.optimization_goal)
                                   FROM conjuntos_anuncios cs2
                                  WHERE cs2.campania_id = c.id), '') REGEXP 'POST_ENGAGEMENT|PAGE_LIKES|EVENT_RESPONSES'
                         AND SUM(ms.interacciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.interacciones)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') AND SUM(ms.leads) > 0
                        THEN SUM(ms.gasto) / SUM(ms.leads)
                    WHEN c.objetivo = 'MESSAGES' AND SUM(ms.conversaciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES')
                         AND SUM(ms.interacciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.interacciones)
                    WHEN SUM(ms.resultados) > 0 THEN SUM(ms.gasto) / SUM(ms.resultados)
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
             GROUP BY c.id, c.objetivo",
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
                    WHEN cs.optimization_goal IN ('POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN SUM(ms.interacciones)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN SUM(ms.leads)
                    WHEN c.objetivo = 'MESSAGES' THEN SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES')
                         THEN CASE WHEN SUM(ms.conversaciones) > 0 THEN SUM(ms.conversaciones)
                                   ELSE SUM(ms.interacciones) END
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
                    WHEN cs.optimization_goal IN ('POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') AND SUM(ms.interacciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.interacciones)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') AND SUM(ms.leads) > 0
                        THEN SUM(ms.gasto) / SUM(ms.leads)
                    WHEN c.objetivo = 'MESSAGES' AND SUM(ms.conversaciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES')
                         AND SUM(ms.interacciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.interacciones)
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
                    WHEN cs.optimization_goal IN ('POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN SUM(ms.interacciones)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') THEN SUM(ms.leads)
                    WHEN c.objetivo = 'MESSAGES' THEN SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES')
                         THEN CASE WHEN SUM(ms.conversaciones) > 0 THEN SUM(ms.conversaciones)
                                   ELSE SUM(ms.interacciones) END
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
                    WHEN cs.optimization_goal IN ('POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') AND SUM(ms.interacciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.interacciones)
                    WHEN c.objetivo IN ('OUTCOME_LEADS','LEAD_GENERATION') AND SUM(ms.leads) > 0
                        THEN SUM(ms.gasto) / SUM(ms.leads)
                    WHEN c.objetivo = 'MESSAGES' AND SUM(ms.conversaciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.conversaciones)
                    WHEN c.objetivo IN ('OUTCOME_ENGAGEMENT','POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES')
                         AND SUM(ms.interacciones) > 0
                        THEN SUM(ms.gasto) / SUM(ms.interacciones)
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
