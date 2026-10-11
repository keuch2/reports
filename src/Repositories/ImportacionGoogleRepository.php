<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

final class ImportacionGoogleRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param 'google_ads'|'ga4' $fuente */
    public function crear(string $fuente, int $referenciaId, int $usuarioId, string $rangoInicio, string $rangoFin): int
    {
        $this->db->execute(
            'INSERT INTO importaciones_google (fuente, referencia_id, usuario_id, rango_inicio, rango_fin)
                  VALUES (:f, :r, :u, :ri, :rf)',
            ['f' => $fuente, 'r' => $referenciaId, 'u' => $usuarioId, 'ri' => $rangoInicio, 'rf' => $rangoFin]
        );

        return $this->db->lastInsertId();
    }

    public function marcarCompletada(int $id, int $entidades, int $filas, ?string $aviso = null): void
    {
        $this->db->execute(
            'UPDATE importaciones_google
                SET estado = \'completada\', finalizado_en = NOW(),
                    entidades_afectadas = :e, filas_afectadas = :f, error_mensaje = :a
              WHERE id = :id',
            ['e' => $entidades, 'f' => $filas, 'a' => $aviso, 'id' => $id]
        );
    }

    public function marcarFallida(int $id, string $error): void
    {
        $this->db->execute(
            'UPDATE importaciones_google SET estado = \'fallida\', finalizado_en = NOW(), error_mensaje = :e WHERE id = :id',
            ['e' => mb_substr($error, 0, 5000), 'id' => $id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function recientes(int $limit = 15): array
    {
        return $this->db->select(
            'SELECT i.id, i.fuente, i.rango_inicio, i.rango_fin, i.estado, i.iniciado_en,
                    i.entidades_afectadas, i.filas_afectadas, i.error_mensaje,
                    COALESCE(gc.nombre, gp.nombre) AS referencia_nombre
               FROM importaciones_google i
          LEFT JOIN google_ads_cuentas gc ON i.fuente = \'google_ads\' AND gc.id = i.referencia_id
          LEFT JOIN ga4_propiedades gp ON i.fuente = \'ga4\' AND gp.id = i.referencia_id
           ORDER BY i.iniciado_en DESC, i.id DESC
              LIMIT ' . (int) $limit
        );
    }
}
