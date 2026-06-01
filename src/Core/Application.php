<?php

declare(strict_types=1);

namespace MisterCo\Reports\Core;

use Dotenv\Dotenv;
use MisterCo\Reports\Repositories\ClienteRepository;
use MisterCo\Reports\Repositories\ConfiguracionRepository;
use MisterCo\Reports\Repositories\CuentaPublicitariaRepository;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Repositories\ImportacionRepository;
use MisterCo\Reports\Repositories\MetricaCatalogoRepository;
use MisterCo\Reports\Repositories\MetricaSnapshotRepository;
use MisterCo\Reports\Repositories\PlantillaPdfRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\AuthService;
use MisterCo\Reports\Services\DashboardPreferenciasService;
use MisterCo\Reports\Services\DashboardService;
use MisterCo\Reports\Services\Meta\ImportacionService;
use MisterCo\Reports\Services\Meta\MetaTokenService;
use MisterCo\Reports\Services\PasswordPolicyService;
use MisterCo\Reports\Services\PasswordResetService;
use MisterCo\Reports\Services\PermisosService;
use MisterCo\Reports\Services\ReportePdfService;
use MisterCo\Reports\Services\TotpService;
use MisterCo\Reports\Services\TwoFactorService;
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
        $pathPrefix = (string) ($appConfig['path_prefix'] ?? '');
        Response::setPathPrefix($pathPrefix);
        $container->instance('app.path_prefix', (object) ['value' => $pathPrefix]);
        $container->bind(View::class, fn (Container $c) => new View(
            $basePath . '/templates',
            $c->get(Session::class),
            $pathPrefix,
        ));
        $container->bind(AuditService::class, fn (Container $c) => new AuditService($c->get(Database::class)));
        $container->bind(AuthService::class, fn (Container $c) => new AuthService(
            $c->get(Database::class),
            $c->get(Session::class),
            $c->get(AuditService::class),
        ));
        $container->bind(Encryptor::class, fn () => new Encryptor((string) ($appConfig['key'] ?? '')));
        $container->bind(ConfiguracionRepository::class, fn (Container $c) => new ConfiguracionRepository(
            $c->get(Database::class),
            $c->get(Encryptor::class),
        ));
        $container->bind(MetaTokenService::class, fn (Container $c) => new MetaTokenService(
            $c->get(ConfiguracionRepository::class),
            (string) ($_ENV['META_API_VERSION'] ?? 'v20.0'),
        ));

        // Repositorios
        $container->bind(CuentaPublicitariaRepository::class, fn (Container $c) => new CuentaPublicitariaRepository($c->get(Database::class)));
        $container->bind(ClienteRepository::class, fn (Container $c) => new ClienteRepository($c->get(Database::class)));
        $container->bind(EntidadesMetaRepository::class, fn (Container $c) => new EntidadesMetaRepository($c->get(Database::class)));
        $container->bind(MetricaSnapshotRepository::class, fn (Container $c) => new MetricaSnapshotRepository($c->get(Database::class)));
        $container->bind(ImportacionRepository::class, fn (Container $c) => new ImportacionRepository($c->get(Database::class)));
        $container->bind(MetricaCatalogoRepository::class, fn (Container $c) => new MetricaCatalogoRepository($c->get(Database::class)));
        $container->bind(PlantillaPdfRepository::class, fn (Container $c) => new PlantillaPdfRepository($c->get(Database::class)));

        // Servicios de dominio
        $container->bind(PermisosService::class, fn (Container $c) => new PermisosService($c->get(Database::class)));
        $container->bind(ImportacionService::class, fn (Container $c) => new ImportacionService(
            $c->get(MetaTokenService::class),
            $c->get(CuentaPublicitariaRepository::class),
            $c->get(EntidadesMetaRepository::class),
            $c->get(MetricaSnapshotRepository::class),
            $c->get(ImportacionRepository::class),
        ));
        $container->bind(DashboardService::class, fn (Container $c) => new DashboardService(
            $c->get(Database::class),
            $c->get(PermisosService::class),
        ));
        $container->bind(DashboardPreferenciasService::class, fn (Container $c) => new DashboardPreferenciasService($c->get(Database::class)));
        $container->bind(ReportePdfService::class, fn (Container $c) => new ReportePdfService(
            $c->get(View::class),
            $c->get(DashboardService::class),
            $c->get(Database::class),
            $basePath . '/storage/reportes',
        ));

        // Fase 3
        $container->bind(PasswordPolicyService::class, fn () => new PasswordPolicyService());
        $container->bind(PasswordResetService::class, fn (Container $c) => new PasswordResetService(
            $c->get(Database::class),
            $c->get(PasswordPolicyService::class),
            $c->get(AuditService::class),
            [
                'host' => $_ENV['SMTP_HOST'] ?? '',
                'port' => (int) ($_ENV['SMTP_PORT'] ?? 587),
                'username' => $_ENV['SMTP_USERNAME'] ?? '',
                'password' => $_ENV['SMTP_PASSWORD'] ?? '',
                'from_address' => $_ENV['SMTP_FROM_ADDRESS'] ?? '',
                'from_name' => $_ENV['SMTP_FROM_NAME'] ?? 'Mister Co. Reports',
            ],
            (string) ($appConfig['url'] ?? 'http://localhost'),
        ));
        $container->bind(TotpService::class, fn () => new TotpService());
        $container->bind(TwoFactorService::class, fn (Container $c) => new TwoFactorService(
            $c->get(Database::class),
            $c->get(Encryptor::class),
            $c->get(TotpService::class),
            $c->get(AuditService::class),
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
        $prefix = (string) ($this->container->get('app.path_prefix')->value ?? '');
        $request = Request::fromGlobals($prefix);

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
