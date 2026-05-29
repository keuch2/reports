<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Cliente;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Services\DashboardPreferenciasService;
use MisterCo\Reports\Services\PermisosService;

final class PreferenciasController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) $usuario->clienteId;

        $service = $this->container->get(DashboardPreferenciasService::class);
        $permisos = $this->container->get(PermisosService::class);
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('cliente/preferencias', [
            'usuario' => $usuario,
            'titulo' => 'Mis preferencias',
            'preferencias' => $service->obtener($clienteId),
            'widgets_disponibles' => DashboardPreferenciasService::WIDGETS_DISPONIBLES,
            'presets' => DashboardPreferenciasService::PRESETS_RANGO,
            'metricas_deshabilitadas_por_admin' => $permisos->metricasDeshabilitadas($clienteId),
            'success' => $session->getFlash('success'),
        ]));
    }

    public function guardar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $clienteId = (int) $usuario->clienteId;

        $widgetsOrden = trim((string) $request->input('widgets_orden', ''));
        $orden = $widgetsOrden === '' ? [] : explode(',', $widgetsOrden);
        $orden = array_values(array_filter(array_map('trim', $orden), 'strlen'));

        $rangoDefault = (string) $request->input('rango_default', 'ultimos_30_dias');

        $this->container->get(DashboardPreferenciasService::class)
            ->guardar($clienteId, $orden, $rangoDefault);

        $this->container->get(Session::class)->flash('success', 'Preferencias guardadas.');

        return Response::redirect('/cliente/preferencias');
    }
}
