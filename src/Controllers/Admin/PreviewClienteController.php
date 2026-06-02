<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Services\DashboardPreferenciasService;
use MisterCo\Reports\Services\DashboardService;
use MisterCo\Reports\Services\PermisosService;

/**
 * Preview del dashboard de un cliente desde la sesión del admin.
 *
 * Renderiza exactamente lo que ve el cliente (mismo DashboardService, mismas
 * reglas de permisos), pero con header admin y banner indicando que es preview.
 * No toca sesión, no impersona — el admin sigue siendo admin.
 *
 * Solo lectura: no se exponen acciones del cliente (cambiar preferencias,
 * exportar PDF firmado como el cliente, etc).
 */
final class PreviewClienteController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function dashboard(Request $request): Response
    {
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $cliente = $this->container->get(ClienteRepository::class)->buscarPorId($clienteId);
        if ($cliente === null) {
            return Response::html('<h1>404 — Cliente no encontrado.</h1>', 404);
        }

        $service = $this->container->get(DashboardService::class);
        $view = $this->container->get(View::class);
        $prefs = $this->container->get(DashboardPreferenciasService::class)->obtener($clienteId);

        $campaniasAsignadas = $service->campaniasDelCliente($clienteId);

        [$desde, $hasta, $preset] = $this->resolverRango(
            (string) $request->input('preset', $prefs['rango_default']),
            (string) $request->input('desde', ''),
            (string) $request->input('hasta', ''),
        );

        $totales = $campaniasAsignadas === [] ? [] : $service->totalesGlobales($clienteId, $desde, $hasta);
        $campanias = $campaniasAsignadas === [] ? [] : $service->porCampania($clienteId, $desde, $hasta);
        $evolucion = $campaniasAsignadas === [] ? [] : $service->evolucionDiaria($clienteId, $desde, $hasta);

        $permisos = $this->container->get(PermisosService::class);
        $deshabilitadas = $permisos->metricasDeshabilitadas($clienteId);
        $widgetsVisibles = array_values(array_filter(
            $prefs['widgets'],
            static fn (string $w): bool => !in_array($w, $deshabilitadas, true)
        ));

        $moneda = (string) ($campaniasAsignadas[0]['moneda'] ?? '');

        return Response::html($view->render('admin/preview_dashboard', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Vista previa · ' . $cliente['nombre_comercial'],
            'cliente' => $cliente,
            'campanias_asignadas_count' => count($campaniasAsignadas),
            'moneda' => $moneda,
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

    public function detalleCampania(Request $request): Response
    {
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $campaniaId = (int) ($request->attributes['cid'] ?? 0);

        $repoCliente = $this->container->get(ClienteRepository::class);
        $cliente = $repoCliente->buscarPorId($clienteId);
        if ($cliente === null) {
            return Response::html('<h1>404 — Cliente no encontrado.</h1>', 404);
        }

        $entidades = $this->container->get(EntidadesMetaRepository::class);
        $cam = $entidades->buscarCampania($campaniaId);
        if ($cam === null) {
            return Response::html('<h1>404 — Campaña no encontrada.</h1>', 404);
        }

        $service = $this->container->get(DashboardService::class);
        if (!$service->clienteTieneAccesoACampania($clienteId, $campaniaId)) {
            return Response::html('<h1>Esta campaña no está asignada a este cliente.</h1>', 404);
        }

        [$desde, $hasta, $preset] = $this->resolverRango(
            (string) $request->input('preset', 'ultimos_30_dias'),
        );

        $totales = $service->totalesCampania($clienteId, $campaniaId, $desde, $hasta);
        $anuncios = $service->anunciosDeCampaniaConMetricas($clienteId, $campaniaId, $desde, $hasta);

        $view = $this->container->get(View::class);

        return Response::html($view->render('admin/preview_campania', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Vista previa · ' . $cam['nombre'],
            'cliente' => $cliente,
            'campania' => $cam,
            'totales' => $totales,
            'anuncios' => $anuncios,
            'desde' => $desde,
            'hasta' => $hasta,
            'preset' => $preset,
        ]));
    }

    /** @return array{0:string,1:string,2:string} */
    private function resolverRango(string $preset, string $desdeInput = '', string $hastaInput = ''): array
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

        if ($preset === 'personalizado'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desdeInput)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hastaInput)
        ) {
            return [$desdeInput, $hastaInput, 'personalizado'];
        }

        $r = $presets[$preset] ?? $presets['ultimos_30_dias'];

        return [$r[0], $r[1], array_key_exists($preset, $presets) ? $preset : 'ultimos_30_dias'];
    }
}
