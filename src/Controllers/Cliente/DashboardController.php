<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Cliente;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Services\DashboardPreferenciasService;
use MisterCo\Reports\Services\DashboardService;
use MisterCo\Reports\Services\PermisosService;

/**
 * Dashboard cliente.
 *
 * Modelo nuevo: el cliente ve un único dashboard agregado sobre TODAS las
 * campañas que tiene asignadas (de cualquier cuenta). KPIs y gráficos suman
 * todas esas campañas; la tabla las lista una por una indicando su cuenta.
 */
final class DashboardController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function index(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) $usuario->clienteId;

        $service = $this->container->get(DashboardService::class);
        $view = $this->container->get(View::class);
        $prefs = $this->container->get(DashboardPreferenciasService::class)->obtener($clienteId);

        $campaniasAsignadas = $service->campaniasDelCliente($clienteId);
        if ($campaniasAsignadas === []) {
            return Response::html($view->render('cliente/sin_datos', [
                'usuario' => $usuario,
                'titulo' => 'Mi dashboard',
            ]));
        }

        [$desde, $hasta, $preset] = $this->resolverRango(
            (string) $request->input('preset', $prefs['rango_default']),
            (string) $request->input('desde', ''),
            (string) $request->input('hasta', ''),
        );

        $totales = $service->totalesGlobales($clienteId, $desde, $hasta);
        $campanias = $service->porCampania($clienteId, $desde, $hasta);
        $evolucion = $service->evolucionDiaria($clienteId, $desde, $hasta);

        $permisos = $this->container->get(PermisosService::class);
        $deshabilitadas = $permisos->metricasDeshabilitadas($clienteId);
        $widgetsVisibles = array_values(array_filter(
            $prefs['widgets'],
            static fn (string $w): bool => !in_array($w, $deshabilitadas, true)
        ));

        // Moneda predominante (asumimos que las campañas del cliente comparten moneda;
        // si no, mostramos la primera y dejamos al admin cuidar la consistencia).
        $monedaPredominante = (string) ($campaniasAsignadas[0]['moneda'] ?? '');

        return Response::html($view->render('cliente/dashboard_meta', [
            'usuario' => $usuario,
            'titulo' => 'Mi dashboard',
            'campanias_asignadas_count' => count($campaniasAsignadas),
            'moneda' => $monedaPredominante,
            'desde' => $desde,
            'hasta' => $hasta,
            'preset' => $preset,
            'totales' => $totales,
            'campanias' => $campanias,
            'evolucion' => $evolucion,
            'widgets_visibles' => $widgetsVisibles,
            'widgets_disponibles' => DashboardPreferenciasService::WIDGETS_DISPONIBLES,
            'metricas_deshabilitadas' => $deshabilitadas,
        ]));
    }

    /** @return array{0:string,1:string,2:string} [desde, hasta, preset] */
    private function resolverRango(string $preset, string $desdeInput, string $hastaInput): array
    {
        $hoy = date('Y-m-d');
        $presets = [
            'hoy' => [$hoy, $hoy],
            'ayer' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
            'ultimos_7_dias' => [date('Y-m-d', strtotime('-7 days')), $hoy],
            'ultimos_30_dias' => [date('Y-m-d', strtotime('-30 days')), $hoy],
            'mes_actual' => [date('Y-m-01'), $hoy],
            'mes_pasado' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
        ];

        if ($preset === 'personalizado' && $this->fechaValida($desdeInput) && $this->fechaValida($hastaInput)) {
            return [$desdeInput, $hastaInput, 'personalizado'];
        }

        $r = $presets[$preset] ?? $presets['ultimos_30_dias'];

        return [$r[0], $r[1], array_key_exists($preset, $presets) ? $preset : 'ultimos_30_dias'];
    }

    private function fechaValida(string $f): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f) !== false;
    }
}
