<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

/**
 * Cuentas, campañas y métricas diarias de Google Ads, más la asignación de
 * campañas a clientes y las consultas agregadas del dashboard/PDF.
 */
final class GoogleAdsRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listarCuentas(): array
    {
        return $this->db->select(
            'SELECT id, customer_id, nombre, moneda, zona_horaria, login_customer_id, ultima_sincronizacion_en
               FROM google_ads_cuentas ORDER BY nombre'
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarCuenta(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT id, customer_id, nombre, moneda, zona_horaria, login_customer_id
               FROM google_ads_cuentas WHERE id = :id',
            ['id' => $id]
        );
    }

    public function upsertCuenta(string $customerId, string $nombre, ?string $moneda, ?string $zonaHoraria, ?string $loginCustomerId): void
    {
        $this->db->execute(
            'INSERT INTO google_ads_cuentas (customer_id, nombre, moneda, zona_horaria, login_customer_id)
                  VALUES (:cid, :n, :m, :tz, :login)
                  ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), moneda = VALUES(moneda),
                                          zona_horaria = VALUES(zona_horaria),
                                          login_customer_id = VALUES(login_customer_id)',
            ['cid' => $customerId, 'n' => $nombre, 'm' => $moneda, 'tz' => $zonaHoraria, 'login' => $loginCustomerId]
        );
    }

    public function marcarSincronizada(int $cuentaId): void
    {
        $this->db->execute('UPDATE google_ads_cuentas SET ultima_sincronizacion_en = NOW() WHERE id = :id', ['id' => $cuentaId]);
    }

    public function upsertCampania(int $cuentaId, string $googleCampaignId, string $nombre, ?string $estado, ?string $tipoCanal, ?float $presupuestoDiario): int
    {
        $this->db->execute(
            'INSERT INTO google_ads_campanias (cuenta_id, google_campaign_id, nombre, estado, tipo_canal, presupuesto_diario)
                  VALUES (:cu, :g, :n, :e, :t, :p)
                  ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), estado = VALUES(estado),
                                          tipo_canal = VALUES(tipo_canal),
                                          presupuesto_diario = COALESCE(VALUES(presupuesto_diario), presupuesto_diario)',
            ['cu' => $cuentaId, 'g' => $googleCampaignId, 'n' => $nombre, 'e' => $estado, 't' => $tipoCanal, 'p' => $presupuestoDiario]
        );
        $row = $this->db->selectOne(
            'SELECT id FROM google_ads_campanias WHERE cuenta_id = :cu AND google_campaign_id = :g',
            ['cu' => $cuentaId, 'g' => $googleCampaignId]
        );

        return (int) ($row['id'] ?? 0);
    }

    /**
     * Reemplaza las métricas de la cuenta en el rango: Google no devuelve los días
     * sin actividad, así que borrar antes evita que queden valores viejos.
     *
     * @param list<array{campania_id:int, fecha:string, gasto:float, impresiones:int, clicks:int, conversiones:float, valor_conversiones:float}> $filas
     */
    public function reemplazarMetricas(int $cuentaId, string $desde, string $hasta, array $filas): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'DELETE m FROM google_ads_metricas_diarias m
                   JOIN google_ads_campanias c ON c.id = m.campania_id
                  WHERE c.cuenta_id = :cu AND m.fecha BETWEEN :d AND :h',
                ['cu' => $cuentaId, 'd' => $desde, 'h' => $hasta]
            );
            foreach ($filas as $f) {
                $this->db->execute(
                    'INSERT INTO google_ads_metricas_diarias
                        (campania_id, fecha, gasto, impresiones, clicks, conversiones, valor_conversiones)
                     VALUES (:c, :f, :g, :i, :cl, :co, :v)',
                    [
                        'c' => $f['campania_id'], 'f' => $f['fecha'], 'g' => $f['gasto'], 'i' => $f['impresiones'],
                        'cl' => $f['clicks'], 'co' => $f['conversiones'], 'v' => $f['valor_conversiones'],
                    ]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> Campañas de una cuenta (para asignar a clientes). */
    public function campaniasDeCuenta(int $cuentaId): array
    {
        return $this->db->select(
            'SELECT id, google_campaign_id, nombre, estado, tipo_canal
               FROM google_ads_campanias WHERE cuenta_id = :cu ORDER BY nombre',
            ['cu' => $cuentaId]
        );
    }

    /** @return list<int> */
    public function idsAsignadas(int $clienteId): array
    {
        $rows = $this->db->select(
            'SELECT campania_id FROM permisos_cliente_google_ads_campania WHERE cliente_id = :c',
            ['c' => $clienteId]
        );

        return array_map(static fn ($r) => (int) $r['campania_id'], $rows);
    }

    /** @param list<int> $campaniasIds */
    public function reemplazarAsignadas(int $clienteId, array $campaniasIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->execute('DELETE FROM permisos_cliente_google_ads_campania WHERE cliente_id = :c', ['c' => $clienteId]);
            foreach ($campaniasIds as $id) {
                $this->db->execute(
                    'INSERT INTO permisos_cliente_google_ads_campania (cliente_id, campania_id) VALUES (:c, :ca)',
                    ['c' => $clienteId, 'ca' => $id]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function campaniasDelCliente(int $clienteId): array
    {
        return $this->db->select(
            'SELECT c.id, c.nombre AS campania, c.estado, c.tipo_canal,
                    cu.id AS cuenta_id, cu.nombre AS cuenta_nombre, cu.moneda
               FROM permisos_cliente_google_ads_campania p
               JOIN google_ads_campanias c ON c.id = p.campania_id
               JOIN google_ads_cuentas cu ON cu.id = c.cuenta_id
              WHERE p.cliente_id = :c
           ORDER BY cu.nombre, c.nombre',
            ['c' => $clienteId]
        );
    }

    /** @return list<string> YYYY-MM con datos, más reciente primero. */
    public function mesesConDatos(int $clienteId): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT DATE_FORMAT(m.fecha, '%Y-%m') AS mes
               FROM google_ads_metricas_diarias m
               JOIN permisos_cliente_google_ads_campania p ON p.campania_id = m.campania_id
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
            'SELECT COALESCE(SUM(m.gasto), 0) AS gasto,
                    COALESCE(SUM(m.impresiones), 0) AS impresiones,
                    COALESCE(SUM(m.clicks), 0) AS clicks,
                    COALESCE(SUM(m.conversiones), 0) AS conversiones,
                    COALESCE(SUM(m.valor_conversiones), 0) AS valor_conversiones
               FROM google_ads_metricas_diarias m
               JOIN permisos_cliente_google_ads_campania p ON p.campania_id = m.campania_id
              WHERE p.cliente_id = :c AND m.fecha BETWEEN :d AND :h',
            ['c' => $clienteId, 'd' => $desde, 'h' => $hasta]
        ) ?? [];

        return self::conDerivadas($row);
    }

    /** @return list<array<string, mixed>> Métricas por campaña con actividad en el rango. */
    public function porCampania(int $clienteId, string $desde, string $hasta): array
    {
        $rows = $this->db->select(
            'SELECT c.id AS campania_id, c.nombre AS campania, c.estado, c.tipo_canal,
                    cu.nombre AS cuenta_nombre, cu.moneda,
                    SUM(m.gasto) AS gasto, SUM(m.impresiones) AS impresiones, SUM(m.clicks) AS clicks,
                    SUM(m.conversiones) AS conversiones, SUM(m.valor_conversiones) AS valor_conversiones
               FROM google_ads_metricas_diarias m
               JOIN permisos_cliente_google_ads_campania p ON p.campania_id = m.campania_id
               JOIN google_ads_campanias c ON c.id = m.campania_id
               JOIN google_ads_cuentas cu ON cu.id = c.cuenta_id
              WHERE p.cliente_id = :c AND m.fecha BETWEEN :d AND :h
           GROUP BY c.id, c.nombre, c.estado, c.tipo_canal, cu.nombre, cu.moneda
           ORDER BY gasto DESC',
            ['c' => $clienteId, 'd' => $desde, 'h' => $hasta]
        );

        return array_map(self::conDerivadas(...), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function evolucionDiaria(int $clienteId, string $desde, string $hasta): array
    {
        return $this->db->select(
            'SELECT m.fecha, SUM(m.gasto) AS gasto, SUM(m.impresiones) AS impresiones,
                    SUM(m.clicks) AS clicks, SUM(m.conversiones) AS conversiones
               FROM google_ads_metricas_diarias m
               JOIN permisos_cliente_google_ads_campania p ON p.campania_id = m.campania_id
              WHERE p.cliente_id = :c AND m.fecha BETWEEN :d AND :h
           GROUP BY m.fecha
           ORDER BY m.fecha',
            ['c' => $clienteId, 'd' => $desde, 'h' => $hasta]
        );
    }

    /** @return array<string, mixed> Totales de una campaña asignada al cliente. */
    public function totalesCampania(int $clienteId, int $campaniaId, string $desde, string $hasta): array
    {
        $row = $this->db->selectOne(
            'SELECT COALESCE(SUM(m.gasto), 0) AS gasto,
                    COALESCE(SUM(m.impresiones), 0) AS impresiones,
                    COALESCE(SUM(m.clicks), 0) AS clicks,
                    COALESCE(SUM(m.conversiones), 0) AS conversiones,
                    COALESCE(SUM(m.valor_conversiones), 0) AS valor_conversiones
               FROM google_ads_metricas_diarias m
               JOIN permisos_cliente_google_ads_campania p ON p.campania_id = m.campania_id
              WHERE p.cliente_id = :c AND m.campania_id = :ca AND m.fecha BETWEEN :d AND :h',
            ['c' => $clienteId, 'ca' => $campaniaId, 'd' => $desde, 'h' => $hasta]
        ) ?? [];

        return self::conDerivadas($row);
    }

    /** @return list<array<string, mixed>> Serie diaria de una campaña asignada al cliente. */
    public function evolucionCampania(int $clienteId, int $campaniaId, string $desde, string $hasta): array
    {
        return $this->db->select(
            'SELECT m.fecha, m.gasto, m.conversiones
               FROM google_ads_metricas_diarias m
               JOIN permisos_cliente_google_ads_campania p ON p.campania_id = m.campania_id
              WHERE p.cliente_id = :c AND m.campania_id = :ca AND m.fecha BETWEEN :d AND :h
           ORDER BY m.fecha',
            ['c' => $clienteId, 'ca' => $campaniaId, 'd' => $desde, 'h' => $hasta]
        );
    }

    /**
     * CTR, CPC, CPM y costo por conversión calculados sobre los totales (no se
     * promedian promedios diarios).
     *
     * @param array<string, mixed> $f
     * @return array<string, mixed>
     */
    private static function conDerivadas(array $f): array
    {
        $gasto = (float) ($f['gasto'] ?? 0);
        $impresiones = (int) ($f['impresiones'] ?? 0);
        $clicks = (int) ($f['clicks'] ?? 0);
        $conversiones = (float) ($f['conversiones'] ?? 0);

        return array_merge($f, [
            'gasto' => $gasto,
            'impresiones' => $impresiones,
            'clicks' => $clicks,
            'conversiones' => $conversiones,
            'valor_conversiones' => (float) ($f['valor_conversiones'] ?? 0),
            'ctr' => $impresiones > 0 ? $clicks / $impresiones * 100 : null,
            'cpc' => $clicks > 0 ? $gasto / $clicks : null,
            'cpm' => $impresiones > 0 ? $gasto / $impresiones * 1000 : null,
            'costo_por_conversion' => $conversiones > 0 ? $gasto / $conversiones : null,
        ]);
    }
}
