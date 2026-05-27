<?php

declare(strict_types=1);

namespace MisterCo\Reports\Core;

use Dotenv\Dotenv;
use MisterCo\Reports\Services\AuthService;
use Throwable;

final class Application
{
    private function __construct(
        public readonly string $basePath,
        public readonly Container $container,
        public readonly Router $router,
    ) {
    }

    public static function bootstrap(string $basePath): self
    {
        Dotenv::createImmutable($basePath)->safeLoad();

        $appConfig = require $basePath . '/config/app.php';
        $dbConfig = require $basePath . '/config/database.php';

        self::configureErrors((bool) $appConfig['debug'], $basePath);

        $container = new Container();
        $router = new Router();

        $container->instance('config.app', (object) $appConfig);
        $container->instance('config.db', (object) $dbConfig);
        $container->instance(Router::class, $router);

        $container->bind(Database::class, fn () => new Database($dbConfig));
        $container->bind(Session::class, fn () => new Session(
            (int) $appConfig['session_lifetime_minutes'],
            (bool) $appConfig['session_secure_cookie']
        ));
        $container->bind(View::class, fn (Container $c) => new View(
            $basePath . '/templates',
            $c->get(Session::class),
        ));
        $container->bind(AuthService::class, fn (Container $c) => new AuthService(
            $c->get(Database::class),
            $c->get(Session::class),
        ));

        // Forzar inicio de sesión temprano.
        $container->get(Session::class);

        $app = new self($basePath, $container, $router);

        $routesFile = $basePath . '/config/routes.php';
        if (is_file($routesFile)) {
            (require $routesFile)($router);
        }

        return $app;
    }

    public function run(): void
    {
        $request = Request::fromGlobals();

        try {
            $response = $this->router->dispatch($request, $this->container);
        } catch (Throwable $e) {
            $response = $this->handleException($e);
        }

        $response->send();
    }

    private function handleException(Throwable $e): Response
    {
        $config = $this->container->get('config.app');
        $debug = $config->debug ?? false;

        $message = $debug
            ? sprintf('<pre>%s</pre>', htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8'))
            : '<h1>500 — Error interno</h1><p>Algo salió mal. El equipo fue notificado.</p>';

        error_log('[' . date('c') . '] ' . $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        return Response::html('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem">' . $message . '</body></html>', 500);
    }

    private static function configureErrors(bool $debug, string $basePath): void
    {
        $logFile = $basePath . '/storage/logs/php-' . date('Y-m-d') . '.log';
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        if ($debug) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        }
    }
}
