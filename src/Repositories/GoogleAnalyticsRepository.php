<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

/**
 * Propiedades GA4, métricas diarias (totales y por canal), asignación de
 * propiedades a clientes y consultas agregadas del dashboard/PDF.
 */
final class GoogleAnalyticsRepository
{
    private const SUMAS = 'COALESCE(SUM(m.sesiones), 0) AS sesiones,
                    COALESCE(SUM(m.usuarios), 0) AS usuarios,
                    COALESCE(SUM(m.usuarios_nuevos), 0) AS usuarios_nuevos,
                    COALESCE(SUM(m.vistas), 0) AS vistas,
                    COALESCE(SUM(m.sesiones_con_interaccion), 0) AS sesiones_con_interaccion,
                    COALESCE(SUM(m.duracion_interaccion_seg), 0) AS duracion_interaccion_seg,
                    COALESCE(SUM(m.eventos_clave), 0) AS eventos_clave,
                    COALESCE(SUM(m.ingresos), 0) AS ingresos';

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listarPropiedades(): array
    {
        return $this->db->select(
            'SELECT id, property_id, nombre, cuenta_nombre, moneda, zona_horaria, ultima_sincronizacion_en
               FROM ga4_propiedades ORDER BY cuenta_nombre, nombre'
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPropiedad(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT id, property_id, nombre, cuenta_nombre, moneda, zona_horaria FROM ga4_propiedades WHERE id = :id',
            ['id' => $id]
        );
    }

    public function upsertPropiedad(string $propertyId, string $nombre, ?string $cuentaNombre, ?string $moneda, ?string $zonaHoraria): void
    {
        $this->db->execute(
            'INSERT INTO ga4_propiedades (property_id, nombre, cuenta_nombre, moneda, zona_horaria)
                  VALUES (:p, :n, :c, :m, :tz)
                  ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), cuenta_nombre = VALUES(cuenta_nombre),
                                          moneda = COALESCE(VALUES(moneda), moneda),
                                          zona_horaria = COALESCE(VALUES(zona_horaria), zona_horaria)',
            ['p' => $propertyId, 'n' => $nombre, 'c' => $cuentaNombre, 'm' => $moneda, 'tz' => $zonaHoraria]
        );
    }

    public function marcarSincronizada(int $propiedadId): void
    {
        $this->db->execute('UPDATE ga4_propiedades SET ultima_sincronizacion_en = NOW() WHERE id = :id', ['id' => $propiedadId]);
    }

