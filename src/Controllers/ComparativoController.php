<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\ComparativoService;
use MisterCo\Reports\Services\ReportePdfService;

/**
 * Reporte comparativo: dos campañas (o la misma) en dos rangos de fechas.
 * Sirve al cliente (/cliente/comparar) y a la vista previa del admin
 * (/admin/clientes/{id}/comparar), igual que GoogleReporteController.
 */
final class ComparativoController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        $ctx = $this->contexto($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $service = $this->container->get(ComparativoService::class);
        $disponibles = $service->campaniasDisponibles($ctx['cliente_id']);

        // Sin selección: el formulario arranca con la primera campaña, últimos
        // 30 días contra los 30 anteriores.
        $primera = (string) (array_key_first($disponibles) ?? '');
        $specA = $this->spec($request, 'a', $primera, date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-1 day')));
        $specB = $this->spec($request, 'b', $specA['clave'], date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('-31 days')));

        $comparacion = null;
        $error = null;
        if ((string) $request->input('a', '') !== '') {
            $comparacion = $service->comparar($ctx['cliente_id'], $specA, $specB);
            $error = $comparacion === null ? 'Elegí campañas asignadas a este cliente.' : null;
        }

        return Response::html($this->container->get(View::class)->render('comparativo/comparar', $ctx['vista'] + [
            'titulo' => 'Comparar campañas',
            'disponibles' => $disponibles,
            'spec_a' => $specA,
            'spec_b' => $specB,
            'comparacion' => $comparacion,
            'error' => $error,
        ]));
    }

    public function pdf(Request $request): Response
    {
        $ctx = $this->contexto($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        $comparacion = $this->container->get(ComparativoService::class)->comparar(
            $ctx['cliente_id'],
            $this->spec($request, 'a', '', '', ''),
            $this->spec($request, 'b', '', '', ''),
        );
        if ($comparacion === null) {
            return Response::html('<h1>403 — Sin acceso a esas campañas.</h1>', 403);
        }

        $comentarios = trim((string) $request->input('comentarios', ''));
        $pdf = $this->container->get(ReportePdfService::class)->generarComparativo(
            $ctx['cliente_id'],
            $comparacion,
            $usuario->id,
            $comentarios !== '' ? $comentarios : null,
            filter_var($request->input('marca_de_agua', false), FILTER_VALIDATE_BOOLEAN),
        );

        $this->container->get(AuditService::class)->registrar(
            'pdf.generado', $usuario, $request->ip, $request->userAgent, 'reporte_pdf_comparativo', $pdf['nombre'],
            [
                'cliente_id' => $ctx['cliente_id'],
                'a' => $comparacion['a']['clave'] . " {$comparacion['a']['desde']} a {$comparacion['a']['hasta']}",
                'b' => $comparacion['b']['clave'] . " {$comparacion['b']['desde']} a {$comparacion['b']['hasta']}",
                'tamanio' => $pdf['tamanio'],
            ]
        );

        return Response::pdf($pdf);
    }

    /** @return array{clave:string, desde:string, hasta:string} */
    private function spec(Request $request, string $lado, string $clave, string $desde, string $hasta): array
    {
        return [
            'clave' => (string) $request->input($lado, $clave),
            'desde' => (string) $request->input($lado . '_desde', $desde),
            'hasta' => (string) $request->input($lado . '_hasta', $hasta),
        ];
    }

    /**
     * Cliente del request (el propio si es cliente, el de la ruta si es admin).
     *
     * @return array{cliente_id:int, vista:array<string,mixed>}|Response
     */
    private function contexto(Request $request): array|Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $esAdmin = $usuario->esAdmin();
        $clienteId = $esAdmin ? (int) ($request->attributes['id'] ?? 0) : (int) $usuario->clienteId;

        $cliente = $this->container->get(ClienteRepository::class)->buscarPorId($clienteId);
        if ($cliente === null) {
            return Response::html('<h1>404 — Cliente no encontrado.</h1>', 404);
        }

        return [
            'cliente_id' => $clienteId,
            'vista' => [
                'usuario' => $usuario,
                'cliente' => $cliente,
                'es_admin' => $esAdmin,
                'base' => $esAdmin ? '/admin/clientes/' . $clienteId . '/comparar' : '/cliente/comparar',
            ],
        ];
    }
}
