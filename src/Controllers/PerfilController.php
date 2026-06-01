<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers;

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
 * "Mi perfil" para admin y cliente: editar nombre/correo + cambiar contraseña.
 * Funciona indistinto del rol. La vista usa el header del rol correspondiente.
 */
final class PerfilController
{
    private const ARGON_OPTIONS = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];

    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $repo = $this->container->get(UsuarioRepository::class);
        $row = $repo->buscarPorId($usuario->id);

        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('perfil/mi_perfil', [
            'usuario' => $usuario,
            'titulo' => 'Mi perfil',
            'datos' => $row,
            'errores_perfil' => $session->getFlash('errores_perfil', []),
            'errores_pass' => $session->getFlash('errores_pass', []),
            'success' => $session->getFlash('success'),
        ]));
    }

    public function actualizarPerfil(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(UsuarioRepository::class);

        $nombre = trim((string) $request->input('nombre_completo', ''));
        $correo = trim((string) $request->input('correo', ''));

        $errores = [];
        if ($nombre === '') {
            $errores[] = 'El nombre no puede estar vacío.';
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo no es válido.';
        }
        if ($errores === [] && $repo->correoExiste($correo, $usuario->id)) {
            $errores[] = 'Ya hay otra cuenta con ese correo.';
        }

        if ($errores !== []) {
            $session->flash('errores_perfil', $errores);

            return Response::redirect('/mi-perfil');
        }

        $repo->actualizarPerfil($usuario->id, $nombre, $correo);
        $this->container->get(AuditService::class)->registrar(
            'perfil.actualizado', $usuario, $request->ip, $request->userAgent,
            'usuario', (string) $usuario->id, ['nombre' => $nombre, 'correo' => $correo]
        );
        $session->flash('success', 'Perfil actualizado.');

        return Response::redirect('/mi-perfil');
    }

    public function cambiarPassword(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $session = $this->container->get(Session::class);
        $repo = $this->container->get(UsuarioRepository::class);

        $actual = (string) $request->input('password_actual', '');
        $nueva = (string) $request->input('password_nueva', '');
        $confirmacion = (string) $request->input('password_confirmacion', '');

        $errores = [];
        if (!$repo->verificarPasswordActual($usuario->id, $actual)) {
            $errores[] = 'La contraseña actual no es correcta.';
        }
        if ($nueva !== $confirmacion) {
            $errores[] = 'La nueva contraseña y su confirmación no coinciden.';
        }
        if ($errores === []) {
            $erroresPolicy = $this->container->get(PasswordPolicyService::class)->validar($nueva);
            $errores = array_merge($errores, $erroresPolicy);
        }

        if ($errores !== []) {
            $session->flash('errores_pass', $errores);

            return Response::redirect('/mi-perfil');
        }

        $repo->actualizarPassword(
            $usuario->id,
            password_hash($nueva, PASSWORD_ARGON2ID, self::ARGON_OPTIONS)
        );
        $this->container->get(AuditService::class)->registrar(
            'perfil.password_cambiada', $usuario, $request->ip, $request->userAgent,
            'usuario', (string) $usuario->id
        );
        $session->flash('success', 'Contraseña actualizada. Tu sesión sigue activa.');

        return Response::redirect('/mi-perfil');
    }
}
