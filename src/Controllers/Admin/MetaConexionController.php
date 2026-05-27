<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\CuentaPublicitariaRepository;
use MisterCo\Reports\Services\Meta\MetaApiException;
use MisterCo\Reports\Services\Meta\MetaTokenService;

final class MetaConexionController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);
        $tokenService = $this->container->get(MetaTokenService::class);
        $cuentasRepo = $this->container->get(CuentaPublicitariaRepository::class);

        return Response::html($view->render('admin/meta_conexion', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Conectar cuenta Meta',
            'tiene_token' => $tokenService->tieneToken(),
            'cuentas' => $cuentasRepo->listarTodas(),
            'error' => $session->getFlash('error'),
            'success' => $session->getFlash('success'),
        ]));
    }

    public function conectar(Request $request): Response
    {
        $token = trim((string) $request->input('token', ''));
        $session = $this->container->get(Session::class);
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        if ($token === '') {
            $session->flash('error', 'Pegá un token válido de System User.');

            return Response::redirect('/admin/meta');
        }

        $tokenService = $this->container->get(MetaTokenService::class);
        $cuentasRepo = $this->container->get(CuentaPublicitariaRepository::class);

        try {
            $cliente = $tokenService->clienteCon($token);
            $cuentasMeta = $cliente->validarTokenYListarCuentas();
        } catch (MetaApiException $e) {
            $session->flash('error', 'Token inválido o sin permisos: ' . $e->getMessage());

            return Response::redirect('/admin/meta');
        }

        $tokenService->guardarToken($token, $usuario->id);

        $importadas = 0;
        foreach ($cuentasMeta as $c) {
            $cuentasRepo->upsert(
                metaAccountId: (string) ($c['account_id'] ?? str_replace('act_', '', (string) ($c['id'] ?? ''))),
                nombre: (string) ($c['name'] ?? 'Sin nombre'),
                businessManagerId: isset($c['business']['id']) ? (string) $c['business']['id'] : null,
                estado: isset($c['account_status']) ? (string) $c['account_status'] : null,
                moneda: isset($c['currency']) ? (string) $c['currency'] : null,
                zonaHoraria: isset($c['timezone_name']) ? (string) $c['timezone_name'] : null,
            );
            $importadas++;
        }

        $session->flash('success', "Token válido. Se sincronizaron {$importadas} cuentas publicitarias.");

        return Response::redirect('/admin/meta');
    }

    public function desconectar(Request $request): Response
    {
        $this->container->get(MetaTokenService::class)->borrarToken();
        $this->container->get(Session::class)->flash('success', 'Token de Meta eliminado.');

        return Response::redirect('/admin/meta');
    }
}
