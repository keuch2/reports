<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Google;

use MisterCo\Reports\Repositories\GoogleAdsRepository;
use MisterCo\Reports\Repositories\GoogleAnalyticsRepository;
use MisterCo\Reports\Repositories\ImportacionGoogleRepository;
use RuntimeException;
use Throwable;

/**
 * Sincroniza cuentas de Google Ads / propiedades GA4 accesibles e importa
 * métricas diarias on-demand (misma lógica que la importación de Meta).
 * Idempotente: cada importación reemplaza las métricas del rango.
 */
final class GoogleImportacionService
{
    /** Métricas GA4 de los totales diarios, en el orden en que se piden. */
    private const GA4_METRICAS_DIARIAS = [
        'sesiones' => 'sessions',
        'usuarios' => 'totalUsers',
        'usuarios_nuevos' => 'newUsers',
        'vistas' => 'screenPageViews',
        'sesiones_con_interaccion' => 'engagedSessions',
        'duracion_interaccion_seg' => 'userEngagementDuration',
        'eventos_clave' => 'keyEvents',
        'ingresos' => 'totalRevenue',
    ];

    private const GA4_METRICAS_CANAL = [
        'sesiones' => 'sessions',
        'usuarios' => 'totalUsers',
        'eventos_clave' => 'keyEvents',
        'ingresos' => 'totalRevenue',
    ];

    public function __construct(
        private readonly GoogleTokenService $tokenService,
        private readonly GoogleAdsRepository $adsRepo,
        private readonly GoogleAnalyticsRepository $gaRepo,
        private readonly ImportacionGoogleRepository $importacionRepo,
    ) {
    }

    /**
     * Descubre las cuentas de Google Ads (incluidas las que cuelgan de un MCC) y
     * las propiedades GA4 accesibles con la cuenta conectada.
     *
     * @return array{cuentas_ads:int, propiedades_ga4:int, avisos:list<string>}
     */
    public function sincronizar(): array
    {
        $cliente = $this->tokenService->cliente();
        $avisos = [];

        $cuentasAds = 0;
        if ($this->tokenService->adsDisponible()) {
            try {
                $cuentasAds = $this->sincronizarCuentasAds($cliente, $avisos);
            } catch (GoogleApiException $e) {
                $avisos[] = 'Google Ads: ' . $e->getMessage();
            }
        } else {
            $avisos[] = 'Google Ads no se sincronizó: falta GOOGLE_ADS_DEVELOPER_TOKEN en el .env.';
        }

        $propiedades = 0;
        try {
            $propiedades = $this->sincronizarPropiedadesGa4($cliente);
        } catch (GoogleApiException $e) {
            $avisos[] = 'Google Analytics: ' . $e->getMessage();
        }

        return ['cuentas_ads' => $cuentasAds, 'propiedades_ga4' => $propiedades, 'avisos' => $avisos];
    }

