<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Repositories\MetricaCatalogoRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\PermisosService;

/**
 * Permisos avanzados por cliente — complementan al modelo "asignar campañas".
 *
 * - Anuncios ocultos: dentro de cada campaña asignada, el admin puede ocultar
 *   anuncios específicos (caso uso: ocultar creativos en testing).
 * - Métricas deshabilitadas: por cliente, qué métricas no se muestran en
 *   widgets, dashboard ni PDFs.
 *
 * La asignación de CAMPAÑAS al cliente vive en /admin/clientes/{id} (no acá).
 */
final class PermisosController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $repoCliente = $this->container->get(ClienteRepository::class);
        $cliente = $repoCliente->buscarPorId($clienteId);
        if ($cliente === null) {
            return Response::html('<h1>404</h1>', 404);
        }

        $permisos = $this->container->get(PermisosService::class);
        $entidades = $this->container->get(EntidadesMetaRepository::class);
        $catalogo = $this->container->get(MetricaCatalogoRepository::class);

        // Trabajamos sobre las campañas asignadas — no sobre todas las cuentas.
        $campaniasAsignadas = $repoCliente->campaniasAsignadas($clienteId);

        $anunciosPorCampania = [];
        $anunciosOcultos = [];
        foreach ($campaniasAsignadas as $c) {
            $cid = (int) $c['id'];
            $anunciosPorCampania[$cid] = $entidades->anunciosDeCampania($cid);
            $anunciosOcultos[$cid] = $permisos->anunciosOcultosDeCampania($clienteId, $cid);
        }

        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('admin/clientes_permisos', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Permisos · ' . $cliente['nombre_comercial'],
            'cliente' => $cliente,
            'campanias_asignadas' => $campaniasAsignadas,
            'anuncios_por_campania' => $anunciosPorCampania,
            'anuncios_ocultos' => $anunciosOcultos,
            'catalogo' => $catalogo->listarPorCategoria(),
            'metricas_deshabilitadas' => $permisos->metricasDeshabilitadas($clienteId),
            'success' => $session->getFlash('success'),
            'error' => $session->getFlash('error'),
        ]));
    }

    public function guardarAnuncios(Request $request): Response
    {
        /** @var \MisterCo\Reports\Domain\Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $campaniaId = (int) $request->input('campania_id', 0);
        $ocultos = array_map('intval', (array) ($request->post['ocultos'] ?? []));

        $this->container->get(PermisosService::class)
            ->reemplazarAnunciosOcultos($clienteId, $campaniaId, $ocultos);

        $this->container->get(AuditService::class)->registrar(
            'permisos.anuncios_actualizados', $usuario, $request->ip, $request->userAgent,
            'cliente', (string) $clienteId, ['campania_id' => $campaniaId, 'anuncios_ocultos' => $ocultos]
        );
        $this->container->get(Session::class)->flash('success',
            count($ocultos) . ' anuncio(s) marcados como ocultos en esa campaña.');

        return Response::redirect("/admin/clientes/{$clienteId}/permisos");
    }

    public function guardarMetricas(Request $request): Response
    {
        /** @var \MisterCo\Reports\Domain\Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $deshabilitadas = array_map('intval', (array) ($request->post['deshabilitadas'] ?? []));

        $this->container->get(PermisosService::class)
            ->reemplazarMetricasDeshabilitadas($clienteId, $deshabilitadas);

        $this->container->get(AuditService::class)->registrar(
            'permisos.metricas_actualizadas', $usuario, $request->ip, $request->userAgent,
            'cliente', (string) $clienteId, ['metricas_deshabilitadas' => $deshabilitadas]
        );
        $this->container->get(Session::class)->flash('success',
            count($deshabilitadas) . ' métrica(s) marcadas como deshabilitadas.');

        return Response::redirect("/admin/clientes/{$clienteId}/permisos");
    }
}
