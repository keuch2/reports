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

final class PermisosController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Vista de permisos por cliente. Si hay una cuenta seleccionada, muestra
     * sus campañas y anuncios. Las métricas son siempre por cliente.
     */
    public function mostrar(Request $request): Response
    {
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $repoCliente = $this->container->get(ClienteRepository::class);
        $cliente = $repoCliente->buscarPorId($clienteId);
        if ($cliente === null) {
            return Response::html('<h1>404</h1>', 404);
        }

        $cuentas = $repoCliente->cuentasAsignadas($clienteId);
        $cuentaId = (int) ($request->query['cuenta_id'] ?? ($cuentas[0]['id'] ?? 0));

        $permisos = $this->container->get(PermisosService::class);
        $entidades = $this->container->get(EntidadesMetaRepository::class);
        $catalogo = $this->container->get(MetricaCatalogoRepository::class);

        $campanias = [];
        $anunciosPorCampania = [];
        $camsOcultas = [];
        $anunciosOcultos = [];

        if ($cuentaId > 0) {
            $campanias = $entidades->campaniasDeCuenta($cuentaId);
            $camsOcultas = $permisos->campaniasOcultas($clienteId, $cuentaId);

            foreach ($campanias as $c) {
                $cid = (int) $c['id'];
                $anunciosPorCampania[$cid] = $entidades->anunciosDeCampania($cid);
                $anunciosOcultos[$cid] = $permisos->anunciosOcultosDeCampania($clienteId, $cid);
            }
        }

        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('admin/clientes_permisos', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Permisos · ' . $cliente['nombre_comercial'],
            'cliente' => $cliente,
            'cuentas' => $cuentas,
            'cuenta_id' => $cuentaId,
            'campanias' => $campanias,
            'anuncios_por_campania' => $anunciosPorCampania,
            'campanias_ocultas' => $camsOcultas,
            'anuncios_ocultos' => $anunciosOcultos,
            'catalogo' => $catalogo->listarPorCategoria(),
            'metricas_deshabilitadas' => $permisos->metricasDeshabilitadas($clienteId),
            'success' => $session->getFlash('success'),
            'error' => $session->getFlash('error'),
        ]));
    }

    public function guardarCampanias(Request $request): Response
    {
        /** @var \MisterCo\Reports\Domain\Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $cuentaId = (int) $request->input('cuenta_id', 0);
        $ocultas = array_map('intval', (array) ($request->post['ocultas'] ?? []));

        $this->container->get(PermisosService::class)
            ->reemplazarCampaniasOcultas($clienteId, $cuentaId, $ocultas);

        $this->container->get(AuditService::class)->registrar(
            'permisos.campanias_actualizadas', $usuario, $request->ip, $request->userAgent,
            'cliente', (string) $clienteId, ['cuenta_id' => $cuentaId, 'campanias_ocultas' => $ocultas]
        );
        $this->container->get(Session::class)->flash('success',
            count($ocultas) . ' campaña(s) marcadas como ocultas.');

        return Response::redirect("/admin/clientes/{$clienteId}/permisos?cuenta_id={$cuentaId}");
    }

    public function guardarAnuncios(Request $request): Response
    {
        /** @var \MisterCo\Reports\Domain\Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $cuentaId = (int) $request->input('cuenta_id', 0);
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

        return Response::redirect("/admin/clientes/{$clienteId}/permisos?cuenta_id={$cuentaId}");
    }

    public function guardarMetricas(Request $request): Response
    {
        /** @var \MisterCo\Reports\Domain\Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) ($request->attributes['id'] ?? 0);
        $cuentaId = (int) $request->input('cuenta_id', 0);
        $deshabilitadas = array_map('intval', (array) ($request->post['deshabilitadas'] ?? []));

        $this->container->get(PermisosService::class)
            ->reemplazarMetricasDeshabilitadas($clienteId, $deshabilitadas);

        $this->container->get(AuditService::class)->registrar(
            'permisos.metricas_actualizadas', $usuario, $request->ip, $request->userAgent,
            'cliente', (string) $clienteId, ['metricas_deshabilitadas' => $deshabilitadas]
        );
        $this->container->get(Session::class)->flash('success',
            count($deshabilitadas) . ' métrica(s) marcadas como deshabilitadas.');

        $base = "/admin/clientes/{$clienteId}/permisos";
        $base .= $cuentaId > 0 ? "?cuenta_id={$cuentaId}" : '';

        return Response::redirect($base);
    }
}