    /**
     * Importa campañas + métricas diarias por campaña de una cuenta de Google Ads.
     *
     * @return array{importacion_id:int, campanias:int, filas:int}
     */
    public function importarAds(int $cuentaId, string $desde, string $hasta, int $usuarioId): array
    {
        $cuenta = $this->adsRepo->buscarCuenta($cuentaId);
        if ($cuenta === null) {
            throw new RuntimeException("Cuenta de Google Ads {$cuentaId} no existe.");
        }
        $customerId = (string) $cuenta['customer_id'];
        $login = $cuenta['login_customer_id'] !== null ? (string) $cuenta['login_customer_id'] : null;

        $importacionId = $this->importacionRepo->crear('google_ads', $cuentaId, $usuarioId, $desde, $hasta);
        try {
            $cliente = $this->tokenService->cliente();

            // 1) Campañas no eliminadas, para poder asignarlas aunque no tengan datos todavía.
            $idsInternos = [];
            $gaqlCampanias = "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type,
                                     campaign_budget.amount_micros
                                FROM campaign
                               WHERE campaign.status != 'REMOVED'";
            foreach ($cliente->adsBuscar($customerId, $gaqlCampanias, $login) as $fila) {
                $idsInternos[(string) $fila['campaign']['id']] = $this->upsertCampania($cuentaId, $fila);
            }

            // 2) Métricas diarias. Incluye campañas eliminadas que tuvieron actividad en el rango.
            $gaqlMetricas = sprintf(
                "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type,
                        segments.date, metrics.cost_micros, metrics.impressions, metrics.clicks,
                        metrics.conversions, metrics.conversions_value
                   FROM campaign
                  WHERE segments.date BETWEEN '%s' AND '%s'",
                $desde,
                $hasta
            );
            $filas = [];
            foreach ($cliente->adsBuscar($customerId, $gaqlMetricas, $login) as $fila) {
                $googleId = (string) $fila['campaign']['id'];
                $idsInternos[$googleId] ??= $this->upsertCampania($cuentaId, $fila);
                $m = $fila['metrics'] ?? [];
                // Los valores en 0 vienen omitidos en el JSON (defaults de proto3).
                $filas[] = [
                    'campania_id' => $idsInternos[$googleId],
                    'fecha' => (string) $fila['segments']['date'],
                    'gasto' => ((int) ($m['costMicros'] ?? 0)) / 1_000_000,
                    'impresiones' => (int) ($m['impressions'] ?? 0),
                    'clicks' => (int) ($m['clicks'] ?? 0),
                    'conversiones' => (float) ($m['conversions'] ?? 0),
                    'valor_conversiones' => (float) ($m['conversionsValue'] ?? 0),
                ];
            }

            $this->adsRepo->reemplazarMetricas($cuentaId, $desde, $hasta, $filas);
            $this->adsRepo->marcarSincronizada($cuentaId);

            $aviso = $filas === [] && $idsInternos !== []
                ? "Google Ads no devolvió métricas para {$desde} → {$hasta}: las campañas no tuvieron actividad en ese rango."
                : null;
            $this->importacionRepo->marcarCompletada($importacionId, count($idsInternos), count($filas), $aviso);
        } catch (Throwable $e) {
            $this->importacionRepo->marcarFallida($importacionId, $e->getMessage());
            throw $e;
        }

        return ['importacion_id' => $importacionId, 'campanias' => count($idsInternos), 'filas' => count($filas)];
    }

    /**
     * Importa métricas diarias (totales y por canal) de una propiedad GA4.
     *
     * @return array{importacion_id:int, dias:int, filas_canal:int}
     */
    public function importarAnalytics(int $propiedadId, string $desde, string $hasta, int $usuarioId): array
    {
        $propiedad = $this->gaRepo->buscarPropiedad($propiedadId);
        if ($propiedad === null) {
            throw new RuntimeException("Propiedad GA4 {$propiedadId} no existe.");
        }
        $propertyId = (string) $propiedad['property_id'];

        $importacionId = $this->importacionRepo->crear('ga4', $propiedadId, $usuarioId, $desde, $hasta);
        try {
            $cliente = $this->tokenService->cliente();
            $rango = [['startDate' => $desde, 'endDate' => $hasta]];

            $diarias = [];
            foreach ($cliente->gaReporte($propertyId, [
                'dateRanges' => $rango,
                'dimensions' => [['name' => 'date']],
                'metrics' => array_map(static fn ($m) => ['name' => $m], array_values(self::GA4_METRICAS_DIARIAS)),
            ]) as $fila) {
                $diarias[] = ['fecha' => $this->fechaGa4($fila['dimensiones'][0])]
                    + $this->mapearMetricas(self::GA4_METRICAS_DIARIAS, $fila['metricas']);
            }

            $canales = [];
            foreach ($cliente->gaReporte($propertyId, [
                'dateRanges' => $rango,
                'dimensions' => [['name' => 'date'], ['name' => 'sessionDefaultChannelGroup']],
                'metrics' => array_map(static fn ($m) => ['name' => $m], array_values(self::GA4_METRICAS_CANAL)),
            ]) as $fila) {
                $canales[] = [
                    'fecha' => $this->fechaGa4($fila['dimensiones'][0]),
                    'canal' => mb_substr($fila['dimensiones'][1] !== '' ? $fila['dimensiones'][1] : '(sin canal)', 0, 100),
                ] + $this->mapearMetricas(self::GA4_METRICAS_CANAL, $fila['metricas']);
            }

            $this->gaRepo->reemplazarMetricas($propiedadId, $desde, $hasta, $diarias, $canales);
            $this->gaRepo->marcarSincronizada($propiedadId);

            $aviso = $diarias === []
                ? "Google Analytics no devolvió datos para {$desde} → {$hasta}: la propiedad no registró tráfico en ese rango."
                : null;
            $this->importacionRepo->marcarCompletada($importacionId, 1, count($diarias) + count($canales), $aviso);
        } catch (Throwable $e) {
            $this->importacionRepo->marcarFallida($importacionId, $e->getMessage());
            throw $e;
        }

        return ['importacion_id' => $importacionId, 'dias' => count($diarias), 'filas_canal' => count($canales)];
    }

