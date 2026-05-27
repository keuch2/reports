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
use MisterCo\Reports\Repositories\ImportacionRepository;
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
        $tokenService = $this->container->get(MetaTokenService::class);

        return Response::html($view->render('admin/importar', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Importar datos',
            'tiene_token' => $tokenService->tieneToken(),
            'cuentas' => $cuentasRepo->listarTodas(),
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

        try {
            $service = $this->container->get(ImportacionService::class);
            $r = $service->importar($cuentaId, $rangoInicio, $rangoFin, $usuario->id);
            $session->flash('success', sprintf(
                'Importación #%d completada: %d campañas, %d adsets, %d anuncios, %d snapshots.',
                $r['importacion_id'], $r['campanias'], $r['adsets'], $r['anuncios'], $r['snapshots']
            ));
        } catch (MetaApiException $e) {
            $msg = $e->esTokenInvalido()
                ? 'El token de Meta es inválido o expiró. Reconectá la cuenta.'
                : 'Meta API: ' . $e->getMessage();
            $session->flash('error', $msg);
        } catch (Throwable $e) {
            $session->flash('error', 'Fallo en la importación: ' . $e->getMessage());
        }

        return Response::redirect('/admin/importar');
    }

    private function validarFecha(string $fecha): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && strtotime($fecha) !== false;
    }
}