    /**
     * Reemplaza las métricas de la propiedad en el rango (totales diarios y por canal).
     *
     * @param list<array<string, int|float|string>> $diarias
     * @param list<array<string, int|float|string>> $canales
     */
    public function reemplazarMetricas(int $propiedadId, string $desde, string $hasta, array $diarias, array $canales): void
    {
        $rango = ['p' => $propiedadId, 'd' => $desde, 'h' => $hasta];
        $this->db->beginTransaction();
        try {
            $this->db->execute('DELETE FROM ga4_metricas_diarias WHERE propiedad_id = :p AND fecha BETWEEN :d AND :h', $rango);
            $this->db->execute('DELETE FROM ga4_metricas_canal WHERE propiedad_id = :p AND fecha BETWEEN :d AND :h', $rango);
            foreach ($diarias as $f) {
                $this->db->execute(
                    'INSERT INTO ga4_metricas_diarias
                        (propiedad_id, fecha, sesiones, usuarios, usuarios_nuevos, vistas,
                         sesiones_con_interaccion, duracion_interaccion_seg, eventos_clave, ingresos)
                     VALUES (:p, :fecha, :sesiones, :usuarios, :usuarios_nuevos, :vistas,
                             :sesiones_con_interaccion, :duracion_interaccion_seg, :eventos_clave, :ingresos)',
                    ['p' => $propiedadId] + $f
                );
            }
            foreach ($canales as $f) {
                $this->db->execute(
                    'INSERT INTO ga4_metricas_canal (propiedad_id, fecha, canal, sesiones, usuarios, eventos_clave, ingresos)
                     VALUES (:p, :fecha, :canal, :sesiones, :usuarios, :eventos_clave, :ingresos)',
                    ['p' => $propiedadId] + $f
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return list<int> */
    public function idsAsignadas(int $clienteId): array
    {
        $rows = $this->db->select(
            'SELECT propiedad_id FROM permisos_cliente_ga4_propiedad WHERE cliente_id = :c',
            ['c' => $clienteId]
        );

        return array_map(static fn ($r) => (int) $r['propiedad_id'], $rows);
    }

    /** @param list<int> $propiedadesIds */
    public function reemplazarAsignadas(int $clienteId, array $propiedadesIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->execute('DELETE FROM permisos_cliente_ga4_propiedad WHERE cliente_id = :c', ['c' => $clienteId]);
            foreach ($propiedadesIds as $id) {
                $this->db->execute(
                    'INSERT INTO permisos_cliente_ga4_propiedad (cliente_id, propiedad_id) VALUES (:c, :p)',
                    ['c' => $clienteId, 'p' => $id]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function propiedadesDelCliente(int $clienteId): array
    {
        return $this->db->select(
            'SELECT gp.id, gp.property_id, gp.nombre, gp.cuenta_nombre, gp.moneda
               FROM permisos_cliente_ga4_propiedad p
               JOIN ga4_propiedades gp ON gp.id = p.propiedad_id
              WHERE p.cliente_id = :c
           ORDER BY gp.nombre',
            ['c' => $clienteId]
        );
    }

    /** @return list<string> YYYY-MM con datos, más reciente primero. */
    public function mesesConDatos(int $clienteId): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT DATE_FORMAT(m.fecha, '%Y-%m') AS mes
               FROM ga4_metricas_diarias m
               JOIN permisos_cliente_ga4_propiedad p ON p.propiedad_id = m.propiedad_id
              WHERE p.cliente_id = :c
           ORDER BY mes DESC",
            ['c' => $clienteId]
        );

        return array_map(static fn ($r) => (string) $r['mes'], $rows);
    }

    /** @return array<string, mixed> */
    public function totales(int $clienteId, string $desde, string $hasta): array
    {
        $row = $this->db->selectOne(
            'SELECT ' . self::SUMAS . '
               FROM ga4_metricas_diarias m
               JOIN permisos_cliente_ga4_propiedad p ON p.propiedad_id = m.propiedad_id
              WHERE p.cliente_id = :c AND m.fecha BETWEEN :d AND :h',
            ['c' => $clienteId, 'd' => $desde, 'h' => $hasta]
        ) ?? [];

        return self::conDerivadas($row);
    }

    /** @return list<array<string, mixed>> */
    public function porPropiedad(int $clienteId, string $desde, string $hasta): array
    {
        $rows = $this->db->select(
            'SELECT gp.id AS propiedad_id, gp.nombre AS propiedad, ' . self::SUMAS . '
               FROM ga4_metricas_diarias m
               JOIN permisos_cliente_ga4_propiedad p ON p.propiedad_id = m.propiedad_id
               JOIN ga4_propiedades gp ON gp.id = m.propiedad_id
              WHERE p.cliente_id = :c AND m.fecha BETWEEN :d AND :h
           GROUP BY gp.id, gp.nombre
           ORDER BY sesiones DESC',
            ['c' => $clienteId, 'd' => $desde, 'h' => $hasta]
        );

        return array_map(self::conDerivadas(...), $rows);
    }

    /** @return list<array<string, mixed>> Sesiones, usuarios, eventos clave e ingresos por canal. */
    public function porCanal(int $clienteId, string $desde, string $hasta): array
    {
        return $this->db->select(
            'SELECT m.canal, SUM(m.sesiones) AS sesiones, SUM(m.usuarios) AS usuarios,
                    SUM(m.eventos_clave) AS eventos_clave, SUM(m.ingresos) AS ingresos
               FROM ga4_metricas_canal m
               JOIN permisos_cliente_ga4_propiedad p ON p.propiedad_id = m.propiedad_id
              WHERE p.cliente_id = :c AND m.fecha BETWEEN :d AND :h
           GROUP BY m.canal
           ORDER BY sesiones DESC',
            ['c' => $clienteId, 'd' => $desde, 'h' => $hasta]
        );
    }

    /** @return list<array<string, mixed>> */
    public function evolucionDiaria(int $clienteId, string $desde, string $hasta): array
    {
        return $this->db->select(
            'SELECT m.fecha, SUM(m.sesiones) AS sesiones, SUM(m.usuarios) AS usuarios,
                    SUM(m.vistas) AS vistas, SUM(m.eventos_clave) AS eventos_clave
               FROM ga4_metricas_diarias m
               JOIN permisos_cliente_ga4_propiedad p ON p.propiedad_id = m.propiedad_id
              WHERE p.cliente_id = :c AND m.fecha BETWEEN :d AND :h
           GROUP BY m.fecha
           ORDER BY m.fecha',
            ['c' => $clienteId, 'd' => $desde, 'h' => $hasta]
        );
    }

    /**
     * @param array<string, mixed> $f
     * @return array<string, mixed>
     */
    private static function conDerivadas(array $f): array
    {
        $sesiones = (int) ($f['sesiones'] ?? 0);

        return array_merge($f, [
            'sesiones' => $sesiones,
            'usuarios' => (int) ($f['usuarios'] ?? 0),
            'usuarios_nuevos' => (int) ($f['usuarios_nuevos'] ?? 0),
            'vistas' => (int) ($f['vistas'] ?? 0),
            'eventos_clave' => (float) ($f['eventos_clave'] ?? 0),
            'ingresos' => (float) ($f['ingresos'] ?? 0),
            'tasa_interaccion' => $sesiones > 0 ? (int) ($f['sesiones_con_interaccion'] ?? 0) / $sesiones * 100 : null,
            'duracion_media_seg' => $sesiones > 0 ? (int) ($f['duracion_interaccion_seg'] ?? 0) / $sesiones : null,
            'vistas_por_sesion' => $sesiones > 0 ? (int) ($f['vistas'] ?? 0) / $sesiones : null,
        ]);
    }
}
