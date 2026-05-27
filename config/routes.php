<?php

declare(strict_types=1);

use MisterCo\Reports\Controllers\AuthController;
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

    $router->get('/admin', [HomeController::class, 'admin'], [AdminMiddleware::class]);

    $router->get('/cliente', [HomeController::class, 'cliente'], [ClienteMiddleware::class]);
};
