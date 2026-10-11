<?php

declare(strict_types=1);

namespace MisterCo\Reports\Middleware;

use Closure;
use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Services\AuthService;

final class ClienteMiddleware
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $auth = $this->container->get(AuthService::class);
        $usuario = $auth->usuarioActual();

        if ($usuario === null) {
            return Response::redirect('/login');
        }

        if (!$usuario->esCliente()) {
            return Response::html('<h1>403 — Acceso denegado</h1>', 403);
        }

        $request->attributes['usuario'] = $usuario;
        // El header del cliente muestra solo las fuentes que tiene asignadas.
        $this->container->get(View::class)->compartir(
            'fuentes_cliente',
            $this->container->get(ClienteRepository::class)->fuentesAsignadas((int) $usuario->clienteId)
        );

        return $next($request);
    }
}
