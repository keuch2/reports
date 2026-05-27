<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

/**
 * Upserts de campañas, conjuntos y anuncios. Idempotente: re-importar el mismo
 * rango no duplica filas; actualiza columnas mutables.
 */
final class EntidadesMetaRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function upsertCampania(
        string $metaCampaignId,
        int $cuentaPublicitariaId,
        string $nombre,
        ?string $objetivo,
        ?string $estado,
        ?string $fechaInicio,
        ?string $fechaFin,
        ?float $presupuestoDiario,
        ?float $presupuestoTotal,
    ): int {
        $this->db->execute(
            'INSERT INTO campanias (meta_campaign_id, cuenta_publicitaria_id, nombre, objetivo, estado,
                                    fecha_inicio, fecha_fin, presupuesto_diario, presupuesto_total)
                  VALUES (:mid, :cid, :n, :obj, :est, :fi, :ff, :pd, :pt)
                  ON DUPLICATE KEY UPDATE nombre = VALUES(nombre),
                                          objetivo = VALUES(objetivo),
                                          estado = VALUES(estado),
                                          fecha_inicio = VALUES(fecha_inicio),
                                          fecha_fin = VALUES(fecha_fin),
                                          presupuesto_diario = VALUES(presupuesto_diario),
                                          presupuesto_total = VALUES(presupuesto_total)',
            [
                'mid' => $metaCampaignId, 'cid' => $cuentaPublicitariaId, 'n' => $nombre, 'obj' => $objetivo,
                'est' => $estado, 'fi' => $fechaInicio, 'ff' => $fechaFin,
                'pd' => $presupuestoDiario, 'pt' => $presupuestoTotal,
            ]
        );
        $row = $this->db->selectOne('SELECT id FROM campanias WHERE meta_campaign_id = :id', ['id' => $metaCampaignId]);

        return (int) ($row['id'] ?? 0);
    }

    public function upsertAdset(
        string $metaAdsetId,
        int $campaniaId,
        string $nombre,
        ?string $estado,
        ?string $segmentacionJson,
        ?float $presupuestoDiario,
        ?float $presupuestoTotal,
        ?string $optimizationGoal,
    ): int {
        $this->db->execute(
            'INSERT INTO conjuntos_anuncios (meta_adset_id, campania_id, nombre, estado, segmentacion,
                                             presupuesto_diario, presupuesto_total, optimization_goal)
                  VALUES (:mid, :cid, :n, :est, :seg, :pd, :pt, :og)
                  ON DUPLICATE KEY UPDATE nombre = VALUES(nombre),
                                          estado = VALUES(estado),
                                          segmentacion = VALUES(segmentacion),
                                          presupuesto_diario = VALUES(presupuesto_diario),
                                          presupuesto_total = VALUES(presupuesto_total),
                                          optimization_goal = VALUES(optimization_goal)',
            [
                'mid' => $metaAdsetId, 'cid' => $campaniaId, 'n' => $nombre, 'est' => $estado,
                'seg' => $segmentacionJson, 'pd' => $presupuestoDiario, 'pt' => $presupuestoTotal,
                'og' => $optimizationGoal,
            ]
        );
        $row = $this->db->selectOne('SELECT id FROM conjuntos_anuncios WHERE meta_adset_id = :id', ['id' => $metaAdsetId]);

        return (int) ($row['id'] ?? 0);
    }

    public function upsertAnuncio(
        string $metaAdId,
        int $conjuntoAnunciosId,
        string $nombre,
        ?string $creativeId,
        ?string $tipo,
        ?string $thumbnailUrl,
        ?string $estado,
    ): int {
        $this->db->execute(
            'INSERT INTO anuncios (meta_ad_id, conjunto_anuncios_id, nombre, creative_id, tipo, thumbnail_url, estado)
                  VALUES (:mid, :sid, :n, :cre, :t, :th, :est)
                  ON DUPLICATE KEY UPDATE nombre = VALUES(nombre),
                                          creative_id = VALUES(creative_id),
                                          tipo = VALUES(tipo),
                                          thumbnail_url = VALUES(thumbnail_url),
                                          estado = VALUES(estado)',
            [
                'mid' => $metaAdId, 'sid' => $conjuntoAnunciosId, 'n' => $nombre,
                'cre' => $creativeId, 't' => $tipo, 'th' => $thumbnailUrl, 'est' => $estado,
            ]
        );
        $row = $this->db->selectOne('SELECT id FROM anuncios WHERE meta_ad_id = :id', ['id' => $metaAdId]);

        return (int) ($row['id'] ?? 0);
    }

    public function buscarAnuncioPorMetaId(string $metaAdId): ?int
    {
        $row = $this->db->selectOne('SELECT id FROM anuncios WHERE meta_ad_id = :id', ['id' => $metaAdId]);

        return $row !== null ? (int) $row['id'] : null;
    }

    public function buscarCampaniaPorMetaId(string $metaCampaignId): ?int
    {
        $row = $this->db->selectOne('SELECT id FROM campanias WHERE meta_campaign_id = :id', ['id' => $metaCampaignId]);

        return $row !== null ? (int) $row['id'] : null;
    }
}
