<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Repositories\ImportacionRepository;

final class ImportacionHistoricoController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function listar(Request $request): Response
    {
        $repo = $this->container->get(ImportacionRepository::class);
        $view = $this->container->get(View::class);

        return Response::html($view->render('admin/importaciones_historico', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Histórico de importaciones',
            'importaciones' => $repo->recientes(100),
        ]));
    }
}
