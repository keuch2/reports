<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\PeriodoReporte;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\GoogleReporteService;
use MisterCo\Reports\Services\ReportePdfService;

/**
 * Dashboards y PDF de Google Ads y Google Analytics de un cliente.
 *
 * Las mismas acciones sirven al cliente (/cliente/...) y a la vista previa del
 * admin (/admin/clientes/{id}/...): el admin ve exactamente lo mismo que el
 * cliente, con su header y un banner, y los PDF quedan a su nombre.
 */
final class GoogleReporteController
{
    private const FUENTES = [
        'ads' => ['ruta' => 'google-ads', 'nombre' => 'Google Ads', 'template' => 'google/ads_dashboard'],
        'analytics' => ['ruta' => 'analytics', 'nombre' => 'Google Analytics', 'template' => 'google/analytics_dashboard'],
    ];

    public function __construct(private readonly Container $container)
    {
    }

    public function adsDashboard(Request $request): Response
    {
        return $this->dashboard($request, 'ads');
    }

    public function adsPrevia(Request $request): Response
    {
        return $this->previa($request, 'ads');
    }

    public function adsPdf(Request $request): Response
    {
        return $this->pdf($request, 'ads');
    }

    public function analyticsDashboard(Request $request): Response
    {
        return $this->dashboard($request, 'analytics');
    }

    public function analyticsPrevia(Request $request): Response
    {
        return $this->previa($request, 'analytics');
    }

    public function analyticsPdf(Request $request): Response
    {
        return $this->pdf($request, 'analytics');
    }

    private function dashboard(Request $request, string $fuente): Response
    {
        $ctx = $this->contexto($request, $fuente);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $service = $this->container->get(GoogleReporteService::class);
        $meses = $this->meses($fuente, $ctx['cliente_id']);
        $periodo = PeriodoReporte::desdeRequest($request, $meses);
        $datos = $fuente === 'ads'
            ? $service->datosAds($ctx['cliente_id'], $periodo->desde, $periodo->hasta)
            : $service->datosAnalytics($ctx['cliente_id'], $periodo->desde, $periodo->hasta);

        return Response::html($this->container->get(View::class)->render(self::FUENTES[$fuente]['template'], $ctx['vista'] + $datos + [
            'titulo' => self::FUENTES[$fuente]['nombre'],
            'periodo' => $periodo,
            'meses_disponibles' => $meses,
        ]));
    }

    private function previa(Request $request, string $fuente): Response
    {
        $ctx = $this->contexto($request, $fuente);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $meses = $this->meses($fuente, $ctx['cliente_id']);

        return Response::html($this->container->get(View::class)->render('google/reporte_previa', $ctx['vista'] + [
            'titulo' => 'Generar reporte · ' . self::FUENTES[$fuente]['nombre'],
            'nombre_fuente' => self::FUENTES[$fuente]['nombre'],
            'periodo' => PeriodoReporte::desdeRequest($request, $meses),
            'meses_disponibles' => $meses,
        ]));
    }

    private function pdf(Request $request, string $fuente): Response
    {
        $ctx = $this->contexto($request, $fuente);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $clienteId = $ctx['cliente_id'];
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        $periodo = PeriodoReporte::desdeRequest($request, $this->meses($fuente, $clienteId));
        $comentarios = trim((string) $request->input('comentarios', ''));
        $comentarios = $comentarios !== '' ? $comentarios : null;
        $marcaDeAgua = filter_var($request->input('marca_de_agua', false), FILTER_VALIDATE_BOOLEAN);

        $pdfService = $this->container->get(ReportePdfService::class);
        $pdf = $fuente === 'ads'
            ? $pdfService->generarGoogleAds($clienteId, $periodo->desde, $periodo->hasta, $usuario->id, $comentarios, $marcaDeAgua)
            : $pdfService->generarAnalytics($clienteId, $periodo->desde, $periodo->hasta, $usuario->id, $comentarios, $marcaDeAgua);

        $this->container->get(AuditService::class)->registrar(
            'pdf.generado', $usuario, $request->ip, $request->userAgent,
            $fuente === 'ads' ? 'reporte_pdf_google_ads' : 'reporte_pdf_google_analytics', $pdf['nombre'],
            ['cliente_id' => $clienteId, 'rango' => "{$periodo->desde} a {$periodo->hasta}", 'tamanio' => $pdf['tamanio']]
        );

        return Response::pdf($pdf);
    }

    /**
     * Cliente del request (el propio si es cliente, el de la ruta si es admin)
     * con los datos comunes de la vista. El cliente sin asignaciones de esta
     * fuente recibe 403; el admin ve la pantalla con el aviso.
     *
     * @return array{cliente_id:int, vista:array<string,mixed>}|Response
     */
    private function contexto(Request $request, string $fuente): array|Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $esAdmin = $usuario->esAdmin();
        $clienteId = $esAdmin ? (int) ($request->attributes['id'] ?? 0) : (int) $usuario->clienteId;

        $cliente = $this->container->get(ClienteRepository::class)->buscarPorId($clienteId);
        if ($cliente === null) {
            return Response::html('<h1>404 — Cliente no encontrado.</h1>', 404);
        }

        $service = $this->container->get(GoogleReporteService::class);
        $asignado = $fuente === 'ads' ? $service->tieneGoogleAds($clienteId) : $service->tieneAnalytics($clienteId);
        if (!$asignado && !$esAdmin) {
            return Response::html('<h1>403 — No tenés datos de ' . self::FUENTES[$fuente]['nombre'] . ' asignados.</h1>', 403);
        }

        return [
            'cliente_id' => $clienteId,
            'vista' => [
                'usuario' => $usuario,
                'cliente' => $cliente,
                'es_admin' => $esAdmin,
                'asignado' => $asignado,
                'base' => ($esAdmin ? '/admin/clientes/' . $clienteId : '/cliente') . '/' . self::FUENTES[$fuente]['ruta'],
            ],
        ];
    }

    /** @return list<string> */
    private function meses(string $fuente, int $clienteId): array
    {
        $service = $this->container->get(GoogleReporteService::class);

        return $fuente === 'ads' ? $service->mesesAds($clienteId) : $service->mesesAnalytics($clienteId);
    }
}
