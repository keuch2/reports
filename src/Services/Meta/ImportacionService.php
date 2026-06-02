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
 * a nivel ad + datos del creative (post, copy, imagen, link). Idempotente vía upserts.
 *
 * Snapshots a nivel ad. Para mostrar resultados por adset/campaña agregamos en SQL
 * sumando los ads que pertenecen al adset/campaña.
 */
final class ImportacionService
{
    /**
     * Fields para insights diarios a nivel ad. `actions` y `action_values`
     * vienen como arrays {action_type, value}; extraemos los que nos importan.
     */
    private const INSIGHTS_FIELDS = [
        'date_start', 'date_stop', 'ad_id', 'spend', 'impressions', 'reach', 'frequency',
        'clicks', 'inline_link_clicks', 'ctr', 'cpc', 'cpm',
        'cost_per_result', 'results', 'conversions',
        'actions',
    ];

    /**
     * action_types que nos interesan, agrupados por la métrica nuestra a la que mapean.
     * Si Meta retorna varios candidatos, sumamos.
     */
    private const ACTION_TYPES = [
        'conversaciones' => [
            'onsite_conversion.messaging_conversation_started_7d',
            'onsite_conversion.total_messaging_connection',
        ],
        'landing_page_views' => [
            'landing_page_view',
        ],
    ];

    /**
     * Fields del creative que nos interesan para mostrar el anuncio al cliente.
     * Meta los devuelve dentro de `creative{...}` cuando se piden en la query del ad.
     */
    private const CREATIVE_FIELDS = [
        'id', 'body', 'title', 'image_url', 'thumbnail_url',
        'object_story_spec', 'call_to_action_type', 'link_url',
        'effective_object_story_id', 'instagram_permalink_url',
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

            // 3) Ads + creative
            $creativeFieldsExpr = 'creative{' . implode(',', self::CREATIVE_FIELDS) . '}';
            foreach ($cliente->paginar("act_{$metaAccountId}/ads", [
                'fields' => [
                    'id', 'name', 'adset_id', 'status', 'preview_shareable_link',
                    $creativeFieldsExpr,
                ],
                'limit' => 100,
            ]) as $ad) {
                $llamadas++;
                $adsetIdInterno = $mapaAdsetCampania[(string) ($ad['adset_id'] ?? '')] ?? null;
                if ($adsetIdInterno === null) {
                    continue;
                }
                $datosCreative = $this->extraerCreative($ad['creative'] ?? [], (string) ($ad['preview_shareable_link'] ?? ''));
                $this->entidadesRepo->upsertAnuncio(
                    metaAdId: (string) $ad['id'],
                    conjuntoAnunciosId: $adsetIdInterno,
                    nombre: (string) ($ad['name'] ?? 'Sin nombre'),
                    creativeId: $datosCreative['creative_id'],
                    tipo: $datosCreative['tipo'],
                    thumbnailUrl: $datosCreative['thumbnail_url'],
                    estado: isset($ad['status']) ? (string) $ad['status'] : null,
                    cuerpo: $datosCreative['cuerpo'],
                    titulo: $datosCreative['titulo'],
                    linkUrl: $datosCreative['link_url'],
                    imageUrl: $datosCreative['image_url'],
                    callToAction: $datosCreative['call_to_action'],
                    permalinkUrl: $datosCreative['permalink_url'],
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
                $actions = is_array($i['actions'] ?? null) ? $i['actions'] : [];
                $conversaciones = $this->sumarActions($actions, self::ACTION_TYPES['conversaciones']);
                $landingViews = $this->sumarActions($actions, self::ACTION_TYPES['landing_page_views']);

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
                    conversaciones: $conversaciones,
                    landingPageViews: $landingViews,
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

    /**
     * Suma los valores de `actions` cuyo `action_type` matchea alguno de los buscados.
     *
     * @param list<array<string,mixed>> $actions
     * @param list<string> $tiposBuscados
     */
    private function sumarActions(array $actions, array $tiposBuscados): ?int
    {
        $total = 0;
        $encontrado = false;
        foreach ($actions as $a) {
            if (in_array((string) ($a['action_type'] ?? ''), $tiposBuscados, true)) {
                $total += (int) ($a['value'] ?? 0);
                $encontrado = true;
            }
        }

        return $encontrado ? $total : null;
    }

    /**
     * Normaliza los datos del creative que vienen anidados.
     *
     * @param array<string,mixed> $creative
     * @return array{creative_id:?string, tipo:?string, thumbnail_url:?string, cuerpo:?string,
     *               titulo:?string, link_url:?string, image_url:?string,
     *               call_to_action:?string, permalink_url:?string}
     */
    private function extraerCreative(array $creative, string $permalinkFallback): array
    {
        $creativeId = isset($creative['id']) ? (string) $creative['id'] : null;
        $thumbnail = $creative['thumbnail_url'] ?? null;
        $image = $creative['image_url'] ?? null;
        $body = $creative['body'] ?? null;
        $title = $creative['title'] ?? null;
        $linkUrl = $creative['link_url'] ?? null;
        $cta = $creative['call_to_action_type'] ?? null;
        $permalink = $creative['effective_object_story_id'] ?? null;

        // object_story_spec puede traer el contenido real del post (link_data, video_data, photo_data).
        $story = $creative['object_story_spec'] ?? [];
        if (is_array($story)) {
            foreach (['link_data', 'video_data', 'photo_data'] as $key) {
                $data = $story[$key] ?? null;
                if (!is_array($data)) {
                    continue;
                }
                $body = $body ?? ($data['message'] ?? null);
                $title = $title ?? ($data['name'] ?? null);
                $linkUrl = $linkUrl ?? ($data['link'] ?? null);
                $image = $image ?? ($data['picture'] ?? null);
                if (isset($data['call_to_action']['type'])) {
                    $cta = $cta ?? $data['call_to_action']['type'];
                }
            }
        }

        // Determinar tipo del anuncio para iconito en la UI.
        $tipo = null;
        if (isset($creative['video_id']) || isset($story['video_data'])) {
            $tipo = 'video';
        } elseif (isset($story['photo_data']) || $image !== null) {
            $tipo = 'image';
        } elseif (isset($story['link_data'])) {
            $tipo = 'link';
        }

        $permalinkUrl = null;
        if ($creative['instagram_permalink_url'] ?? null) {
            $permalinkUrl = (string) $creative['instagram_permalink_url'];
        } elseif ($permalink !== null) {
            // effective_object_story_id es del tipo "pageId_postId"; armamos URL de FB.
            $permalinkUrl = 'https://www.facebook.com/' . str_replace('_', '/posts/', (string) $permalink);
        } elseif ($permalinkFallback !== '') {
            $permalinkUrl = $permalinkFallback;
        }

        return [
            'creative_id' => $creativeId,
            'tipo' => $tipo,
            'thumbnail_url' => $thumbnail !== null ? (string) $thumbnail : null,
            'cuerpo' => $body !== null ? (string) $body : null,
            'titulo' => $title !== null ? (string) $title : null,
            'link_url' => $linkUrl !== null ? (string) $linkUrl : null,
            'image_url' => $image !== null ? (string) $image : null,
            'call_to_action' => $cta !== null ? (string) $cta : null,
            'permalink_url' => $permalinkUrl,
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
