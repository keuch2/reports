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
        ?string $cuerpo = null,
        ?string $titulo = null,
        ?string $linkUrl = null,
        ?string $imageUrl = null,
        ?string $callToAction = null,
        ?string $permalinkUrl = null,
    ): int {
        $this->db->execute(
            'INSERT INTO anuncios
                (meta_ad_id, conjunto_anuncios_id, nombre, creative_id, tipo, thumbnail_url, estado,
                 cuerpo, titulo, link_url, image_url, call_to_action, permalink_url)
              VALUES (:mid, :sid, :n, :cre, :t, :th, :est, :cu, :ti, :lu, :iu, :cta, :pu)
              ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre),
                creative_id = VALUES(creative_id),
                tipo = VALUES(tipo),
                thumbnail_url = VALUES(thumbnail_url),
                estado = VALUES(estado),
                cuerpo = VALUES(cuerpo),
                titulo = VALUES(titulo),
                link_url = VALUES(link_url),
                image_url = VALUES(image_url),
                call_to_action = VALUES(call_to_action),
                permalink_url = VALUES(permalink_url)',
            [
                'mid' => $metaAdId, 'sid' => $conjuntoAnunciosId, 'n' => $nombre,
                'cre' => $creativeId, 't' => $tipo, 'th' => $thumbnailUrl, 'est' => $estado,
                'cu' => $cuerpo, 'ti' => $titulo, 'lu' => $linkUrl, 'iu' => $imageUrl,
                'cta' => $callToAction, 'pu' => $permalinkUrl,
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

    /** @return list<array<string,mixed>> Todas las campañas de una cuenta (sin filtro de permisos) */
    public function campaniasDeCuenta(int $cuentaId): array
    {
        return $this->db->select(
            'SELECT id, meta_campaign_id, nombre, objetivo, estado, fecha_inicio, fecha_fin
               FROM campanias WHERE cuenta_publicitaria_id = :c ORDER BY nombre',
            ['c' => $cuentaId]
        );
    }

    /**
     * Campañas de una cuenta que tienen al menos un snapshot importado.
     * Sirve para la UI de asignación al cliente: no mostramos campañas huérfanas
     * que quedaron en BD por importaciones viejas borradas.
     *
     * @return list<array<string,mixed>>
     */
    public function campaniasConSnapshotsDeCuenta(int $cuentaId): array
    {
        return $this->db->select(
            'SELECT DISTINCT c.id, c.meta_campaign_id, c.nombre, c.objetivo, c.estado,
                    c.fecha_inicio, c.fecha_fin
               FROM campanias c
               JOIN conjuntos_anuncios cs ON cs.campania_id = c.id
               JOIN anuncios a ON a.conjunto_anuncios_id = cs.id
               JOIN metricas_snapshots ms ON ms.entidad_id = a.id AND ms.nivel = \'ad\'
              WHERE c.cuenta_publicitaria_id = :c
              ORDER BY c.nombre',
            ['c' => $cuentaId]
        );
    }

    /** @return list<array<string,mixed>> Todos los anuncios de una campaña con su adset */
    public function anunciosDeCampania(int $campaniaId): array
    {
        return $this->db->select(
            'SELECT a.id, a.meta_ad_id, a.nombre, a.tipo, a.thumbnail_url, a.image_url,
                    a.cuerpo, a.titulo, a.link_url, a.call_to_action, a.permalink_url, a.estado,
                    cs.nombre AS adset_nombre, cs.id AS adset_id
               FROM anuncios a
               JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              WHERE cs.campania_id = :c
           ORDER BY cs.nombre, a.nombre',
            ['c' => $campaniaId]
        );
    }

    /** @return list<array<string,mixed>> Adsets de una campaña */
    public function adsetsDeCampania(int $campaniaId): array
    {
        return $this->db->select(
            'SELECT id, meta_adset_id, nombre, estado, optimization_goal,
                    presupuesto_diario, presupuesto_total
               FROM conjuntos_anuncios
              WHERE campania_id = :c
           ORDER BY nombre',
            ['c' => $campaniaId]
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarAdset(int $adsetId): ?array
    {
        return $this->db->selectOne(
            'SELECT cs.id, cs.meta_adset_id, cs.nombre, cs.estado, cs.optimization_goal,
                    cs.presupuesto_diario, cs.presupuesto_total, cs.campania_id,
                    c.nombre AS campania_nombre, c.cuenta_publicitaria_id,
                    cp.nombre AS cuenta_nombre, cp.moneda
               FROM conjuntos_anuncios cs
               JOIN campanias c ON c.id = cs.campania_id
               JOIN cuentas_publicitarias cp ON cp.id = c.cuenta_publicitaria_id
              WHERE cs.id = :id',
            ['id' => $adsetId]
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarCampania(int $campaniaId): ?array
    {
        return $this->db->selectOne(
            'SELECT c.id, c.meta_campaign_id, c.nombre, c.objetivo, c.estado,
                    c.fecha_inicio, c.fecha_fin, c.presupuesto_diario, c.presupuesto_total,
                    c.cuenta_publicitaria_id, cp.nombre AS cuenta_nombre, cp.moneda
               FROM campanias c
               JOIN cuentas_publicitarias cp ON cp.id = c.cuenta_publicitaria_id
              WHERE c.id = :id',
            ['id' => $campaniaId]
        );
    }
}
