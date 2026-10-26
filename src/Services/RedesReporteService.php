<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Repositories\RedesSocialesRepository;

/**
 * Datos del reporte de Facebook e Instagram (analíticas orgánicas de Meta)
 * de un cliente. Única fuente para el dashboard y el PDF.
 */
final class RedesReporteService
{
    public function __construct(private readonly RedesSocialesRepository $repo)
    {
    }

    public function tieneRedes(int $clienteId): bool
    {
        return $this->repo->idsPaginasAsignadas($clienteId) !== [] || $this->repo->idsInstagramAsignadas($clienteId) !== [];
    }

    /** @return list<string> */
    public function meses(int $clienteId): array
    {
        return $this->repo->mesesConDatos($clienteId);
    }

    /** @return array<string, mixed> */
    public function datos(int $clienteId, string $desde, string $hasta): array
    {
        $paginas = $this->repo->paginasDelCliente($clienteId);
        $cuentas = $this->repo->instagramDelCliente($clienteId);

        return [
            'paginas' => $paginas,
            'cuentas_instagram' => $cuentas,
            'facebook' => $paginas === [] ? null : [
                'totales' => $this->repo->metricasFacebook($clienteId, $desde, $hasta)[0] ?? [],
                'por_entidad' => $this->repo->metricasFacebook($clienteId, $desde, $hasta, 'entidad'),
                'evolucion' => $this->repo->metricasFacebook($clienteId, $desde, $hasta, 'fecha'),
                'publicaciones' => $this->repo->publicaciones('facebook', $clienteId, $desde, $hasta),
                'cantidad_publicaciones' => $this->repo->cantidadPublicaciones('facebook', $clienteId, $desde, $hasta),
                'seguidores' => $this->sumaSeguidores($paginas),
            ],
            'instagram' => $cuentas === [] ? null : [
                'totales' => $this->repo->metricasInstagram($clienteId, $desde, $hasta)[0] ?? [],
                'por_entidad' => $this->repo->metricasInstagram($clienteId, $desde, $hasta, 'entidad'),
                'evolucion' => $this->repo->metricasInstagram($clienteId, $desde, $hasta, 'fecha'),
                'publicaciones' => $this->repo->publicaciones('instagram', $clienteId, $desde, $hasta),
                'cantidad_publicaciones' => $this->repo->cantidadPublicaciones('instagram', $clienteId, $desde, $hasta),
                'seguidores' => $this->sumaSeguidores($cuentas),
            ],
        ];
    }

    /**
     * Seguidores actuales (al último sincronizar/importar); null si Meta no los informó.
     *
     * @param list<array<string,mixed>> $entidades
     */
    private function sumaSeguidores(array $entidades): ?int
    {
        $conDato = array_filter($entidades, static fn ($e) => $e['seguidores'] !== null);

        return $conDato === [] ? null : (int) array_sum(array_column($conDato, 'seguidores'));
    }
}
