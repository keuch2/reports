<?php

declare(strict_types=1);

namespace MisterCo\Reports\Middleware;

use Closure;
use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Services\AuthService;

final class AuthMiddleware
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

        $request->attributes['usuario'] = $usuario;

        return $next($request);
    }
}
