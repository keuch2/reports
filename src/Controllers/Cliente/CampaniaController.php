<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Cliente;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\PeriodoReporte;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Services\AnalisisCampaniaService;
use MisterCo\Reports\Services\DashboardService;

final class CampaniaController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function detalle(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) $usuario->clienteId;
        $campaniaId = (int) ($request->attributes['id'] ?? 0);

        $entidades = $this->container->get(EntidadesMetaRepository::class);
        $cam = $entidades->buscarCampania($campaniaId);
        if ($cam === null) {
            return Response::html('<h1>404 — Campaña no encontrada</h1>', 404);
        }

        $dashboard = $this->container->get(DashboardService::class);

        // Aislamiento: la campaña tiene que estar explícitamente asignada al cliente.
        if (!$dashboard->clienteTieneAccesoACampania($clienteId, $campaniaId)) {
            return Response::html('<h1>403 — Sin acceso a esta campaña.</h1>', 403);
        }

        $mesesDisponibles = $entidades->mesesConDatosDeCampania($campaniaId);
        $periodo = PeriodoReporte::desdeRequest($request, $mesesDisponibles);
        $desde = $periodo->desde;
        $hasta = $periodo->hasta;

        $totales = $dashboard->totalesCampania($clienteId, $campaniaId, $desde, $hasta);
        $adsets = $dashboard->adsetsDeCampaniaConMetricas($clienteId, $campaniaId, $desde, $hasta);
        $anuncios = $dashboard->anunciosDeCampaniaConMetricas($clienteId, $campaniaId, $desde, $hasta);

        // Agrupar anuncios por adset_id para mostrarlos colapsables debajo de cada grupo.
        $anunciosPorAdset = [];
        foreach ($anuncios as $a) {
            $anunciosPorAdset[(int) $a['adset_id']][] = $a;
        }

        $analisisService = $this->container->get(AnalisisCampaniaService::class);
        [$prevDesde, $prevHasta] = $analisisService->rangoAnterior($desde, $hasta);
        $totalesPrevios = $dashboard->totalesCampania($clienteId, $campaniaId, $prevDesde, $prevHasta);
        $optGoalPredominante = $entidades->optimizationGoalPredominante($campaniaId);
        $campaniaConGoal = $cam + [
            'optimization_goal_predominante' => $optGoalPredominante,
        ];
        $evolucion = $dashboard->evolucionDiariaCampania($clienteId, $campaniaId, $desde, $hasta);
        $resultadosPorTipo = $dashboard->resultadosPorTipoCampania($clienteId, $campaniaId, $desde, $hasta);

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

        return Response::html($view->render('cliente/campania_detalle', [
            'usuario' => $usuario,
            'titulo' => $cam['nombre'],
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
