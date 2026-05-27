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
        array $metricasExtendidas = [],
    ): void {
        $this->db->execute(
            'INSERT INTO metricas_snapshots
                (nivel, entidad_id, meta_entidad_id, fecha, gasto, impresiones, alcance, frecuencia,
                 clicks_totales, clicks_enlace, ctr, cpc, cpm, costo_por_resultado, resultados, conversiones,
                 metricas_extendidas)
              VALUES
                (:n, :eid, :mid, :f, :g, :imp, :alc, :fr, :ct, :ce, :ctr, :cpc, :cpm, :cpr, :res, :con, :ext)
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
                metricas_extendidas = VALUES(metricas_extendidas),
                importado_en = NOW()',
            [
                'n' => $nivel, 'eid' => $entidadId, 'mid' => $metaEntidadId, 'f' => $fecha,
                'g' => $gasto, 'imp' => $impresiones, 'alc' => $alcance, 'fr' => $frecuencia,
                'ct' => $clicksTotales, 'ce' => $clicksEnlace,
                'ctr' => $ctr, 'cpc' => $cpc, 'cpm' => $cpm, 'cpr' => $costoPorResultado,
                'res' => $resultados, 'con' => $conversiones,
                'ext' => $metricasExtendidas === [] ? null : json_encode($metricasExtendidas, JSON_UNESCAPED_UNICODE),
            ]
        );
    }
}
