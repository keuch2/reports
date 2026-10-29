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
use MisterCo\Reports\Services\RedesReporteService;
use MisterCo\Reports\Services\ReportePdfService;

/**
 * Dashboards y PDF por fuente de un cliente: Google Ads, Google Analytics y
 * Facebook/Instagram (analíticas orgánicas de Meta).
 *
 * Las mismas acciones sirven al cliente (/cliente/...) y a la vista previa del
 * admin (/admin/clientes/{id}/...): el admin ve exactamente lo mismo que el
 * cliente, con su header y un banner, y los PDF quedan a su nombre.
 */
final class ReporteFuenteController
{
    private const FUENTES = [
        'ads' => ['ruta' => 'google-ads', 'nombre' => 'Google Ads', 'template' => 'google/ads_dashboard'],
        'analytics' => ['ruta' => 'analytics', 'nombre' => 'Google Analytics', 'template' => 'google/analytics_dashboard'],
        'redes' => ['ruta' => 'redes', 'nombre' => 'Facebook e Instagram', 'template' => 'redes/dashboard'],
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

    public function redesDashboard(Request $request): Response
    {
        return $this->dashboard($request, 'redes');
    }

    public function redesPrevia(Request $request): Response
    {
        return $this->previa($request, 'redes');
    }

    public function redesPdf(Request $request): Response
    {
        return $this->pdf($request, 'redes');
    }

    private function dashboard(Request $request, string $fuente): Response
    {
        $ctx = $this->contexto($request, $fuente);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $meses = $this->meses($fuente, $ctx['cliente_id']);
        $periodo = PeriodoReporte::desdeRequest($request, $meses);
        $google = $this->container->get(GoogleReporteService::class);
        $datos = match ($fuente) {
            'ads' => $google->datosAds($ctx['cliente_id'], $periodo->desde, $periodo->hasta),
            'analytics' => $google->datosAnalytics($ctx['cliente_id'], $periodo->desde, $periodo->hasta),
            'redes' => $this->container->get(RedesReporteService::class)->datos($ctx['cliente_id'], $periodo->desde, $periodo->hasta),
        };

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
            'seccion' => $fuente === 'ads' ? 'google_ads' : $fuente,
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
        $pdf = match ($fuente) {
            'ads' => $pdfService->generarGoogleAds($clienteId, $periodo->desde, $periodo->hasta, $usuario->id, $comentarios, $marcaDeAgua),
            'analytics' => $pdfService->generarAnalytics($clienteId, $periodo->desde, $periodo->hasta, $usuario->id, $comentarios, $marcaDeAgua),
            'redes' => $pdfService->generarRedes($clienteId, $periodo->desde, $periodo->hasta, $usuario->id, $comentarios, $marcaDeAgua),
        };

        $this->container->get(AuditService::class)->registrar(
            'pdf.generado', $usuario, $request->ip, $request->userAgent,
            ['ads' => 'reporte_pdf_google_ads', 'analytics' => 'reporte_pdf_google_analytics', 'redes' => 'reporte_pdf_redes'][$fuente], $pdf['nombre'],
            ['cliente_id' => $clienteId, 'rango' => "{$periodo->desde} a {$periodo->hasta}", 'tamanio' => $pdf['tamanio']]
        );

        return Response::pdf($pdf);
    }

    /**
     * Cliente del request (el propio si es cliente, el de la ruta si es admin)
     * con los datos comunes de la vista. El cliente sin asignaciones de esta
     * fuente (cliente o admin) vuelve al inicio del dashboard.
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

        $google = $this->container->get(GoogleReporteService::class);
        $asignado = match ($fuente) {
            'ads' => $google->tieneGoogleAds($clienteId),
            'analytics' => $google->tieneAnalytics($clienteId),
            'redes' => $this->container->get(RedesReporteService::class)->tieneRedes($clienteId),
        };
        if (!$asignado) {
            // Sin datos de esta fuente no hay sección: al inicio del dashboard, que lleva a la primera con datos.
            return Response::redirect($esAdmin ? '/admin/clientes/' . $clienteId . '/dashboard' : '/cliente');
        }

        return [
            'cliente_id' => $clienteId,
            'vista' => [
                'usuario' => $usuario,
                'cliente' => $cliente,
                'es_admin' => $esAdmin,
                'base' => ($esAdmin ? '/admin/clientes/' . $clienteId : '/cliente') . '/' . self::FUENTES[$fuente]['ruta'],
                'fuentes' => $this->container->get(ClienteRepository::class)->fuentesAsignadas($clienteId),
            ],
        ];
    }

    /** @return list<string> */
    private function meses(string $fuente, int $clienteId): array
    {
        $google = $this->container->get(GoogleReporteService::class);

        return match ($fuente) {
            'ads' => $google->mesesAds($clienteId),
            'analytics' => $google->mesesAnalytics($clienteId),
            'redes' => $this->container->get(RedesReporteService::class)->meses($clienteId),
        };
    }
}
