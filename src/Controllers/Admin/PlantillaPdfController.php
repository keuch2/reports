<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Repositories\PlantillaPdfRepository;

final class PlantillaPdfController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function listar(Request $request): Response
    {
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(PlantillaPdfRepository::class);

        return Response::html($view->render('admin/plantillas_lista', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Plantillas PDF',
            'plantillas' => $repo->listar(),
            'success' => $session->getFlash('success'),
        ]));
    }

    public function mostrarFormulario(Request $request): Response
    {
        $id = (int) ($request->attributes['id'] ?? 0);
        $repo = $this->container->get(PlantillaPdfRepository::class);
        $clientes = $this->container->get(ClienteRepository::class)->listarActivos();

        $plantilla = null;
        $seccionesActuales = array_keys(PlantillaPdfRepository::SECCIONES_DISPONIBLES);
        if ($id > 0) {
            $plantilla = $repo->buscarPorId($id);
            if ($plantilla === null) {
                return Response::html('<h1>404</h1>', 404);
            }
            $seccionesActuales = PlantillaPdfRepository::seccionesDe($plantilla);
        }

        $view = $this->container->get(View::class);

        return Response::html($view->render('admin/plantillas_form', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => $plantilla ? 'Editar plantilla' : 'Nueva plantilla',
            'plantilla' => $plantilla,
            'secciones_actuales' => $seccionesActuales,
            'secciones_disponibles' => PlantillaPdfRepository::SECCIONES_DISPONIBLES,
            'clientes' => $clientes,
        ]));
    }

    public function guardar(Request $request): Response
    {
        $id = (int) ($request->attributes['id'] ?? 0);
        $session = $this->container->get(Session::class);

        $nombre = trim((string) $request->input('nombre', ''));
        $descripcion = trim((string) $request->input('descripcion', ''));
        $clienteId = (int) $request->input('cliente_id', 0);
        $secciones = array_values(array_intersect(
            (array) ($request->post['secciones'] ?? []),
            array_keys(PlantillaPdfRepository::SECCIONES_DISPONIBLES)
        ));

        if ($nombre === '' || $secciones === []) {
            $session->flash('error', 'Nombre y al menos una sección son requeridos.');

            return Response::redirect($id > 0 ? "/admin/plantillas/{$id}/editar" : '/admin/plantillas/nueva');
        }

        $repo = $this->container->get(PlantillaPdfRepository::class);
        $clienteIdFinal = $clienteId > 0 ? $clienteId : null;

        if ($id > 0) {
            $repo->actualizar($id, $nombre, $descripcion !== '' ? $descripcion : null, $secciones, $clienteIdFinal);
            $session->flash('success', 'Plantilla actualizada.');
        } else {
            $repo->crear($nombre, $descripcion !== '' ? $descripcion : null, $secciones, $clienteIdFinal);
            $session->flash('success', 'Plantilla creada.');
        }

        return Response::redirect('/admin/plantillas');
    }

    public function eliminar(Request $request): Response
    {
        $id = (int) ($request->attributes['id'] ?? 0);
        if ($id > 0) {
            $this->container->get(PlantillaPdfRepository::class)->eliminar($id);
            $this->container->get(Session::class)->flash('success', 'Plantilla eliminada.');
        }

        return Response::redirect('/admin/plantillas');
    }
}
