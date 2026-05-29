<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Services\AuthService;
use MisterCo\Reports\Services\TwoFactorService;

final class AuthController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mostrarLogin(Request $request): Response
    {
        $auth = $this->container->get(AuthService::class);
        if ($auth->usuarioActual() !== null) {
            return $this->redirectToHome($auth);
        }

        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('auth/login', [
            'error' => $session->getFlash('error'),
            'correo' => $session->getFlash('correo', ''),
        ], 'layouts/auth'));
    }

    public function procesarLogin(Request $request): Response
    {
        $correo = trim((string) $request->input('correo', ''));
        $password = (string) $request->input('password', '');
        $session = $this->container->get(Session::class);

        if ($correo === '' || $password === '') {
            $session->flash('error', 'Ingresá correo y contraseña.');
            $session->flash('correo', $correo);

            return Response::redirect('/login');
        }

        $auth = $this->container->get(AuthService::class);
        $usuario = $auth->intentarLogin($correo, $password, $request->ip, $request->userAgent);

        if ($usuario === null) {
            $session->flash('error', 'Credenciales inválidas o cuenta bloqueada temporalmente.');
            $session->flash('correo', $correo);

            return Response::redirect('/login');
        }

        // Si tiene 2FA activo, marcamos sesión como "pendiente de 2FA" y derivamos.
        $twoFa = $this->container->get(TwoFactorService::class);
        if ($twoFa->estaHabilitado($usuario->id)) {
            $session->set('2fa_pendiente_usuario_id', $usuario->id);
            // Limpiamos las claves que marcan sesión como autenticada hasta validar el código.
            $session->forget('usuario_id');
            $session->forget('usuario_rol');
            $session->forget('usuario_cliente_id');

            return Response::redirect('/2fa');
        }

        return $this->redirectToHome($auth);
    }

    public function mostrar2fa(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        if (!$session->has('2fa_pendiente_usuario_id')) {
            return Response::redirect('/login');
        }
        $view = $this->container->get(View::class);

        return Response::html($view->render('auth/2fa_login', [
            'error' => $session->getFlash('error'),
        ], 'layouts/auth'));
    }

    public function procesar2fa(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        $usuarioId = (int) $session->get('2fa_pendiente_usuario_id', 0);
        if ($usuarioId === 0) {
            return Response::redirect('/login');
        }

        $codigo = (string) $request->input('codigo', '');
        $twoFa = $this->container->get(TwoFactorService::class);

        if (!$twoFa->verificarLogin($usuarioId, $codigo)) {
            $session->flash('error', 'Código inválido. Probá de nuevo.');

            return Response::redirect('/2fa');
        }

        // Completar autenticación.
        $auth = $this->container->get(AuthService::class);
        $usuario = $auth->forzarLoginPorId($usuarioId);
        $session->forget('2fa_pendiente_usuario_id');

        if ($usuario === null) {
            return Response::redirect('/login');
        }

        return $this->redirectToHome($auth);
    }

    public function logout(Request $request): Response
    {
        $this->container->get(AuthService::class)->logout();

        return Response::redirect('/login');
    }

    private function redirectToHome(AuthService $auth): Response
    {
        $usuario = $auth->usuarioActual();
        if ($usuario === null) {
            return Response::redirect('/login');
        }

        return Response::redirect($usuario->esAdmin() ? '/admin' : '/cliente');
    }
}
