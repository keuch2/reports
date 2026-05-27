<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Cliente;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Services\DashboardService;
use MisterCo\Reports\Services\ReportePdfService;

final class ReporteController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function descargar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) $usuario->clienteId;
        $cuentaId = (int) $request->input('cuenta_id', 0);

        $dashboard = $this->container->get(DashboardService::class);
        if (!$dashboard->clienteTieneAccesoACuenta($clienteId, $cuentaId)) {
            return Response::html('<h1>403 — Sin acceso a esa cuenta.</h1>', 403);
        }

        [$desde, $hasta] = $this->resolverRango(
            (string) $request->input('preset', 'ultimos_30_dias'),
            (string) $request->input('desde', ''),
            (string) $request->input('hasta', ''),
        );

        $pdf = $this->container->get(ReportePdfService::class)
            ->generar($clienteId, $cuentaId, $desde, $hasta, $usuario->id);

        $contenido = (string) file_get_contents($pdf['ruta']);

        return new Response(
            body: $contenido,
            status: 200,
            headers: [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $pdf['nombre'] . '"',
                'Content-Length' => (string) strlen($contenido),
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    /** @return array{0:string,1:string} */
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

        if ($preset === 'personalizado' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desdeInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hastaInput)) {
            return [$desdeInput, $hastaInput];
        }

        return $presets[$preset] ?? $presets['ultimos_30_dias'];
    }
}
