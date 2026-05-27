<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Services\AuthService;

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
