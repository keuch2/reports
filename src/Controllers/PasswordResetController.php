<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Services\PasswordResetService;

final class PasswordResetController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mostrarSolicitud(Request $request): Response
    {
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('auth/password_solicitar', [
            'success' => $session->getFlash('success'),
            'error' => $session->getFlash('error'),
        ], 'layouts/auth'));
    }

    public function procesarSolicitud(Request $request): Response
    {
        $correo = trim((string) $request->input('correo', ''));
        $session = $this->container->get(Session::class);

        if ($correo !== '') {
            $this->container->get(PasswordResetService::class)
                ->solicitar($correo, $request->ip);
        }

        // No revelamos si existe — siempre el mismo mensaje.
        $session->flash('success', 'Si el correo existe en el sistema, te enviamos un enlace para recuperar tu contraseña.');

        return Response::redirect('/password/solicitar');
    }

    public function mostrarReset(Request $request): Response
    {
        $token = (string) ($request->query['token'] ?? '');
        $service = $this->container->get(PasswordResetService::class);
        $row = $service->verificarToken($token);

        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('auth/password_reset', [
            'token' => $token,
            'token_valido' => $row !== null,
            'usuario_correo' => $row['correo'] ?? null,
            'errores' => $session->getFlash('errores', []),
        ], 'layouts/auth'));
    }

    public function procesarReset(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');
        $confirmacion = (string) $request->input('password_confirmacion', '');
        $session = $this->container->get(Session::class);

        if ($password !== $confirmacion) {
            $session->flash('errores', ['Las contraseñas no coinciden.']);

            return Response::redirect('/password/reset?token=' . urlencode($token));
        }

        $errores = $this->container->get(PasswordResetService::class)
            ->consumirYActualizar($token, $password, $request->ip);

        if ($errores !== []) {
            $session->flash('errores', $errores);

            return Response::redirect('/password/reset?token=' . urlencode($token));
        }

        $session->flash('success', 'Contraseña actualizada. Ya podés ingresar con la nueva.');

        return Response::redirect('/login');
    }
}
