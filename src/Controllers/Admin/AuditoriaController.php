<?php

declare(strict_types=1);

namespace MisterCo\Reports\Controllers\Admin;

use MisterCo\Reports\Core\Container;
use MisterCo\Reports\Core\Request;
use MisterCo\Reports\Core\Response;
use MisterCo\Reports\Core\View;
use MisterCo\Reports\Services\AuditService;

final class AuditoriaController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function listar(Request $request): Response
    {
        $service = $this->container->get(AuditService::class);

        $filtros = [
            'accion' => trim((string) $request->input('accion', '')),
            'rol' => trim((string) $request->input('rol', '')),
            'desde' => $this->fecha((string) $request->input('desde', '')),
            'hasta' => $this->fecha((string) $request->input('hasta', '')),
            'buscar' => trim((string) $request->input('buscar', '')),
        ];

        $pagina = max(1, (int) $request->input('pagina', 1));
        $porPagina = 50;
        $eventos = $service->listar($filtros, $porPagina, ($pagina - 1) * $porPagina);

        $view = $this->container->get(View::class);

        return Response::html($view->render('admin/auditoria', [
            'usuario' => $request->attributes['usuario'],
            'titulo' => 'Auditoría',
            'eventos' => $eventos,
            'acciones' => $service->accionesDistintas(),
            'filtros' => $filtros,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'hay_mas' => count($eventos) === $porPagina,
        ]));
    }

    private function fecha(string $f): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) === 1 ? $f : '';
    }
}
