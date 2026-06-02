<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Cliente;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
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

        [$desde, $hasta, $preset] = $this->resolverRango(
            (string) $request->input('preset', 'ultimos_30_dias'),
        );

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
        $campaniaConGoal = $cam + [
            'optimization_goal_predominante' => $entidades->optimizationGoalPredominante($campaniaId),
        ];
        $analisis = $analisisService->generar(
            $totales,
            $campaniaConGoal,
            $totalesPrevios,
            (string) ($cam['moneda'] ?? ''),
            $desde,
            $hasta,
        );

        $view = $this->container->get(View::class);

        return Response::html($view->render('cliente/campania_detalle', [
            'usuario' => $usuario,
            'titulo' => $cam['nombre'],
            'campania' => $cam,
            'totales' => $totales,
            'adsets' => $adsets,
            'anuncios_por_adset' => $anunciosPorAdset,
            'desde' => $desde,
            'hasta' => $hasta,
            'preset' => $preset,
            'analisis' => $analisis,
        ]));
    }

    /** @return array{0:string,1:string,2:string} */
    private function resolverRango(string $preset): array
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
        $r = $presets[$preset] ?? $presets['ultimos_30_dias'];

        return [$r[0], $r[1], array_key_exists($preset, $presets) ? $preset : 'ultimos_30_dias'];
    }
}
