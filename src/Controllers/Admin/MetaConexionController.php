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
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\Meta\MetaApiException;
use MisterCo\Reports\Services\Meta\MetaOAuthService;
use MisterCo\Reports\Services\Meta\MetaTokenService;

final class MetaConexionController
{
    private const SESSION_OAUTH_STATE = 'meta_oauth_state';

    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);
        $tokenService = $this->container->get(MetaTokenService::class);
        $oauth = $this->container->get(MetaOAuthService::class);
        $cuentasRepo = $this->container->get(CuentaPublicitariaRepository::class);

        return Response::html($view->render('admin/meta_conexion', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Conectar cuenta Meta',
            'tiene_token' => $tokenService->tieneToken(),
            'oauth_disponible' => $oauth->configurado(),
            'oauth_redirect_uri' => $oauth->redirectUri(),
            'vence_en' => $tokenService->venceEn(),
            'dias_vencimiento' => $tokenService->diasHastaVencimiento(),
            'cuentas' => $cuentasRepo->listarTodas(),
            'error' => $session->getFlash('error'),
            'success' => $session->getFlash('success'),
        ]));
    }

    /** Arranca el flujo OAuth: genera state anti-CSRF y redirige al diálogo de Facebook. */
    public function oauthIniciar(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        $oauth = $this->container->get(MetaOAuthService::class);

        if (!$oauth->configurado()) {
            $session->flash('error', 'La App de Meta no está configurada (META_APP_ID / META_APP_SECRET).');

            return Response::redirect('/admin/meta');
        }

        $state = bin2hex(random_bytes(24));
        $session->set(self::SESSION_OAUTH_STATE, $state);

        return Response::redirect($oauth->urlAutorizacion($state));
    }

    /** Callback del diálogo de Facebook: valida state, canjea el code y guarda el token. */
    public function oauthCallback(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        // Usuario canceló o Meta devolvió error.
        $errorMeta = (string) $request->input('error', '');
        if ($errorMeta !== '') {
            $descripcion = (string) $request->input('error_description', $errorMeta);
            $session->flash('error', 'Conexión cancelada o rechazada: ' . $descripcion);

            return Response::redirect('/admin/meta');
        }

        // Anti-CSRF: el state debe coincidir con el generado al iniciar.
        $stateEsperado = (string) $session->get(self::SESSION_OAUTH_STATE, '');
        $session->forget(self::SESSION_OAUTH_STATE);
        $stateRecibido = (string) $request->input('state', '');
        if ($stateEsperado === '' || !hash_equals($stateEsperado, $stateRecibido)) {
            $session->flash('error', 'La sesión de conexión expiró o el state no coincide. Probá de nuevo.');

            return Response::redirect('/admin/meta');
        }

        $code = (string) $request->input('code', '');
        if ($code === '') {
            $session->flash('error', 'Meta no devolvió el código de autorización.');

            return Response::redirect('/admin/meta');
        }

        $oauth = $this->container->get(MetaOAuthService::class);
        $tokenService = $this->container->get(MetaTokenService::class);

        try {
            ['token' => $token, 'expira_en' => $expiraEn] = $oauth->intercambiarCodigo($code);
            $cliente = $tokenService->clienteCon($token);
            $cuentasMeta = $cliente->validarTokenYListarCuentas();
        } catch (MetaApiException $e) {
            $session->flash('error', 'No se pudo completar la conexión: ' . $e->getMessage());

            return Response::redirect('/admin/meta');
        }

        $tokenService->guardarTokenConVencimiento($token, $usuario->id, $expiraEn);
        $this->container->get(AuditService::class)->registrar(
            'meta.oauth_conectado', $usuario, $request->ip, $request->userAgent, 'meta_token', null,
            [
                'cuentas_descubiertas' => count($cuentasMeta),
                'vence_en' => $expiraEn?->format('Y-m-d'),
            ]
        );

        $importadas = $this->sincronizarCuentas($cuentasMeta);

        $vence = $expiraEn !== null ? ' El acceso vence el ' . $expiraEn->format('d/m/Y') . '.' : '';
        $session->flash('success', "Cuenta conectada con Facebook. Se sincronizaron {$importadas} cuentas publicitarias.{$vence}");

        return Response::redirect('/admin/meta');
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

        try {
            $cliente = $tokenService->clienteCon($token);
            $cuentasMeta = $cliente->validarTokenYListarCuentas();
        } catch (MetaApiException $e) {
            $session->flash('error', 'Token inválido o sin permisos: ' . $e->getMessage());

            return Response::redirect('/admin/meta');
        }

        $tokenService->guardarToken($token, $usuario->id);
        $this->container->get(AuditService::class)->registrar(
            'meta.token_conectado', $usuario, $request->ip, $request->userAgent, 'meta_token', null,
            ['cuentas_descubiertas' => count($cuentasMeta)]
        );

        $importadas = $this->sincronizarCuentas($cuentasMeta);

        $session->flash('success', "Token válido. Se sincronizaron {$importadas} cuentas publicitarias.");

        return Response::redirect('/admin/meta');
    }

    public function desconectar(Request $request): Response
    {
        /** @var \MisterCo\Reports\Domain\Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $this->container->get(MetaTokenService::class)->borrarToken();
        $this->container->get(AuditService::class)->registrar(
            'meta.token_desconectado', $usuario, $request->ip, $request->userAgent, 'meta_token'
        );
        $this->container->get(Session::class)->flash('success', 'Token de Meta eliminado.');

        return Response::redirect('/admin/meta');
    }

    /**
     * Upsertea las cuentas publicitarias descubiertas con el token.
     *
     * @param list<array<string,mixed>> $cuentasMeta
     */
    private function sincronizarCuentas(array $cuentasMeta): int
    {
        $cuentasRepo = $this->container->get(CuentaPublicitariaRepository::class);
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

        return $importadas;
    }
}
