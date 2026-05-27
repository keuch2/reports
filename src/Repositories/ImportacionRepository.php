<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

final class ImportacionRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function crear(int $cuentaPublicitariaId, int $usuarioId, string $rangoInicio, string $rangoFin): int
    {
        $this->db->execute(
            'INSERT INTO importaciones_meta (cuenta_publicitaria_id, usuario_id, rango_inicio, rango_fin, estado)
                  VALUES (:cid, :uid, :ri, :rf, \'en_curso\')',
            ['cid' => $cuentaPublicitariaId, 'uid' => $usuarioId, 'ri' => $rangoInicio, 'rf' => $rangoFin]
        );

        return $this->db->lastInsertId();
    }

    public function marcarCompletada(
        int $id,
        int $campanias,
        int $adsets,
        int $anuncios,
        int $snapshots,
        int $llamadas,
    ): void {
        $this->db->execute(
            'UPDATE importaciones_meta
                SET estado = \'completada\',
                    finalizado_en = NOW(),
                    campanias_afectadas = :c, adsets_afectados = :a,
                    anuncios_afectados = :an, snapshots_afectados = :s,
                    llamadas_meta = :l
              WHERE id = :id',
            ['c' => $campanias, 'a' => $adsets, 'an' => $anuncios, 's' => $snapshots, 'l' => $llamadas, 'id' => $id]
        );
    }

    public function marcarFallida(int $id, string $error, int $llamadas = 0): void
    {
        $this->db->execute(
            'UPDATE importaciones_meta
                SET estado = \'fallida\', finalizado_en = NOW(),
                    error_mensaje = :e, llamadas_meta = :l
              WHERE id = :id',
            ['e' => mb_substr($error, 0, 5000), 'l' => $llamadas, 'id' => $id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function recientes(int $limit = 20): array
    {
        return $this->db->select(
            'SELECT i.id, i.rango_inicio, i.rango_fin, i.estado, i.iniciado_en, i.finalizado_en,
                    i.campanias_afectadas, i.adsets_afectados, i.anuncios_afectados, i.snapshots_afectados,
                    i.error_mensaje, cp.nombre AS cuenta_nombre
               FROM importaciones_meta i
               JOIN cuentas_publicitarias cp ON cp.id = i.cuenta_publicitaria_id
           ORDER BY i.iniciado_en DESC
              LIMIT ' . (int) $limit
        );
    }
}
