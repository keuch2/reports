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
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Repositories\ImportacionRepository;
use MisterCo\Reports\Services\AuditService;
use MisterCo\Reports\Services\Meta\ImportacionService;
use MisterCo\Reports\Services\Meta\MetaApiException;
use MisterCo\Reports\Services\Meta\MetaTokenService;
use Throwable;

final class ImportacionController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function mostrar(Request $request): Response
    {
        $view = $this->container->get(View::class);
        $session = $this->container->get(Session::class);
        $cuentasRepo = $this->container->get(CuentaPublicitariaRepository::class);
        $importRepo = $this->container->get(ImportacionRepository::class);
        $entidadesRepo = $this->container->get(EntidadesMetaRepository::class);
        $tokenService = $this->container->get(MetaTokenService::class);

        $cuentas = $cuentasRepo->listarTodas();
        $ultimasFechas = [];
        $campaniasPorCuenta = [];
        foreach ($cuentas as $c) {
            $cid = (int) $c['id'];
            $ultimasFechas[$cid] = $importRepo->ultimaFechaImportada($cid);
            // Cargamos las campañas persistidas (no las de Meta). Si la cuenta nunca tuvo
            // importación, la query devuelve [] y no rompe; el fieldset no se muestra.
            $camps = $entidadesRepo->campaniasDeCuenta($cid);
            if ($camps !== []) {
                $campaniasPorCuenta[$cid] = $camps;
            }
        }

        return Response::html($view->render('admin/importar', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Importar datos',
            'tiene_token' => $tokenService->tieneToken(),
            'cuentas' => $cuentas,
            'ultimas_fechas' => $ultimasFechas,
            'campanias_por_cuenta' => $campaniasPorCuenta,
            'recientes' => $importRepo->recientes(15),
            'rango_inicio_default' => date('Y-m-d', strtotime('-30 days')),
            'rango_fin_default' => date('Y-m-d'),
            'error' => $session->getFlash('error'),
            'success' => $session->getFlash('success'),
        ]));
    }

    public function ejecutar(Request $request): Response
    {
        $session = $this->container->get(Session::class);
        /** @var Usuario $usuario */
        $usuario = $request->attributes['usuario'];

        $cuentaId = (int) $request->input('cuenta_id', 0);
        $rangoInicio = (string) $request->input('rango_inicio', '');
        $rangoFin = (string) $request->input('rango_fin', '');

        // "Importar toda la cuenta" fuerza el modo cuenta-completa e ignora
        // cualquier selección de campañas. Es la opción por defecto y la única
        // forma de traer campañas nuevas que todavía no están en el sistema
        // (la lista de checkboxes sale de la BD, no de Meta en vivo).
        $importarTodo = filter_var($request->input('importar_todo', false), FILTER_VALIDATE_BOOLEAN);

        // Lista opcional de meta_campaign_id a importar. Si vacía → toda la cuenta.
        $campaniasInput = $request->post['campanias_meta_ids'] ?? [];
        if (!is_array($campaniasInput)) {
            $campaniasInput = [];
        }
        $metaCampaignIds = $importarTodo ? [] : array_values(array_filter(array_map(
            static fn ($v) => trim((string) $v),
            $campaniasInput
        ), 'strlen'));

        if ($cuentaId <= 0 || !$this->validarFecha($rangoInicio) || !$this->validarFecha($rangoFin)) {
            $session->flash('error', 'Datos inválidos: revisá cuenta y rango.');

            return Response::redirect('/admin/importar');
        }

        if ($rangoInicio > $rangoFin) {
            $session->flash('error', 'La fecha de inicio no puede ser posterior a la fecha de fin.');

            return Response::redirect('/admin/importar');
        }

        set_time_limit(0);
        ignore_user_abort(true);

        $audit = $this->container->get(AuditService::class);
        $detalles = [
            'rango' => "{$rangoInicio} a {$rangoFin}",
            'modo' => $metaCampaignIds === [] ? 'cuenta_entera' : 'selectivo',
            'campanias_seleccionadas' => count($metaCampaignIds),
        ];
        $audit->registrar('importacion.iniciada', $usuario, $request->ip, $request->userAgent,
            'cuenta_publicitaria', (string) $cuentaId, $detalles);

        try {
            $service = $this->container->get(ImportacionService::class);
            $r = $service->importar($cuentaId, $rangoInicio, $rangoFin, $usuario->id, $metaCampaignIds);
            $audit->registrar('importacion.completada', $usuario, $request->ip, $request->userAgent,
                'importacion', (string) $r['importacion_id'], $r);

            $modo = $metaCampaignIds === []
                ? 'cuenta completa'
                : count($metaCampaignIds) . ' campaña(s) seleccionada(s)';
            $msg = sprintf(
                'Importación #%d completada (%s): %d campañas, %d adsets, %d anuncios, %d snapshots.',
                $r['importacion_id'], $modo, $r['campanias'], $r['adsets'], $r['anuncios'], $r['snapshots']
            );
            if ($r['snapshots'] === 0 && $r['anuncios'] > 0) {
                $msg .= ' ⚠ Meta no devolvió métricas en este rango — revisá el detalle en histórico.';
            }
            $session->flash('success', $msg);
        } catch (MetaApiException $e) {
            $msg = $e->esTokenInvalido()
                ? 'El token de Meta es inválido o expiró. Reconectá la cuenta.'
                : 'Meta API: ' . $e->getMessage();
            $audit->registrar('importacion.fallida', $usuario, $request->ip, $request->userAgent,
                'cuenta_publicitaria', (string) $cuentaId, ['error' => $e->getMessage(), 'token_invalido' => $e->esTokenInvalido()]);
            $session->flash('error', $msg);
        } catch (Throwable $e) {
            $audit->registrar('importacion.fallida', $usuario, $request->ip, $request->userAgent,
                'cuenta_publicitaria', (string) $cuentaId, ['error' => $e->getMessage()]);
            $session->flash('error', 'Fallo en la importación: ' . $e->getMessage());
        }

        return Response::redirect('/admin/importaciones');
    }

    private function validarFecha(string $fecha): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && strtotime($fecha) !== false;
    }
}
