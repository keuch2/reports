<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Cliente;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Services\DashboardService;
use MisterCo\Reports\Services\PermisosService;

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
        $cuentaId = (int) $cam['cuenta_publicitaria_id'];

        // Aislamiento: el cliente debe tener acceso a la cuenta de esta campaña,
        // y la campaña no debe estar marcada como oculta.
        if (!$dashboard->clienteTieneAccesoACuenta($clienteId, $cuentaId)) {
            return Response::html('<h1>403 — Sin acceso a esta campaña.</h1>', 403);
        }
        $permisos = $this->container->get(PermisosService::class);
        if (in_array($campaniaId, $permisos->campaniasOcultas($clienteId, $cuentaId), true)) {
            return Response::html('<h1>403 — Sin acceso a esta campaña.</h1>', 403);
        }

        [$desde, $hasta, $preset] = $this->resolverRango(
            (string) $request->input('preset', 'ultimos_30_dias'),
        );

        $totales = $dashboard->totalesCampania($clienteId, $campaniaId, $desde, $hasta);
        $anuncios = $dashboard->anunciosDeCampaniaConMetricas($clienteId, $campaniaId, $desde, $hasta);

        $view = $this->container->get(View::class);

        return Response::html($view->render('cliente/campania_detalle', [
            'usuario' => $usuario,
            'titulo' => $cam['nombre'],
            'campania' => $cam,
            'totales' => $totales,
            'anuncios' => $anuncios,
            'desde' => $desde,
            'hasta' => $hasta,
            'preset' => $preset,
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
