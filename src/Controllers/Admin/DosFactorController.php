<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Services\TwoFactorService;

final class DosFactorController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $service = $this->container->get(TwoFactorService::class);
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html($view->render('admin/2fa', [
            'usuario' => $usuario,
            'titulo' => 'Autenticación en 2 pasos',
            'habilitado' => $service->estaHabilitado($usuario->id),
            'enrolamiento' => $session->getFlash('enrolamiento'),
            'backup_codes' => $session->getFlash('backup_codes'),
            'success' => $session->getFlash('success'),
            'error' => $session->getFlash('error'),
        ]));
    }

    public function iniciar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $session = $this->container->get(Session::class);

        $enrol = $this->container->get(TwoFactorService::class)
            ->iniciarEnrolamiento($usuario->id, $usuario->correo);

        $session->flash('enrolamiento', $enrol);

        return Response::redirect('/admin/2fa');
    }

    public function confirmar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $codigo = (string) $request->input('codigo', '');
        $session = $this->container->get(Session::class);

        $codes = $this->container->get(TwoFactorService::class)
            ->confirmarEnrolamiento($usuario->id, $codigo);

        if ($codes === null) {
            $session->flash('error', 'Código inválido. Asegurate de tener el reloj sincronizado.');

            return Response::redirect('/admin/2fa');
        }

        $session->flash('success', '2FA habilitado. Guardá tus códigos de backup en un lugar seguro.');
        $session->flash('backup_codes', $codes);

        return Response::redirect('/admin/2fa');
    }

    public function deshabilitar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $this->container->get(TwoFactorService::class)->deshabilitar($usuario->id);
        $this->container->get(Session::class)->flash('success', '2FA deshabilitado.');

        return Response::redirect('/admin/2fa');
    }
}
