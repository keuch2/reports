<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\PeriodoReporte;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Repositories\PlantillaPdfRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\DashboardService;
use MisterCo\Reports\Services\ReportePdfService;

/**
 * Reportes PDF de un cliente generados desde la sesión del admin.
 *
 * Mismo PDF que descarga el cliente (mismo ReportePdfService, misma plantilla,
 * mismos permisos de anuncios/métricas), pero queda registrado a nombre del
 * admin en el histórico y en auditoría. No impersona al cliente.
 */
final class ReporteClienteController
{
    public function __construct(private readonly Container $container)
    {
    }

    /** Vista previa con período, comentarios y marca de agua antes de generar el PDF. */
    public function previa(Request $request): Response
    {
        $cliente = $this->clienteConCampanias($request);
        if ($cliente instanceof Response) {
            return $cliente;
        }
        $clienteId = (int) $cliente['id'];

        $mesesDisponibles = $this->container->get(DashboardService::class)->mesesConDatosDelCliente($clienteId);

        return Response::html($this->container->get(View::class)->render('admin/preview_reporte', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Generar reporte · ' . $cliente['nombre_comercial'],
            'cliente' => $cliente,
            'meses_disponibles' => $mesesDisponibles,
            'periodo' => PeriodoReporte::desdeRequest($request, $mesesDisponibles),
        ]));
    }

    public function descargar(Request $request): Response
    {
        $cliente = $this->clienteConCampanias($request);
        if ($cliente instanceof Response) {
            return $cliente;
        }
        $clienteId = (int) $cliente['id'];
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        $periodo = PeriodoReporte::desdeRequest(
            $request,
            $this->container->get(DashboardService::class)->mesesConDatosDelCliente($clienteId)
        );
        [$desde, $hasta] = [$periodo->desde, $periodo->hasta];

        $comentarios = trim((string) $request->input('comentarios', ''));
        $marcaDeAgua = filter_var($request->input('marca_de_agua', false), FILTER_VALIDATE_BOOLEAN);

        $plantilla = $this->container->get(PlantillaPdfRepository::class)->paraCliente($clienteId);
        $secciones = $plantilla !== null ? PlantillaPdfRepository::seccionesDe($plantilla) : [];

        $pdf = $this->container->get(ReportePdfService::class)
            ->generar($clienteId, $desde, $hasta, $usuario->id, $secciones,
                $comentarios !== '' ? $comentarios : null, $marcaDeAgua);

        $this->container->get(AuditService::class)->registrar(
            'pdf.generado', $usuario, $request->ip, $request->userAgent,
            'reporte_pdf', (string) ($pdf['nombre'] ?? ''),
            ['cliente_id' => $clienteId, 'rango' => "{$desde} a {$hasta}", 'tamanio' => $pdf['tamanio'] ?? 0]
        );

        return Response::pdf($pdf);
    }

    public function descargarCampania(Request $request): Response
    {
        $cliente = $this->clienteConCampanias($request);
        if ($cliente instanceof Response) {
            return $cliente;
        }
        $clienteId = (int) $cliente['id'];
        $campaniaId = (int) ($request->attributes['cid'] ?? 0);
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        if (!$this->container->get(DashboardService::class)->clienteTieneAccesoACampania($clienteId, $campaniaId)) {
            return Response::html('<h1>Esta campaña no está asignada a este cliente.</h1>', 404);
        }

        $periodo = PeriodoReporte::desdeRequest(
            $request,
            $this->container->get(EntidadesMetaRepository::class)->mesesConDatosDeCampania($campaniaId)
        );
        [$desde, $hasta] = [$periodo->desde, $periodo->hasta];

        $comentarios = trim((string) $request->input('comentarios', ''));
        $marcaDeAgua = filter_var($request->input('marca_de_agua', false), FILTER_VALIDATE_BOOLEAN);

        $pdf = $this->container->get(ReportePdfService::class)
            ->generarCampania($clienteId, $campaniaId, $desde, $hasta, $usuario->id,
                $comentarios !== '' ? $comentarios : null, $marcaDeAgua);

        $this->container->get(AuditService::class)->registrar(
            'pdf.generado', $usuario, $request->ip, $request->userAgent,
            'reporte_pdf_campania', (string) ($pdf['nombre'] ?? ''),
            ['cliente_id' => $clienteId, 'campania_id' => $campaniaId, 'rango' => "{$desde} a {$hasta}", 'tamanio' => $pdf['tamanio'] ?? 0]
        );

        return Response::pdf($pdf);
    }

    /**
     * Cliente de la ruta, o la respuesta de error si no existe o no tiene campañas.
     *
     * @return array<string,mixed>|Response
     */
    private function clienteConCampanias(Request $request): array|Response
    {
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $cliente = $this->container->get(ClienteRepository::class)->buscarPorId($clienteId);
        if ($cliente === null) {
            return Response::html('<h1>404 — Cliente no encontrado.</h1>', 404);
        }
        if ($this->container->get(DashboardService::class)->campaniasDelCliente($clienteId) === []) {
            return Response::html('<h1>Este cliente no tiene campañas asignadas.</h1>', 404);
        }

        return $cliente;
    }
}
