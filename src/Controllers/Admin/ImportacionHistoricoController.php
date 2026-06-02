<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\ImportacionRepository;
use MisterCo\Reports\Repositories\MetricaSnapshotRepository;
use MisterCo\Reports\Services\AuditService;

final class ImportacionHistoricoController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function listar(Request $request): Response
    {
        $repo = $this->container->get(ImportacionRepository::class);
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('admin/importaciones_historico', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Histórico de importaciones',
            'importaciones' => $repo->recientes(100),
            'success' => $session->getFlash('success'),
            'error' => $session->getFlash('error'),
        ]));
    }

    /**
     * Borra los snapshots de una importación. Las entidades (campañas, adsets,
     * anuncios) se preservan porque pueden estar referenciadas por otras
     * importaciones y por las asignaciones de los clientes. Después de borrar,
     * volver a importar el mismo rango re-puebla los datos limpios.
     */
    public function borrarDatos(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $id = (int) ($request->attributes['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(ImportacionRepository::class);

        $imp = $repo->buscarPorId($id);
        if ($imp === null) {
            $session->flash('error', 'Importación no encontrada.');

            return Response::redirect('/admin/importaciones');
        }

        $borradas = $this->container->get(MetricaSnapshotRepository::class)
            ->borrarDeImportacion($id);
        $repo->marcarSnapshotsBorrados($id);

        $this->container->get(AuditService::class)->registrar(
            'importacion.snapshots_borrados', $usuario, $request->ip, $request->userAgent,
            'importacion', (string) $id,
            ['cuenta_id' => $imp['cuenta_publicitaria_id'], 'rango' => $imp['rango_inicio'] . ' a ' . $imp['rango_fin'], 'filas_borradas' => $borradas]
        );

        $session->flash('success', "Snapshots eliminados ({$borradas} filas). Las campañas/anuncios siguen disponibles; volvé a importar el mismo rango para repoblar.");

        return Response::redirect('/admin/importaciones');
    }
}
