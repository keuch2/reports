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
use MisterCo\Reports\Repositories\PlantillaPdfRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\DashboardService;
use MisterCo\Reports\Services\ReportePdfService;

final class ReporteController
{
    public function __construct(private readonly Container $container)
    {
    }

    /** Vista previa con comentario editable antes de generar el PDF. */
    public function previa(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) $usuario->clienteId;

        $dashboard = $this->container->get(DashboardService::class);
        if ($dashboard->campaniasDelCliente($clienteId) === []) {
            return Response::html('<h1>403 — No tenés campañas asignadas.</h1>', 403);
        }

        $mesesDisponibles = $dashboard->mesesConDatosDelCliente($clienteId);
        $periodo = PeriodoReporte::desdeRequest($request, $mesesDisponibles);

        $view = $this->container->get(View::class);

        return Response::html($view->render('cliente/reporte_previa', [
            'usuario' => $usuario,
            'titulo' => 'Generar reporte',
            'meses_disponibles' => $mesesDisponibles,
            'periodo' => $periodo,
        ]));
    }

    public function descargar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) $usuario->clienteId;

        $dashboard = $this->container->get(DashboardService::class);
        if ($dashboard->campaniasDelCliente($clienteId) === []) {
            return Response::html('<h1>403 — No tenés campañas asignadas.</h1>', 403);
        }

        $periodo = PeriodoReporte::desdeRequest($request, $dashboard->mesesConDatosDelCliente($clienteId));
        [$desde, $hasta] = [$periodo->desde, $periodo->hasta];

        $comentarios = trim((string) $request->input('comentarios', ''));
        $marcaDeAgua = filter_var($request->input('marca_de_agua', false), FILTER_VALIDATE_BOOLEAN);

        $plantillaRepo = $this->container->get(PlantillaPdfRepository::class);
        $plantilla = $plantillaRepo->paraCliente($clienteId);
        $secciones = $plantilla !== null ? PlantillaPdfRepository::seccionesDe($plantilla) : [];

        $pdf = $this->container->get(ReportePdfService::class)
            ->generar($clienteId, $desde, $hasta, $usuario->id, $secciones,
                $comentarios !== '' ? $comentarios : null, $marcaDeAgua);

        $this->container->get(AuditService::class)->registrar(
            'pdf.generado', $usuario, $request->ip, $request->userAgent,
            'reporte_pdf', (string) ($pdf['nombre'] ?? ''),
            ['rango' => "{$desde} a {$hasta}", 'tamanio' => $pdf['tamanio'] ?? 0]
        );

        return Response::pdf($pdf);
    }

    public function descargarCampania(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) $usuario->clienteId;
        $campaniaId = (int) ($request->attributes['id'] ?? 0);

        $dashboard = $this->container->get(DashboardService::class);
        if (!$dashboard->clienteTieneAccesoACampania($clienteId, $campaniaId)) {
            return Response::html('<h1>403 — Sin acceso a esta campaña.</h1>', 403);
        }

        $entidades = $this->container->get(EntidadesMetaRepository::class);
        $periodo = PeriodoReporte::desdeRequest($request, $entidades->mesesConDatosDeCampania($campaniaId));
        [$desde, $hasta] = [$periodo->desde, $periodo->hasta];

        $comentarios = trim((string) $request->input('comentarios', ''));
        $marcaDeAgua = filter_var($request->input('marca_de_agua', false), FILTER_VALIDATE_BOOLEAN);

        $pdf = $this->container->get(ReportePdfService::class)
            ->generarCampania($clienteId, $campaniaId, $desde, $hasta, $usuario->id,
                $comentarios !== '' ? $comentarios : null, $marcaDeAgua);

        $this->container->get(AuditService::class)->registrar(
            'pdf.generado', $usuario, $request->ip, $request->userAgent,
            'reporte_pdf_campania', (string) ($pdf['nombre'] ?? ''),
            ['campania_id' => $campaniaId, 'rango' => "{$desde} a {$hasta}", 'tamanio' => $pdf['tamanio'] ?? 0]
        );

        return Response::pdf($pdf);
    }
}
