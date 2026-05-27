<?php

declare(strict_types=1);

namespace MisterCo\Reports\Core;

use Closure;
use RuntimeException;

/**
 * Router con parámetros nombrados ({slug}) y middlewares en cadena.
 */
final class Router
{
    /** @var list<array{method:string,pattern:string,regex:string,params:list<string>,handler:array{0:string,1:string},middlewares:list<string>}> */
    private array $routes = [];

    /**
     * @param array{0:string,1:string} $handler [ControllerClass::class, 'method']
     * @param list<string> $middlewares
     */
    public function add(string $method, string $pattern, array $handler, array $middlewares = []): void
    {
        $params = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $m) use (&$params): string {
                $params[] = $m[1];

                return '([^/]+)';
            },
            $pattern
        );

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    /** @param list<string> $middlewares */
    public function get(string $pattern, array $handler, array $middlewares = []): void
    {
        $this->add('GET', $pattern, $handler, $middlewares);
    }

    /** @param list<string> $middlewares */
    public function post(string $pattern, array $handler, array $middlewares = []): void
    {
        $this->add('POST', $pattern, $handler, $middlewares);
    }

    public function dispatch(Request $request, Container $container): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }

            array_shift($matches);
            foreach ($route['params'] as $i => $name) {
                $request->attributes[$name] = $matches[$i] ?? null;
            }

            $next = function (Request $req) use ($route, $container): Response {
                [$controllerClass, $method] = $route['handler'];

                if (!$container->has($controllerClass)) {
                    $container->bind($controllerClass, fn (Container $c): object => new $controllerClass($c));
                }

                $controller = $container->get($controllerClass);

                return $controller->{$method}($req);
            };

            foreach (array_reverse($route['middlewares']) as $middlewareClass) {
                if (!$container->has($middlewareClass)) {
                    $container->bind($middlewareClass, fn (Container $c): object => new $middlewareClass($c));
                }
                $middleware = $container->get($middlewareClass);
                $current = $next;
                $next = static fn (Request $req): Response => $middleware->handle($req, $current);
            }

            return $next($request);
        }

        return Response::html($this->renderNotFound(), 404);
    }

    private function renderNotFound(): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>404</title></head>'
            . '<body style="font-family:sans-serif;padding:2rem"><h1>404 — Página no encontrada</h1></body></html>';
    }
}
