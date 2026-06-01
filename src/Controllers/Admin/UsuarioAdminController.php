<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\UsuarioRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\PasswordPolicyService;

/**
 * CRUD de usuarios con rol 'admin' (no toca clientes, esos viven en ClienteController).
 *
 * Reglas:
 * - Un admin no puede desactivarse a sí mismo (evita lockout).
 * - Siempre debe quedar al menos un admin activo en el sistema.
 * - No hay "delete" físico — sólo activar/desactivar (preserva auditoría).
 */
final class UsuarioAdminController
{
    private const ARGON_OPTIONS = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];

    public function __construct(private readonly Container $container)
    {
    }

    public function listar(Request $request): Response
    {
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(UsuarioRepository::class);

        return Response::html($view->render('admin/usuarios_lista', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Usuarios admin',
            'admins' => $repo->listarAdmins(),
            'success' => $session->getFlash('success'),
            'error' => $session->getFlash('error'),
        ]));
    }

    public function mostrarNuevo(Request $request): Response
    {
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('admin/usuarios_nuevo', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Nuevo admin',
            'errores' => $session->getFlash('errores', []),
            'form' => $session->getFlash('form', []),
        ]));
    }

    public function crear(Request $request): Response
    {
        /** @var Usuario $admin */
        $admin = $request->attributes['usuario'];
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(UsuarioRepository::class);

        $nombre = trim((string) $request->input('nombre_completo', ''));
        $correo = trim((string) $request->input('correo', ''));
        $password = (string) $request->input('password', '');

        $errores = [];
        if ($nombre === '') {
            $errores[] = 'El nombre no puede estar vacío.';
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo no es válido.';
        }
        if ($errores === [] && $repo->correoExiste($correo)) {
            $errores[] = 'Ya hay una cuenta con ese correo.';
        }
        if ($errores === []) {
            $erroresPolicy = $this->container->get(PasswordPolicyService::class)->validar($password);
            $errores = array_merge($errores, $erroresPolicy);
        }

        if ($errores !== []) {
            $session->flash('errores', $errores);
            $session->flash('form', ['nombre_completo' => $nombre, 'correo' => $correo]);

            return Response::redirect('/admin/usuarios/nuevo');
        }

        $nuevoId = $repo->crearAdmin(
            $correo,
            $nombre,
            password_hash($password, PASSWORD_ARGON2ID, self::ARGON_OPTIONS)
        );

        $this->container->get(AuditService::class)->registrar(
            'admin.creado', $admin, $request->ip, $request->userAgent,
            'usuario', (string) $nuevoId, ['nombre' => $nombre, 'correo' => $correo]
        );

        $session->flash('success', "Admin {$correo} creado.");

        return Response::redirect('/admin/usuarios');
    }

    public function activar(Request $request): Response
    {
        return $this->cambiarEstado($request, true);
    }

    public function desactivar(Request $request): Response
    {
        return $this->cambiarEstado($request, false);
    }

    private function cambiarEstado(Request $request, bool $activar): Response
    {
        /** @var Usuario $admin */
        $admin = $request->attributes['usuario'];
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(UsuarioRepository::class);
        $id = (int) ($request->attributes['id'] ?? 0);

        if ($id <= 0) {
            return Response::redirect('/admin/usuarios');
        }

        $target = $repo->buscarPorId($id);
        if ($target === null || $target['rol'] !== 'admin') {
            $session->flash('error', 'Admin no encontrado.');

            return Response::redirect('/admin/usuarios');
        }

        if ($id === $admin->id) {
            $session->flash('error', 'No podés cambiar el estado de tu propia cuenta.');

            return Response::redirect('/admin/usuarios');
        }

        if (!$activar && $repo->cuentaAdminsActivos() <= 1) {
            $session->flash('error', 'No podés desactivar al último admin activo.');

            return Response::redirect('/admin/usuarios');
        }

        $repo->setActivo($id, $activar);
        $this->container->get(AuditService::class)->registrar(
            $activar ? 'admin.activado' : 'admin.desactivado',
            $admin, $request->ip, $request->userAgent,
            'usuario', (string) $id, ['correo' => $target['correo']]
        );
        $session->flash('success', $activar ? "Admin {$target['correo']} activado." : "Admin {$target['correo']} desactivado.");

        return Response::redirect('/admin/usuarios');
    }
}