    /** @param list<string> $avisos */
    private function sincronizarCuentasAds(GoogleApiClient $cliente, array &$avisos): int
    {
        $sincronizadas = [];
        // customer_client incluye la propia cuenta (nivel 0) y, si es MCC, todas
        // las cuentas que administra. Las MCC no tienen campañas: las salteamos.
        $gaql = "SELECT customer_client.id, customer_client.descriptive_name, customer_client.currency_code,
                        customer_client.time_zone, customer_client.manager
                   FROM customer_client
                  WHERE customer_client.status = 'ENABLED'";

        foreach ($cliente->adsCuentasAccesibles() as $raiz) {
            try {
                foreach ($cliente->adsBuscar($raiz, $gaql, $raiz) as $fila) {
                    $cc = $fila['customerClient'] ?? [];
                    $id = (string) ($cc['id'] ?? '');
                    if ($id === '' || !empty($cc['manager']) || isset($sincronizadas[$id])) {
                        continue;
                    }
                    $this->adsRepo->upsertCuenta(
                        $id,
                        (string) ($cc['descriptiveName'] ?? '') !== '' ? (string) $cc['descriptiveName'] : "Cuenta {$id}",
                        isset($cc['currencyCode']) ? (string) $cc['currencyCode'] : null,
                        isset($cc['timeZone']) ? (string) $cc['timeZone'] : null,
                        $raiz,
                    );
                    $sincronizadas[$id] = true;
                }
            } catch (GoogleApiException $e) {
                // Cuentas canceladas/suspendidas devuelven error: seguimos con las demás.
                $avisos[] = "Google Ads (cuenta {$raiz}): " . $e->getMessage();
            }
        }

        return count($sincronizadas);
    }

    private function sincronizarPropiedadesGa4(GoogleApiClient $cliente): int
    {
        $total = 0;
        foreach ($cliente->gaResumenCuentas() as $cuenta) {
            foreach ($cuenta['propertySummaries'] ?? [] as $p) {
                $propertyId = substr((string) ($p['property'] ?? ''), strlen('properties/'));
                if ($propertyId === '') {
                    continue;
                }
                $detalle = [];
                try {
                    $detalle = $cliente->gaPropiedad($propertyId);
                } catch (GoogleApiException) {
                    // Sin moneda/zona no se rompe nada: el reporte las muestra vacías.
                }
                $this->gaRepo->upsertPropiedad(
                    $propertyId,
                    (string) ($p['displayName'] ?? "Propiedad {$propertyId}"),
                    isset($cuenta['displayName']) ? (string) $cuenta['displayName'] : null,
                    isset($detalle['currencyCode']) ? (string) $detalle['currencyCode'] : null,
                    isset($detalle['timeZone']) ? (string) $detalle['timeZone'] : null,
                );
                $total++;
            }
        }

        return $total;
    }

    /** @param array<string, mixed> $fila */
    private function upsertCampania(int $cuentaId, array $fila): int
    {
        $c = $fila['campaign'];
        $micros = $fila['campaignBudget']['amountMicros'] ?? null;

        return $this->adsRepo->upsertCampania(
            $cuentaId,
            (string) $c['id'],
            (string) ($c['name'] ?? 'Sin nombre'),
            isset($c['status']) ? (string) $c['status'] : null,
            isset($c['advertisingChannelType']) ? (string) $c['advertisingChannelType'] : null,
            $micros !== null ? ((int) $micros) / 1_000_000 : null,
        );
    }

    /**
     * @param array<string, string> $mapa columna => métrica GA4, en el orden pedido
     * @param list<string> $valores
     * @return array<string, int|float>
     */
    private function mapearMetricas(array $mapa, array $valores): array
    {
        $resultado = [];
        foreach (array_keys($mapa) as $i => $columna) {
            $valor = (float) ($valores[$i] ?? 0);
            $resultado[$columna] = in_array($columna, ['eventos_clave', 'ingresos'], true) ? round($valor, 2) : (int) round($valor);
        }

        return $resultado;
    }

    /** GA4 devuelve la dimensión date como YYYYMMDD. */
    private function fechaGa4(string $yyyymmdd): string
    {
        return substr($yyyymmdd, 0, 4) . '-' . substr($yyyymmdd, 4, 2) . '-' . substr($yyyymmdd, 6, 2);
    }
}
