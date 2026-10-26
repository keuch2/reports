<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\Session;
use MisterCo\Reports\Domain\Usuario;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\Meta\MetaApiException;
use MisterCo\Reports\Services\Meta\RedesSocialesService;
use Throwable;

/**
 * Analíticas orgánicas de Meta desde el admin: sincronizar páginas de Facebook
 * y cuentas de Instagram (en Cuenta Meta) e importar sus métricas (en Importar).
 */
final class RedesSocialesController
{
    public function __construct(private readonly Container $container)
    {
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

    /** Importa una página (`origen` = "facebook:ID") o una cuenta de Instagram ("instagram:ID"). */
    public function importar(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        [$plataforma, $id] = array_pad(explode(':', (string) $request->input('origen', ''), 2), 2, '');
        $id = (int) $id;
        $desde = (string) $request->input('rango_inicio', '');
        $hasta = (string) $request->input('rango_fin', '');
        $esFecha = static fn (string $f): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f) !== false;

        if (!in_array($plataforma, ['facebook', 'instagram'], true) || $id <= 0 || !$esFecha($desde) || !$esFecha($hasta) || $desde > $hasta) {
            $session->flash('error', 'Datos inválidos: revisá la página o cuenta y el rango de fechas.');

            return Response::redirect('/admin/importar');
        }

        set_time_limit(0);
        ignore_user_abort(true);

        $service = $this->container->get(RedesSocialesService::class);
        $audit = $this->container->get(AuditService::class);
        $entidad = $plataforma === 'facebook' ? 'meta_pagina' : 'meta_instagram';
        try {
            $r = $plataforma === 'facebook'
                ? $service->importarFacebook($id, $desde, $hasta, $usuario->id)
                : $service->importarInstagram($id, $desde, $hasta, $usuario->id);
            $audit->registrar('importacion_redes.completada', $usuario, $request->ip, $request->userAgent,
                $entidad, (string) $id, $r + ['rango' => "{$desde} a {$hasta}"]);
            $msg = sprintf('Importación %s #%d completada: %d días con métricas, %d publicaciones.',
                $plataforma === 'facebook' ? 'de Facebook' : 'de Instagram', $r['importacion_id'], $r['dias'], $r['publicaciones']);
            if ($r['avisos'] !== []) {
                $msg .= ' Avisos: ' . implode(' · ', $r['avisos']);
            }
            $session->flash('success', $msg);
        } catch (Throwable $e) {
            $audit->registrar('importacion_redes.fallida', $usuario, $request->ip, $request->userAgent,
                $entidad, (string) $id, ['error' => $e->getMessage(), 'rango' => "{$desde} a {$hasta}"]);
            $session->flash('error', $e instanceof MetaApiException ? $this->mensajeError($e) : 'Fallo en la importación: ' . $e->getMessage());
        }

        return Response::redirect('/admin/importar');
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
}
