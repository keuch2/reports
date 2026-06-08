<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

final class MetricaSnapshotRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Upsert por (nivel, entidad_id, fecha). Idempotente.
     *
     * @param array<string, mixed> $metricasExtendidas
     */
    public function upsert(
        string $nivel,
        int $entidadId,
        string $metaEntidadId,
        string $fecha,
        float $gasto,
        int $impresiones,
        int $alcance,
        ?float $frecuencia,
        int $clicksTotales,
        int $clicksEnlace,
        ?float $ctr,
        ?float $cpc,
        ?float $cpm,
        ?float $costoPorResultado,
        ?int $resultados,
        ?int $conversiones,
        ?int $conversaciones = null,
        ?int $landingPageViews = null,
        ?int $leads = null,
        ?float $costoPorConversacion = null,
        ?int $interacciones = null,
        array $metricasExtendidas = [],
    ): void {
        $this->db->execute(
            'INSERT INTO metricas_snapshots
                (nivel, entidad_id, meta_entidad_id, fecha, gasto, impresiones, alcance, frecuencia,
                 clicks_totales, clicks_enlace, ctr, cpc, cpm, costo_por_resultado, resultados, conversiones,
                 conversaciones, landing_page_views, leads, interacciones, costo_por_conversacion, metricas_extendidas)
              VALUES
                (:n, :eid, :mid, :f, :g, :imp, :alc, :fr, :ct, :ce, :ctr, :cpc, :cpm, :cpr, :res, :con,
                 :conv, :lpv, :lds, :inter, :cpconv, :ext)
              ON DUPLICATE KEY UPDATE
                meta_entidad_id = VALUES(meta_entidad_id),
                gasto = VALUES(gasto),
                impresiones = VALUES(impresiones),
                alcance = VALUES(alcance),
                frecuencia = VALUES(frecuencia),
                clicks_totales = VALUES(clicks_totales),
                clicks_enlace = VALUES(clicks_enlace),
                ctr = VALUES(ctr),
                cpc = VALUES(cpc),
                cpm = VALUES(cpm),
                costo_por_resultado = VALUES(costo_por_resultado),
                resultados = VALUES(resultados),
                conversiones = VALUES(conversiones),
                conversaciones = VALUES(conversaciones),
                landing_page_views = VALUES(landing_page_views),
                leads = VALUES(leads),
                interacciones = VALUES(interacciones),
                costo_por_conversacion = VALUES(costo_por_conversacion),
                metricas_extendidas = VALUES(metricas_extendidas),
                importado_en = NOW()',
            [
                'n' => $nivel, 'eid' => $entidadId, 'mid' => $metaEntidadId, 'f' => $fecha,
                'g' => $gasto, 'imp' => $impresiones, 'alc' => $alcance, 'fr' => $frecuencia,
                'ct' => $clicksTotales, 'ce' => $clicksEnlace,
                'ctr' => $ctr, 'cpc' => $cpc, 'cpm' => $cpm, 'cpr' => $costoPorResultado,
                'res' => $resultados, 'con' => $conversiones,
                'conv' => $conversaciones, 'lpv' => $landingPageViews,
                'lds' => $leads, 'inter' => $interacciones, 'cpconv' => $costoPorConversacion,
                'ext' => $metricasExtendidas === [] ? null : json_encode($metricasExtendidas, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /**
     * Borra todos los snapshots de una importación: los snapshots cuya fecha esté
     * en el rango de la importación y que pertenezcan a anuncios de su cuenta publicitaria.
     *
     * Devuelve la cantidad de filas borradas.
     */
    public function borrarDeImportacion(int $importacionId): int
    {
        return $this->db->execute(
            "DELETE ms FROM metricas_snapshots ms
              JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
              JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              JOIN campanias c ON c.id = cs.campania_id
              JOIN importaciones_meta im ON im.cuenta_publicitaria_id = c.cuenta_publicitaria_id
                                         AND im.id = :imp
             WHERE ms.fecha BETWEEN im.rango_inicio AND im.rango_fin",
            ['imp' => $importacionId]
        );
    }
}
