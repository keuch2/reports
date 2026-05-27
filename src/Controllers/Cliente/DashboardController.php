<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Cliente;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Services\DashboardService;

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

        $cuentas = $service->cuentasDelCliente($clienteId);
        if ($cuentas === []) {
            return Response::html($view->render('cliente/sin_datos', [
                'usuario' => $usuario,
                'titulo' => 'Mi dashboard',
            ]));
        }

        $cuentaIdSolicitada = (int) $request->input('cuenta_id', 0);
        $cuentaActiva = $this->elegirCuenta($cuentas, $cuentaIdSolicitada);

        [$desde, $hasta, $preset] = $this->resolverRango(
            (string) $request->input('preset', 'ultimos_30_dias'),
            (string) $request->input('desde', ''),
            (string) $request->input('hasta', ''),
        );

        $totales = $service->totalesPorCuenta($clienteId, (int) $cuentaActiva['id'], $desde, $hasta);
        $campanias = $service->porCampania($clienteId, (int) $cuentaActiva['id'], $desde, $hasta);
        $evolucion = $service->evolucionDiaria($clienteId, (int) $cuentaActiva['id'], $desde, $hasta);

        return Response::html($view->render('cliente/dashboard_meta', [
            'usuario' => $usuario,
            'titulo' => 'Dashboard · ' . $cuentaActiva['nombre'],
            'cuentas' => $cuentas,
            'cuenta_activa' => $cuentaActiva,
            'desde' => $desde,
            'hasta' => $hasta,
            'preset' => $preset,
            'totales' => $totales,
            'campanias' => $campanias,
            'evolucion' => $evolucion,
        ]));
    }

    /**
     * @param list<array<string,mixed>> $cuentas
     * @return array<string,mixed>
     */
    private function elegirCuenta(array $cuentas, int $idSolicitada): array
    {
        if ($idSolicitada > 0) {
            foreach ($cuentas as $c) {
                if ((int) $c['id'] === $idSolicitada) {
                    return $c;
                }
            }
        }

        return $cuentas[0];
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
