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

        $mesesDisponibles = $service->mesesConDatosDelCliente($clienteId);
        $mesSeleccionado = $this->resolverMes((string) $request->input('mes', ''), $mesesDisponibles);
        if ($mesSeleccionado === null) {
            $desde = date('Y-m-01');
            $hasta = date('Y-m-t');
        } else {
            $ts = strtotime($mesSeleccionado . '-01');
            $desde = date('Y-m-01', $ts);
            $hasta = date('Y-m-t', $ts);
        }

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
            'mes_seleccionado' => $mesSeleccionado,
            'meses_disponibles' => $mesesDisponibles,
            'totales' => $totales,
            'campanias' => $campanias,
            'evolucion' => $evolucion,
            'widgets_visibles' => $widgetsVisibles,
            'widgets_disponibles' => DashboardPreferenciasService::WIDGETS_DISPONIBLES,
            'metricas_deshabilitadas' => $deshabilitadas,
        ]));
    }

    /** @param list<string> $disponibles */
    private function resolverMes(string $mes, array $disponibles): ?string
    {
        if ($disponibles === []) {
            return null;
        }
        if ($mes !== '' && in_array($mes, $disponibles, true)) {
            return $mes;
        }
        return $disponibles[0];
    }
}
