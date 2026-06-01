<?php

declare(strict_types=1);

use MisterCo\Reports\Controllers\Admin\AuditoriaController;
use MisterCo\Reports\Controllers\Admin\ClienteController as AdminClienteController;
use MisterCo\Reports\Controllers\Admin\DosFactorController;
use MisterCo\Reports\Controllers\Admin\ImportacionController;
use MisterCo\Reports\Controllers\Admin\ImportacionHistoricoController;
use MisterCo\Reports\Controllers\Admin\MetaConexionController;
use MisterCo\Reports\Controllers\Admin\PermisosController;
use MisterCo\Reports\Controllers\Admin\PlantillaPdfController;
use MisterCo\Reports\Controllers\Admin\UsuarioAdminController;
use MisterCo\Reports\Controllers\AuthController;
use MisterCo\Reports\Controllers\PerfilController;
use MisterCo\Reports\Controllers\Cliente\CampaniaController as ClienteCampaniaController;
use MisterCo\Reports\Controllers\Cliente\DashboardController as ClienteDashboardController;
use MisterCo\Reports\Controllers\Cliente\PreferenciasController as ClientePreferenciasController;
use MisterCo\Reports\Controllers\Cliente\ReporteController as ClienteReporteController;
use MisterCo\Reports\Controllers\HomeController;
use MisterCo\Reports\Controllers\PasswordResetController;
use MisterCo\Reports\Core\Router;
use MisterCo\Reports\Middleware\AdminMiddleware;
use MisterCo\Reports\Middleware\AuthMiddleware;
use MisterCo\Reports\Middleware\ClienteMiddleware;
use MisterCo\Reports\Middleware\CsrfMiddleware;

return function (Router $router): void {
    $router->get('/', [HomeController::class, 'raiz']);

    $router->get('/login', [AuthController::class, 'mostrarLogin']);
    $router->post('/login', [AuthController::class, 'procesarLogin'], [CsrfMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout'], [CsrfMiddleware::class]);

    // Recuperación de contraseña (público).
    $router->get('/password/solicitar', [PasswordResetController::class, 'mostrarSolicitud']);
    $router->post('/password/solicitar', [PasswordResetController::class, 'procesarSolicitud'], [CsrfMiddleware::class]);
    $router->get('/password/reset', [PasswordResetController::class, 'mostrarReset']);
    $router->post('/password/reset', [PasswordResetController::class, 'procesarReset'], [CsrfMiddleware::class]);

    // 2FA step (entre login y autenticado).
    $router->get('/2fa', [AuthController::class, 'mostrar2fa']);
    $router->post('/2fa', [AuthController::class, 'procesar2fa'], [CsrfMiddleware::class]);

    // Mi perfil (cualquier rol logueado)
    $router->get('/mi-perfil', [PerfilController::class, 'mostrar'], [AuthMiddleware::class]);
    $router->post('/mi-perfil', [PerfilController::class, 'actualizarPerfil'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/mi-perfil/password', [PerfilController::class, 'cambiarPassword'], [AuthMiddleware::class, CsrfMiddleware::class]);

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

    // Permisos granulares por cliente
    $router->get('/admin/clientes/{id}/permisos', [PermisosController::class, 'mostrar'], $admin);
    $router->post('/admin/clientes/{id}/permisos/campanias', [PermisosController::class, 'guardarCampanias'], $adminCsrf);
    $router->post('/admin/clientes/{id}/permisos/anuncios', [PermisosController::class, 'guardarAnuncios'], $adminCsrf);
    $router->post('/admin/clientes/{id}/permisos/metricas', [PermisosController::class, 'guardarMetricas'], $adminCsrf);

    // Plantillas PDF
    $router->get('/admin/plantillas', [PlantillaPdfController::class, 'listar'], $admin);
    $router->get('/admin/plantillas/nueva', [PlantillaPdfController::class, 'mostrarFormulario'], $admin);
    $router->post('/admin/plantillas', [PlantillaPdfController::class, 'guardar'], $adminCsrf);
    $router->get('/admin/plantillas/{id}/editar', [PlantillaPdfController::class, 'mostrarFormulario'], $admin);
    $router->post('/admin/plantillas/{id}', [PlantillaPdfController::class, 'guardar'], $adminCsrf);
    $router->post('/admin/plantillas/{id}/eliminar', [PlantillaPdfController::class, 'eliminar'], $adminCsrf);

    // Auditoría y operación
    $router->get('/admin/auditoria', [AuditoriaController::class, 'listar'], $admin);
    $router->get('/admin/importaciones', [ImportacionHistoricoController::class, 'listar'], $admin);

    // 2FA admin
    $router->get('/admin/2fa', [DosFactorController::class, 'mostrar'], $admin);
    $router->post('/admin/2fa/iniciar', [DosFactorController::class, 'iniciar'], $adminCsrf);
    $router->post('/admin/2fa/confirmar', [DosFactorController::class, 'confirmar'], $adminCsrf);
    $router->post('/admin/2fa/deshabilitar', [DosFactorController::class, 'deshabilitar'], $adminCsrf);

    // CRUD de usuarios admin
    $router->get('/admin/usuarios', [UsuarioAdminController::class, 'listar'], $admin);
    $router->get('/admin/usuarios/nuevo', [UsuarioAdminController::class, 'mostrarNuevo'], $admin);
    $router->post('/admin/usuarios', [UsuarioAdminController::class, 'crear'], $adminCsrf);
    $router->post('/admin/usuarios/{id}/activar', [UsuarioAdminController::class, 'activar'], $adminCsrf);
    $router->post('/admin/usuarios/{id}/desactivar', [UsuarioAdminController::class, 'desactivar'], $adminCsrf);

    // --- Cliente ---
    $cliente = [ClienteMiddleware::class];
    $clienteCsrf = [ClienteMiddleware::class, CsrfMiddleware::class];

    $router->get('/cliente', [ClienteDashboardController::class, 'index'], $cliente);
    $router->get('/cliente/campanias/{id}', [ClienteCampaniaController::class, 'detalle'], $cliente);
    $router->get('/cliente/preferencias', [ClientePreferenciasController::class, 'mostrar'], $cliente);
    $router->post('/cliente/preferencias', [ClientePreferenciasController::class, 'guardar'], $clienteCsrf);
    $router->get('/cliente/reporte/previa', [ClienteReporteController::class, 'previa'], $cliente);
    $router->post('/cliente/reporte.pdf', [ClienteReporteController::class, 'descargar'], $clienteCsrf);
};
