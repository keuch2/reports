<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\GoogleAdsRepository;
use MisterCo\Reports\Repositories\GoogleAnalyticsRepository;
use MisterCo\Reports\Repositories\ImportacionGoogleRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\Google\GoogleApiException;
use MisterCo\Reports\Services\Google\GoogleImportacionService;
use MisterCo\Reports\Services\Google\GoogleOAuthService;
use MisterCo\Reports\Services\Google\GoogleTokenService;
use Throwable;

/**
 * Conexión con Google (OAuth), sincronización de cuentas de Google Ads y
 * propiedades GA4, e importación on-demand de métricas.
 */
final class GoogleConexionController
{
    private const SESSION_OAUTH_STATE = 'google_oauth_state';

    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        $tokenService = $this->container->get(GoogleTokenService::class);
        $oauth = $this->container->get(GoogleOAuthService::class);

        return Response::html($this->container->get(View::class)->render('admin/google_conexion', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Google Ads y Analytics',
            'tiene_token' => $tokenService->tieneToken(),
            'email_conectado' => $tokenService->emailConectado(),
            'ads_disponible' => $tokenService->adsDisponible(),
            'oauth_disponible' => $oauth->configurado(),
            'oauth_redirect_uri' => $oauth->redirectUri(),
            'cuentas_ads' => $this->container->get(GoogleAdsRepository::class)->listarCuentas(),
            'propiedades_ga4' => $this->container->get(GoogleAnalyticsRepository::class)->listarPropiedades(),
            'error' => $session->getFlash('error'),
            'success' => $session->getFlash('success'),
        ]));
    }

    /** Pestaña "Google Ads y Analytics" de Importar: formulario y últimas importaciones. */
    public function mostrarImportar(Request $request): Response
    {
        $session = $this->container->get(Session::class);

        return Response::html($this->container->get(View::class)->render('admin/importar_google', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Importar · Google Ads y Analytics',
            'tiene_token' => $this->container->get(GoogleTokenService::class)->tieneToken(),
            'cuentas_ads' => $this->container->get(GoogleAdsRepository::class)->listarCuentas(),
            'propiedades_ga4' => $this->container->get(GoogleAnalyticsRepository::class)->listarPropiedades(),
            'recientes' => $this->container->get(ImportacionGoogleRepository::class)->recientes(),
            'rango_inicio_default' => date('Y-m-d', strtotime('-30 days')),
            'rango_fin_default' => date('Y-m-d', strtotime('-1 day')),
            'error' => $session->getFlash('error'),
            'success' => $session->getFlash('success'),
        ]));
    }

    public function oauthIniciar(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        $oauth = $this->container->get(GoogleOAuthService::class);

        if (!$oauth->configurado()) {
            $session->flash('error', 'El cliente OAuth de Google no está configurado (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).');

            return Response::redirect('/admin/google');
        }

        $state = bin2hex(random_bytes(24));
        $session->set(self::SESSION_OAUTH_STATE, $state);

        return Response::redirect($oauth->urlAutorizacion($state));
    }

    public function oauthCallback(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        $errorGoogle = (string) $request->input('error', '');
        if ($errorGoogle !== '') {
            $session->flash('error', 'Conexión cancelada o rechazada por Google: ' . $errorGoogle);

            return Response::redirect('/admin/google');
        }

        $stateEsperado = (string) $session->get(self::SESSION_OAUTH_STATE, '');
        $session->forget(self::SESSION_OAUTH_STATE);
        if ($stateEsperado === '' || !hash_equals($stateEsperado, (string) $request->input('state', ''))) {
            $session->flash('error', 'La sesión de conexión expiró o el state no coincide. Probá de nuevo.');

            return Response::redirect('/admin/google');
        }

        $code = (string) $request->input('code', '');
        if ($code === '') {
            $session->flash('error', 'Google no devolvió el código de autorización.');

            return Response::redirect('/admin/google');
        }

        $tokenService = $this->container->get(GoogleTokenService::class);
        try {
            ['refresh_token' => $refresh, 'email' => $email] = $this->container->get(GoogleOAuthService::class)->intercambiarCodigo($code);
            $tokenService->clienteCon($refresh); // valida que el refresh token sirva
        } catch (GoogleApiException $e) {
            $session->flash('error', 'No se pudo completar la conexión: ' . $e->getMessage());

            return Response::redirect('/admin/google');
        }

        $tokenService->guardarToken($refresh, $email, $usuario->id);
        $this->container->get(AuditService::class)->registrar(
            'google.oauth_conectado', $usuario, $request->ip, $request->userAgent, 'google_token', null,
            ['email' => $email]
        );

        $session->flash('success', 'Cuenta de Google conectada' . ($email !== null ? " ({$email})" : '') . '. ' . $this->sincronizarYResumir());

        return Response::redirect('/admin/google');
    }

    public function sincronizar(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        try {
            $session->flash('success', $this->sincronizarYResumir());
        } catch (GoogleApiException $e) {
            $session->flash('error', $this->mensajeError($e));
        }

        return Response::redirect('/admin/google');
    }

    public function desconectar(Request $request): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];
        $this->container->get(GoogleTokenService::class)->borrarToken();
        $this->container->get(AuditService::class)->registrar(
            'google.token_desconectado', $usuario, $request->ip, $request->userAgent, 'google_token'
        );
        $this->container->get(Session::class)->flash('success', 'Conexión con Google eliminada.');

        return Response::redirect('/admin/google');
    }

    /** Importa una cuenta de Google Ads o una propiedad GA4 (`origen` = "ads:ID" | "ga4:ID"). */
    public function importar(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        [$fuente, $id] = array_pad(explode(':', (string) $request->input('origen', ''), 2), 2, '');
        $id = (int) $id;
        $desde = (string) $request->input('rango_inicio', '');
        $hasta = (string) $request->input('rango_fin', '');

        if (!in_array($fuente, ['ads', 'ga4'], true) || $id <= 0 || !$this->esFecha($desde) || !$this->esFecha($hasta)) {
            $session->flash('error', 'Datos inválidos: revisá el origen y el rango.');

            return Response::redirect('/admin/importar/google');
        }
        if ($desde > $hasta) {
            $session->flash('error', 'La fecha de inicio no puede ser posterior a la fecha de fin.');

            return Response::redirect('/admin/importar/google');
        }

        set_time_limit(0);
        ignore_user_abort(true);

        $audit = $this->container->get(AuditService::class);
        $entidad = $fuente === 'ads' ? 'google_ads_cuenta' : 'ga4_propiedad';
        $service = $this->container->get(GoogleImportacionService::class);
        try {
            if ($fuente === 'ads') {
                $r = $service->importarAds($id, $desde, $hasta, $usuario->id);
                $msg = sprintf('Importación Google Ads #%d completada: %d campañas, %d filas diarias.', $r['importacion_id'], $r['campanias'], $r['filas']);
            } else {
                $r = $service->importarAnalytics($id, $desde, $hasta, $usuario->id);
                $msg = sprintf('Importación Google Analytics #%d completada: %d días, %d filas por canal.', $r['importacion_id'], $r['dias'], $r['filas_canal']);
            }
            $audit->registrar('importacion_google.completada', $usuario, $request->ip, $request->userAgent,
                $entidad, (string) $id, $r + ['rango' => "{$desde} a {$hasta}"]);
            $session->flash('success', $msg);
        } catch (Throwable $e) {
            $audit->registrar('importacion_google.fallida', $usuario, $request->ip, $request->userAgent,
                $entidad, (string) $id, ['error' => $e->getMessage(), 'rango' => "{$desde} a {$hasta}"]);
            $session->flash('error', $e instanceof GoogleApiException ? $this->mensajeError($e) : 'Fallo en la importación: ' . $e->getMessage());
        }

        return Response::redirect('/admin/importar/google');
    }

    private function sincronizarYResumir(): string
    {
        $r = $this->container->get(GoogleImportacionService::class)->sincronizar();
        $msg = "Se sincronizaron {$r['cuentas_ads']} cuentas de Google Ads y {$r['propiedades_ga4']} propiedades de Google Analytics.";
        if ($r['avisos'] !== []) {
            $msg .= ' Avisos: ' . implode(' · ', $r['avisos']);
        }

        return $msg;
    }

    private function mensajeError(GoogleApiException $e): string
    {
        return $e->esTokenInvalido()
            ? 'El acceso a Google es inválido o fue revocado. Reconectá la cuenta.'
            : $e->getMessage();
    }

    private function esFecha(string $fecha): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && strtotime($fecha) !== false;
    }
}
