<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Cliente;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
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
        $mesSeleccionado = $this->resolverMes((string) $request->input('mes', ''), $mesesDisponibles);
        [$desde, $hasta] = $this->resolverRango($request, $mesSeleccionado);

        $view = $this->container->get(View::class);

        return Response::html($view->render('cliente/reporte_previa', [
            'usuario' => $usuario,
            'titulo' => 'Generar reporte',
            'meses_disponibles' => $mesesDisponibles,
            'mes_seleccionado' => $mesSeleccionado,
            'desde' => $desde,
            'hasta' => $hasta,
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

        $mesSeleccionado = $this->resolverMes(
            (string) $request->input('mes', ''),
            $dashboard->mesesConDatosDelCliente($clienteId)
        );
        [$desde, $hasta] = $this->resolverRango($request, $mesSeleccionado);

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

        return $this->responderPdf($pdf);
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
        $mesSeleccionado = $this->resolverMes(
            (string) $request->input('mes', ''),
            $entidades->mesesConDatosDeCampania($campaniaId)
        );
        [$desde, $hasta] = $this->resolverRango($request, $mesSeleccionado);

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

        return $this->responderPdf($pdf);
    }

    /**
     * @param array{ruta:string, nombre:string, tamanio:int} $pdf
     */
    private function responderPdf(array $pdf): Response
    {
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

    /**
     * Resuelve el rango de fechas igual que el dashboard: si llega un rango
     * personalizado (desde/hasta) válido, lo respeta; si no, usa el mes
     * calendario completo del mes seleccionado (YYYY-MM). Si tampoco hay mes,
     * cae al mes actual. Así el PDF SIEMPRE cubre el mismo período que la
     * pantalla desde la que se exportó.
     *
     * @return array{0:string,1:string}
     */
    private function resolverRango(Request $request, ?string $mesSeleccionado): array
    {
        $desdeInput = (string) $request->input('desde', '');
        $hastaInput = (string) $request->input('hasta', '');
        $fecha = '/^\d{4}-\d{2}-\d{2}$/';
        if (preg_match($fecha, $desdeInput) && preg_match($fecha, $hastaInput)) {
            return [$desdeInput, $hastaInput];
        }

        $ts = $mesSeleccionado !== null ? strtotime($mesSeleccionado . '-01') : time();

        return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
    }

    /**
     * Si el mes pedido (YYYY-MM) está disponible lo usa; si no, el más reciente;
     * null si el cliente/campaña no tiene meses con datos.
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
}
