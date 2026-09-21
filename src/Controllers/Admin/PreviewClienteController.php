<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\PeriodoReporte;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Services\AnalisisCampaniaService;
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
 * Solo lectura sobre los datos del cliente (no cambia sus preferencias). El
 * admin tiene los mismos filtros de período y exportación a PDF que el cliente;
 * los PDF se generan en ReporteClienteController a nombre del admin.
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

        $mesesDisponibles = $service->mesesConDatosDelCliente($clienteId);
        $periodo = PeriodoReporte::desdeRequest($request, $mesesDisponibles);
        $desde = $periodo->desde;
        $hasta = $periodo->hasta;

        $totales = $campaniasAsignadas === [] ? [] : $service->totalesGlobales($clienteId, $desde, $hasta);
        $campanias = $campaniasAsignadas === [] ? [] : $service->porCampania($clienteId, $desde, $hasta);
        $evolucion = $campaniasAsignadas === [] ? [] : $service->evolucionDiaria($clienteId, $desde, $hasta);
        $resultadosPorTipo = $campaniasAsignadas === [] ? [] : $service->resultadosPorTipoGlobal($clienteId, $desde, $hasta);

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
            'periodo' => $periodo,
            'meses_disponibles' => $mesesDisponibles,
            'totales' => $totales,
            'campanias' => $campanias,
            'evolucion' => $evolucion,
            'resultados_por_tipo' => $resultadosPorTipo,
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

        $mesesDisponibles = $entidades->mesesConDatosDeCampania($campaniaId);
        $periodo = PeriodoReporte::desdeRequest($request, $mesesDisponibles);
        $desde = $periodo->desde;
        $hasta = $periodo->hasta;

        $totales = $service->totalesCampania($clienteId, $campaniaId, $desde, $hasta);
        $adsets = $service->adsetsDeCampaniaConMetricas($clienteId, $campaniaId, $desde, $hasta);
        $anuncios = $service->anunciosDeCampaniaConMetricas($clienteId, $campaniaId, $desde, $hasta);

        $anunciosPorAdset = [];
        foreach ($anuncios as $a) {
            $anunciosPorAdset[(int) $a['adset_id']][] = $a;
        }

        $analisisService = $this->container->get(AnalisisCampaniaService::class);
        [$prevDesde, $prevHasta] = $analisisService->rangoAnterior($desde, $hasta);
        $totalesPrevios = $service->totalesCampania($clienteId, $campaniaId, $prevDesde, $prevHasta);
        $optGoalPredominante = $entidades->optimizationGoalPredominante($campaniaId);
        $campaniaConGoal = $cam + [
            'optimization_goal_predominante' => $optGoalPredominante,
        ];
        $evolucion = $service->evolucionDiariaCampania($clienteId, $campaniaId, $desde, $hasta);
        $resultadosPorTipo = $service->resultadosPorTipoCampania($clienteId, $campaniaId, $desde, $hasta);

        $analisis = $analisisService->generar(
            $totales,
            $campaniaConGoal,
            $totalesPrevios,
            (string) ($cam['moneda'] ?? ''),
            $desde,
            $hasta,
            $resultadosPorTipo,
        );

        $view = $this->container->get(View::class);

        return Response::html($view->render('admin/preview_campania', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Vista previa · ' . $cam['nombre'],
            'cliente' => $cliente,
            'campania' => $campaniaConGoal,
            'totales' => $totales,
            'adsets' => $adsets,
            'anuncios_por_adset' => $anunciosPorAdset,
            'desde' => $desde,
            'hasta' => $hasta,
            'periodo' => $periodo,
            'meses_disponibles' => $mesesDisponibles,
            'analisis' => $analisis,
            'evolucion' => $evolucion,
            'resultados_por_tipo' => $resultadosPorTipo,
        ]));
    }
}
