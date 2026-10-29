<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Repositories\RedesSocialesRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\Meta\MetaApiException;
use MisterCo\Reports\Services\Meta\RedesSocialesService;
use Throwable;

/**
 * Analíticas orgánicas de Meta desde el admin: sincronizar páginas de Facebook
 * y cuentas de Instagram (en Cuenta Meta) e importar sus métricas.
 *
 * La importación la conduce el navegador por tramos (iniciar → tramo → … →
 * finalizar), cada uno una petición JSON corta, para mostrar progreso real y
 * no chocar con el timeout del servidor web en rangos de varios meses.
 */
final class RedesSocialesController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        $repo = $this->container->get(RedesSocialesRepository::class);

        return Response::html($this->container->get(View::class)->render('admin/importar_redes', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Importar · Facebook e Instagram',
            'paginas' => $repo->listarPaginas(),
            'recientes' => $repo->importacionesRecientes(15),
            'rango_inicio_default' => date('Y-m-d', strtotime('-30 days')),
            'rango_fin_default' => date('Y-m-d', strtotime('-1 day')),
        ]));
    }

    public function sincronizar(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        try {
            $r = $this->container->get(RedesSocialesService::class)->sincronizar();
            $this->container->get(AuditService::class)->registrar(
                'redes.sincronizadas', $request->attributes['usuario'], $request->ip, $request->userAgent, 'meta_token', null, $r
            );
            $session->flash('success', "Se sincronizaron {$r['paginas']} páginas de Facebook y {$r['instagram']} cuentas de Instagram.");
        } catch (MetaApiException $e) {
            $session->flash('error', $this->mensajeError($e));
        }

        return Response::redirect('/admin/meta');
    }

    /** Abre la importación (`origen` = "facebook:ID" | "instagram:ID") y devuelve sus tramos. */
    public function iniciar(Request $request): Response
    {
        [$plataforma, $id] = array_pad(explode(':', (string) $request->input('origen', ''), 2), 2, '');
        $id = (int) $id;
        $desde = (string) $request->input('rango_inicio', '');
        $hasta = (string) $request->input('rango_fin', '');

        if (!in_array($plataforma, ['facebook', 'instagram'], true) || $id <= 0
            || !$this->esFecha($desde) || !$this->esFecha($hasta) || $desde > $hasta) {
            return Response::json(['error' => 'Datos inválidos: revisá la página o cuenta y el rango de fechas.'], 422);
        }

        return $this->paso(function () use ($plataforma, $id, $desde, $hasta, $request): array {
            /** @var Usuario $usuario */
            $usuario = $request->attributes['usuario'];
            $r = $this->container->get(RedesSocialesService::class)->iniciarImportacion($plataforma, $id, $desde, $hasta, $usuario->id);
            $this->container->get(AuditService::class)->registrar('importacion_redes.iniciada', $usuario, $request->ip, $request->userAgent,
                $plataforma === 'facebook' ? 'meta_pagina' : 'meta_instagram', (string) $id,
                ['importacion_id' => $r['importacion_id'], 'rango' => "{$desde} a {$hasta}"]);

            return $r;
        });
    }

    public function tramo(Request $request): Response
    {
        $desde = (string) $request->input('desde', '');
        $hasta = (string) $request->input('hasta', '');
        if (!$this->esFecha($desde) || !$this->esFecha($hasta)) {
            return Response::json(['error' => 'Tramo inválido.'], 422);
        }
        set_time_limit(0);

        return $this->paso(fn (): array => $this->container->get(RedesSocialesService::class)
            ->importarTramo((int) ($request->attributes['id'] ?? 0), $desde, $hasta));
    }

    public function finalizar(Request $request): Response
    {
        set_time_limit(0);

        return $this->paso(function () use ($request): array {
            $r = $this->container->get(RedesSocialesService::class)->finalizarImportacion((int) ($request->attributes['id'] ?? 0));
            $this->container->get(AuditService::class)->registrar('importacion_redes.completada', $request->attributes['usuario'],
                $request->ip, $request->userAgent, 'importacion_redes', (string) $r['importacion_id'], $r);

            return $r;
        });
    }

    /**
     * Ejecuta un paso y lo responde como JSON; los errores de Meta se traducen
     * a un mensaje accionable.
     *
     * @param callable(): array<string,mixed> $paso
     */
    private function paso(callable $paso): Response
    {
        ignore_user_abort(true);
        try {
            return Response::json($paso());
        } catch (MetaApiException $e) {
            return Response::json(['error' => $this->mensajeError($e)], 502);
        } catch (Throwable $e) {
            return Response::json(['error' => 'Fallo en la importación: ' . $e->getMessage()], 500);
        }
    }

    private function mensajeError(MetaApiException $e): string
    {
        if ($e->esTokenInvalido()) {
            return 'El token de Meta es inválido o expiró. Reconectá la cuenta.';
        }
        // 10 / 200 = falta de permisos: la conexión es anterior a los permisos de páginas e Instagram.
        if (in_array($e->metaCode, [10, 200], true)) {
            return 'La conexión con Meta no tiene permisos de páginas e Instagram: reconectá con Facebook desde Cuenta Meta. (' . $e->getMessage() . ')';
        }

        return 'Meta API: ' . $e->getMessage();
    }

    private function esFecha(string $f): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f) !== false;
    }
}
