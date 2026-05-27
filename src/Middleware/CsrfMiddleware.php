<?php

declare(strict_types=1);

namespace MisterCo\Reports\Middleware;

use Closure;
use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;

final class CsrfMiddleware
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $session = $this->container->get(Session::class);
            $tokenEnviado = (string) ($request->post['_csrf'] ?? $request->header('x-csrf-token') ?? '');
            $tokenSesion = $session->csrfToken();

            if ($tokenEnviado === '' || !hash_equals($tokenSesion, $tokenEnviado)) {
                return Response::html('<h1>419 — Token CSRF inválido o expirado</h1>', 419);
            }
        }

        return $next($request);
    }
}
