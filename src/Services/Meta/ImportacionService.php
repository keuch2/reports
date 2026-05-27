<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Meta;

use MisterCo\Reports\Repositories\CuentaPublicitariaRepository;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Repositories\ImportacionRepository;
use MisterCo\Reports\Repositories\MetricaSnapshotRepository;
use RuntimeException;
use Throwable;

/**
 * Importa la jerarquía completa de una cuenta publicitaria + métricas diarias
 * a nivel ad para el rango indicado. Idempotente vía upserts.
 */
final class ImportacionService
{
    /** Fields que pedimos a Meta para insights diarios a nivel ad. */
    private const INSIGHTS_FIELDS = [
        'date_start', 'date_stop', 'ad_id', 'spend', 'impressions', 'reach', 'frequency',
        'clicks', 'inline_link_clicks', 'ctr', 'cpc', 'cpm', 'cost_per_result', 'results', 'conversions',
    ];

    public function __construct(
        private readonly MetaTokenService $tokenService,
        private readonly CuentaPublicitariaRepository $cuentasRepo,
        private readonly EntidadesMetaRepository $entidadesRepo,
        private readonly MetricaSnapshotRepository $snapshotsRepo,
        private readonly ImportacionRepository $importacionRepo,
    ) {
    }

    /**
     * Ejecuta una importación completa. Síncrono — el caller debe usar
     * set_time_limit(0) y ignore_user_abort(true) antes de invocar.
     *
     * @return array{importacion_id:int, campanias:int, adsets:int, anuncios:int, snapshots:int}
     */
    public function importar(int $cuentaId, string $rangoInicio, string $rangoFin, int $usuarioId): array
    {
        $cuenta = $this->cuentasRepo->buscarPorId($cuentaId);
        if ($cuenta === null) {
            throw new RuntimeException("Cuenta publicitaria {$cuentaId} no existe.");
        }

        $metaAccountId = (string) $cuenta['meta_account_id'];
        $importacionId = $this->importacionRepo->crear($cuentaId, $usuarioId, $rangoInicio, $rangoFin);

        $cliente = $this->tokenService->cliente();
        $llamadas = 0;
        $campanias = 0;
        $adsets = 0;
        $anuncios = 0;
        $snapshots = 0;

        try {
            // 1) Campañas
            foreach ($cliente->paginar("act_{$metaAccountId}/campaigns", [
                'fields' => ['id', 'name', 'objective', 'status', 'start_time', 'stop_time',
                             'daily_budget', 'lifetime_budget'],
                'limit' => 100,
            ]) as $c) {
                $llamadas++;
                $this->entidadesRepo->upsertCampania(
                    metaCampaignId: (string) $c['id'],
                    cuentaPublicitariaId: $cuentaId,
                    nombre: (string) ($c['name'] ?? 'Sin nombre'),
                    objetivo: isset($c['objective']) ? (string) $c['objective'] : null,
                    estado: isset($c['status']) ? (string) $c['status'] : null,
                    fechaInicio: $this->fechaSolo($c['start_time'] ?? null),
                    fechaFin: $this->fechaSolo($c['stop_time'] ?? null),
                    presupuestoDiario: isset($c['daily_budget']) ? ((float) $c['daily_budget']) / 100 : null,
                    presupuestoTotal: isset($c['lifetime_budget']) ? ((float) $c['lifetime_budget']) / 100 : null,
                );
                $campanias++;
            }

            // 2) Adsets — los traemos por cuenta (más eficiente que recorrer por campaña).
            $mapaAdsetCampania = [];
            foreach ($cliente->paginar("act_{$metaAccountId}/adsets", [
                'fields' => ['id', 'name', 'campaign_id', 'status', 'targeting',
                             'daily_budget', 'lifetime_budget', 'optimization_goal'],
                'limit' => 100,
            ]) as $a) {
                $llamadas++;
                $campaniaIdInterno = $this->entidadesRepo->buscarCampaniaPorMetaId((string) ($a['campaign_id'] ?? ''));
                if ($campaniaIdInterno === null) {
                    continue;
                }
                $segmentacion = isset($a['targeting'])
                    ? json_encode($a['targeting'], JSON_UNESCAPED_UNICODE)
                    : null;
                $adsetIdInterno = $this->entidadesRepo->upsertAdset(
                    metaAdsetId: (string) $a['id'],
                    campaniaId: $campaniaIdInterno,
                    nombre: (string) ($a['name'] ?? 'Sin nombre'),
                    estado: isset($a['status']) ? (string) $a['status'] : null,
                    segmentacionJson: $segmentacion,
                    presupuestoDiario: isset($a['daily_budget']) ? ((float) $a['daily_budget']) / 100 : null,
                    presupuestoTotal: isset($a['lifetime_budget']) ? ((float) $a['lifetime_budget']) / 100 : null,
                    optimizationGoal: isset($a['optimization_goal']) ? (string) $a['optimization_goal'] : null,
                );
                $mapaAdsetCampania[(string) $a['id']] = $adsetIdInterno;
                $adsets++;
            }

            // 3) Ads
            foreach ($cliente->paginar("act_{$metaAccountId}/ads", [
                'fields' => ['id', 'name', 'adset_id', 'creative', 'status', 'preview_shareable_link'],
                'limit' => 100,
            ]) as $ad) {
                $llamadas++;
                $adsetIdInterno = $mapaAdsetCampania[(string) ($ad['adset_id'] ?? '')] ?? null;
                if ($adsetIdInterno === null) {
                    continue;
                }
                $creativeId = isset($ad['creative']['id']) ? (string) $ad['creative']['id'] : null;
                $this->entidadesRepo->upsertAnuncio(
                    metaAdId: (string) $ad['id'],
                    conjuntoAnunciosId: $adsetIdInterno,
                    nombre: (string) ($ad['name'] ?? 'Sin nombre'),
                    creativeId: $creativeId,
                    tipo: null,
                    thumbnailUrl: null,
                    estado: isset($ad['status']) ? (string) $ad['status'] : null,
                );
                $anuncios++;
            }

            // 4) Insights diarios a nivel ad
            foreach ($cliente->paginar("act_{$metaAccountId}/insights", [
                'level' => 'ad',
                'time_increment' => 1,
                'time_range' => json_encode(['since' => $rangoInicio, 'until' => $rangoFin]),
                'fields' => self::INSIGHTS_FIELDS,
                'limit' => 250,
            ]) as $i) {
                $llamadas++;
                $metaAdId = (string) ($i['ad_id'] ?? '');
                $adIdInterno = $this->entidadesRepo->buscarAnuncioPorMetaId($metaAdId);
                if ($adIdInterno === null) {
                    continue;
                }
                $this->snapshotsRepo->upsert(
                    nivel: 'ad',
                    entidadId: $adIdInterno,
                    metaEntidadId: $metaAdId,
                    fecha: (string) ($i['date_start'] ?? ''),
                    gasto: (float) ($i['spend'] ?? 0),
                    impresiones: (int) ($i['impressions'] ?? 0),
                    alcance: (int) ($i['reach'] ?? 0),
                    frecuencia: isset($i['frequency']) ? (float) $i['frequency'] : null,
                    clicksTotales: (int) ($i['clicks'] ?? 0),
                    clicksEnlace: (int) ($i['inline_link_clicks'] ?? 0),
                    ctr: isset($i['ctr']) ? (float) $i['ctr'] : null,
                    cpc: isset($i['cpc']) ? (float) $i['cpc'] : null,
                    cpm: isset($i['cpm']) ? (float) $i['cpm'] : null,
                    costoPorResultado: isset($i['cost_per_result'][0]['values'][0]['value'])
                        ? (float) $i['cost_per_result'][0]['values'][0]['value']
                        : null,
                    resultados: isset($i['results'][0]['values'][0]['value'])
                        ? (int) $i['results'][0]['values'][0]['value']
                        : null,
                    conversiones: isset($i['conversions'][0]['value'])
                        ? (int) $i['conversions'][0]['value']
                        : null,
                );
                $snapshots++;
            }

            $this->cuentasRepo->marcarSincronizada($cuentaId);
            $this->importacionRepo->marcarCompletada($importacionId, $campanias, $adsets, $anuncios, $snapshots, $llamadas);
        } catch (Throwable $e) {
            $this->importacionRepo->marcarFallida($importacionId, $e->getMessage(), $llamadas);
            throw $e;
        }

        return [
            'importacion_id' => $importacionId,
            'campanias' => $campanias,
            'adsets' => $adsets,
            'anuncios' => $anuncios,
            'snapshots' => $snapshots,
        ];
    }

    private function fechaSolo(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $ts = strtotime((string) $valor);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}
