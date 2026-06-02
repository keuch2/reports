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

        $mesesDisponibles = $entidades->mesesConDatosDeCampania($campaniaId);
        $mesSeleccionado = $this->resolverMes((string) $request->input('mes', ''), $mesesDisponibles);
        if ($mesSeleccionado === null) {
            // Campaña sin snapshots — devuelve detalle vacío pero válido.
            $desde = date('Y-m-01');
            $hasta = date('Y-m-t');
        } else {
            [$desde, $hasta] = $this->rangoDelMes($mesSeleccionado);
        }

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
        $analisis = $analisisService->generar(
            $totales,
            $campaniaConGoal,
            $totalesPrevios,
            (string) ($cam['moneda'] ?? ''),
            $desde,
            $hasta,
        );

        $evolucion = $dashboard->evolucionDiariaCampania($clienteId, $campaniaId, $desde, $hasta);

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
            'mes_seleccionado' => $mesSeleccionado,
            'meses_disponibles' => $mesesDisponibles,
            'analisis' => $analisis,
            'evolucion' => $evolucion,
        ]));
    }

    /**
     * Si el mes pedido (YYYY-MM) está en la lista de disponibles lo usa.
     * Si no, devuelve el más reciente, o null si no hay datos.
     *
     * @param list<string> $disponibles
     */
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

    /** @return array{0:string,1:string} */
    private function rangoDelMes(string $yyyymm): array
    {
        $ts = strtotime($yyyymm . '-01');
        return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
    }
}
