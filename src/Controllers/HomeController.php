<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;

final class HomeController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function admin(Request $request): Response
    {
        $view = $this->container->get(View::class);

        return Response::html($view->render('admin/dashboard', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Panel administrativo',
        ]));
    }

    public function cliente(Request $request): Response
    {
        $view = $this->container->get(View::class);

        return Response::html($view->render('cliente/dashboard', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Mi dashboard',
        ]));
    }

    public function raiz(Request $request): Response
    {
        return Response::redirect('/login');
    }
}
