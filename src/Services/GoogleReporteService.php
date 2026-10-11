<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Repositories\GoogleAdsRepository;
use MisterCo\Reports\Repositories\GoogleAnalyticsRepository;

/**
 * Datos de los reportes de Google Ads y Google Analytics de un cliente.
 * Única fuente para el dashboard (cliente y preview admin) y el PDF, así ambos
 * muestran exactamente los mismos números para el mismo período.
 */
final class GoogleReporteService
{
    public function __construct(
        private readonly GoogleAdsRepository $adsRepo,
        private readonly GoogleAnalyticsRepository $gaRepo,
    ) {
    }

    public function tieneGoogleAds(int $clienteId): bool
    {
        return $this->adsRepo->idsAsignadas($clienteId) !== [];
    }

    public function tieneAnalytics(int $clienteId): bool
    {
        return $this->gaRepo->idsAsignadas($clienteId) !== [];
    }

    /** @return list<string> */
    public function mesesAds(int $clienteId): array
    {
        return $this->adsRepo->mesesConDatos($clienteId);
    }

    /** @return list<string> */
    public function mesesAnalytics(int $clienteId): array
    {
        return $this->gaRepo->mesesConDatos($clienteId);
    }

    /** @return array<string, mixed> */
    public function datosAds(int $clienteId, string $desde, string $hasta): array
    {
        $asignadas = $this->adsRepo->campaniasDelCliente($clienteId);

        return [
            'campanias_asignadas_count' => count($asignadas),
            // Igual que en Meta: asumimos que las cuentas del cliente comparten moneda.
            'moneda' => (string) ($asignadas[0]['moneda'] ?? ''),
            'totales' => $this->adsRepo->totales($clienteId, $desde, $hasta),
            'campanias' => $this->adsRepo->porCampania($clienteId, $desde, $hasta),
            'evolucion' => $this->adsRepo->evolucionDiaria($clienteId, $desde, $hasta),
        ];
    }

    /** @return array<string, mixed> */
    public function datosAnalytics(int $clienteId, string $desde, string $hasta): array
    {
        $propiedades = $this->gaRepo->propiedadesDelCliente($clienteId);

        return [
            'propiedades_asignadas' => $propiedades,
            'moneda' => (string) ($propiedades[0]['moneda'] ?? ''),
            'totales' => $this->gaRepo->totales($clienteId, $desde, $hasta),
            'por_propiedad' => $this->gaRepo->porPropiedad($clienteId, $desde, $hasta),
            'canales' => $this->gaRepo->porCanal($clienteId, $desde, $hasta),
            'evolucion' => $this->gaRepo->evolucionDiaria($clienteId, $desde, $hasta),
        ];
    }
}
