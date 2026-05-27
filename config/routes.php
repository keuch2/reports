<?php

declare(strict_types=1);

use MisterCo\Reports\Controllers\Admin\ClienteController as AdminClienteController;
use MisterCo\Reports\Controllers\Admin\ImportacionController;
use MisterCo\Reports\Controllers\Admin\MetaConexionController;
use MisterCo\Reports\Controllers\AuthController;
use MisterCo\Reports\Controllers\Cliente\DashboardController as ClienteDashboardController;
use MisterCo\Reports\Controllers\Cliente\ReporteController as ClienteReporteController;
use MisterCo\Reports\Controllers\HomeController;
use MisterCo\Reports\Core\Router;
use MisterCo\Reports\Middleware\AdminMiddleware;
use MisterCo\Reports\Middleware\ClienteMiddleware;
use MisterCo\Reports\Middleware\CsrfMiddleware;

return function (Router $router): void {
    $router->get('/', [HomeController::class, 'raiz']);

    $router->get('/login', [AuthController::class, 'mostrarLogin']);
    $router->post('/login', [AuthController::class, 'procesarLogin'], [CsrfMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout'], [CsrfMiddleware::class]);

    // --- Admin ---
    $admin = [AdminMiddleware::class];
    $adminCsrf = [AdminMiddleware::class, CsrfMiddleware::class];

    $router->get('/admin', [HomeController::class, 'admin'], $admin);

    $router->get('/admin/meta', [MetaConexionController::class, 'mostrar'], $admin);
    $router->post('/admin/meta/conectar', [MetaConexionController::class, 'conectar'], $adminCsrf);
    $router->post('/admin/meta/desconectar', [MetaConexionController::class, 'desconectar'], $adminCsrf);

    $router->get('/admin/importar', [ImportacionController::class, 'mostrar'], $admin);
    $router->post('/admin/importar', [ImportacionController::class, 'ejecutar'], $adminCsrf);

    $router->get('/admin/clientes', [AdminClienteController::class, 'listar'], $admin);
    $router->get('/admin/clientes/nuevo', [AdminClienteController::class, 'mostrarNuevo'], $admin);
    $router->post('/admin/clientes', [AdminClienteController::class, 'crear'], $adminCsrf);
    $router->get('/admin/clientes/{id}', [AdminClienteController::class, 'detalle'], $admin);
    $router->post('/admin/clientes/{id}/asignar', [AdminClienteController::class, 'asignarCuenta'], $adminCsrf);
    $router->post('/admin/clientes/{id}/desasignar', [AdminClienteController::class, 'desasignarCuenta'], $adminCsrf);

    // --- Cliente ---
    $cliente = [ClienteMiddleware::class];

    $router->get('/cliente', [ClienteDashboardController::class, 'index'], $cliente);
    $router->get('/cliente/reporte.pdf', [ClienteReporteController::class, 'descargar'], $cliente);
};
