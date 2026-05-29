<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Core\Database;

/**
 * Preferencias del dashboard por cliente (widgets, orden, rango por defecto).
 * Persiste en `configuraciones_dashboard_cliente.widgets` (JSON).
 */
final class DashboardPreferenciasService
{
    /** Widgets disponibles por código + etiqueta (orden = orden por defecto). */
    public const WIDGETS_DISPONIBLES = [
        'gasto' => 'Gasto',
        'impresiones' => 'Impresiones',
        'clicks' => 'Clicks',
        'ctr' => 'CTR',
        'cpc' => 'CPC promedio',
        'cpm' => 'CPM promedio',
        'alcance' => 'Alcance',
    ];

    public const PRESETS_RANGO = [
        'hoy' => 'Hoy',
        'ayer' => 'Ayer',
        'ultimos_7_dias' => 'Últimos 7 días',
        'ultimos_30_dias' => 'Últimos 30 días',
        'mes_actual' => 'Mes actual',
        'mes_pasado' => 'Mes pasado',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Devuelve la configuración actual o default si no existe.
     *
     * @return array{widgets: list<string>, rango_default: string}
     */
    public function obtener(int $clienteId): array
    {
        $row = $this->db->selectOne(
            'SELECT widgets, rango_default FROM configuraciones_dashboard_cliente WHERE cliente_id = :c',
            ['c' => $clienteId]
        );

        if ($row === null) {
            return [
                'widgets' => array_keys(self::WIDGETS_DISPONIBLES),
                'rango_default' => 'ultimos_30_dias',
            ];
        }

        $widgets = json_decode((string) $row['widgets'], true);
        if (!is_array($widgets)) {
            $widgets = array_keys(self::WIDGETS_DISPONIBLES);
        }

        return [
            'widgets' => array_values(array_intersect($widgets, array_keys(self::WIDGETS_DISPONIBLES))),
            'rango_default' => (string) $row['rango_default'],
        ];
    }

    /**
     * Guarda configuración. Solo persiste widgets que existen en el catálogo.
     *
     * @param list<string> $widgets ordenados según preferencia del cliente
     */
    public function guardar(int $clienteId, array $widgets, string $rangoDefault): void
    {
        $widgetsValidos = array_values(array_unique(array_intersect($widgets, array_keys(self::WIDGETS_DISPONIBLES))));
        if (!array_key_exists($rangoDefault, self::PRESETS_RANGO)) {
            $rangoDefault = 'ultimos_30_dias';
        }

        $this->db->execute(
            'INSERT INTO configuraciones_dashboard_cliente (cliente_id, widgets, rango_default)
                  VALUES (:c, :w, :r)
                  ON DUPLICATE KEY UPDATE widgets = VALUES(widgets),
                                          rango_default = VALUES(rango_default)',
            ['c' => $clienteId, 'w' => json_encode($widgetsValidos), 'r' => $rangoDefault]
        );
    }
}
