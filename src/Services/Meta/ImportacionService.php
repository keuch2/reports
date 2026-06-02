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
     * Fields para insights diarios a nivel ad. `actions` y `cost_per_action_type`
     * vienen como arrays {action_type, value}; extraemos los que nos importan
     * y guardamos el raw en metricas_extendidas para futuras métricas.
     */
    private const INSIGHTS_FIELDS = [
        'date_start', 'date_stop', 'ad_id', 'spend', 'impressions', 'reach', 'frequency',
        'clicks', 'inline_link_clicks', 'ctr', 'cpc', 'cpm',
        'cost_per_result', 'results', 'conversions',
        'actions', 'cost_per_action_type',
    ];

    /**
     * action_types de interés agrupados por la métrica nuestra a la que mapean.
     * Si Meta retorna varios candidatos coincidentes, los sumamos.
     *
     * Convenciones Meta a 2026:
     * - Mensajes (WhatsApp/Messenger click-to-message):
     *     onsite_conversion.messaging_conversation_started_7d  (estándar actual)
     *     onsite_conversion.total_messaging_connection         (alias antiguo)
     *     onsite_conversion.messaging_first_reply              (alternativa)
     * - Landing page view: landing_page_view
     * - Lead (form): leadgen.other, leadgen_grouped, lead, onsite_conversion.lead_grouped
     */
    private const ACTION_TYPES = [
        'conversaciones' => [
            'onsite_conversion.messaging_conversation_started_7d',
            'onsite_conversion.total_messaging_connection',
            'onsite_conversion.messaging_first_reply',
        ],
        'landing_page_views' => [
            'landing_page_view',
        ],
        'leads' => [
            'lead',
            'leadgen_grouped',
            'leadgen.other',
            'onsite_conversion.lead_grouped',
        ],
    ];

    /**
     * action_types para extraer costos desde cost_per_action_type.
     * Mismos candidatos, primer match gana (no sumamos costos).
     */
    private const COST_ACTION_TYPES_CONVERSACION = [
        'onsite_conversion.messaging_conversation_started_7d',
        'onsite_conversion.total_messaging_connection',
        'onsite_conversion.messaging_first_reply',
    ];

    /**
     * Fields del creative que nos interesan para mostrar el anuncio al cliente.
     * Meta los devuelve dentro de `creative{...}` cuando se piden en la query del ad.
     */
    private const CREATIVE_FIELDS = [
        'id', 'body', 'title', 'image_url', 'thumbnail_url', 'image_hash',
        'object_story_spec', 'asset_feed_spec', 'call_to_action_type', 'link_url',
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
     * Ejecuta una importación. Si $metaCampaignIdsFiltro está vacío, importa toda la cuenta.
     * Si tiene valores, solo procesa esas campañas + sus adsets/ads/insights.
     *
     * @param list<string> $metaCampaignIdsFiltro IDs de campañas Meta a procesar (vacío = todas).
     * @return array{importacion_id:int, campanias:int, adsets:int, anuncios:int, snapshots:int}
     */
    public function importar(
        int $cuentaId,
        string $rangoInicio,
        string $rangoFin,
        int $usuarioId,
        array $metaCampaignIdsFiltro = [],
    ): array {
        $cuenta = $this->cuentasRepo->buscarPorId($cuentaId);
        if ($cuenta === null) {
            throw new RuntimeException("Cuenta publicitaria {$cuentaId} no existe.");
        }

        $metaAccountId = (string) $cuenta['meta_account_id'];
        $importacionId = $this->importacionRepo->crear($cuentaId, $usuarioId, $rangoInicio, $rangoFin);

        // Normalizamos el filtro a Set<string> para lookups rápidos.
        $filtroActivo = $metaCampaignIdsFiltro !== [];
        $filtroSet = array_flip(array_map('strval', $metaCampaignIdsFiltro));

        $cliente = $this->tokenService->cliente();
        $llamadas = 0;
        $campanias = 0;
        $adsets = 0;
        $anuncios = 0;
        $snapshots = 0;
        // Cache de hash → URL HD para reducir llamadas a /adimages.
        $cacheHashUrl = [];
        $warnings = [];

        try {
            // 1) Campañas. Si hay filtro, usamos endpoint /campaigns con filtering server-side
            //    (más eficiente que descargar todas y filtrar localmente).
            $campaniasQuery = [
                'fields' => ['id', 'name', 'objective', 'status', 'start_time', 'stop_time',
                             'daily_budget', 'lifetime_budget'],
                'limit' => 100,
            ];
            if ($filtroActivo) {
                $campaniasQuery['filtering'] = json_encode([[
                    'field' => 'campaign.id',
                    'operator' => 'IN',
                    'value' => array_keys($filtroSet),
                ]]);
            }
            foreach ($cliente->paginar("act_{$metaAccountId}/campaigns", $campaniasQuery) as $c) {
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

            if ($filtroActivo && $campanias === 0) {
                throw new RuntimeException('Las campañas seleccionadas no se encontraron en Meta. ¿Cambiaron de ID?');
            }

            // 2) Adsets — filtramos por las mismas campañas si hay filtro.
            $adsetsQuery = [
                'fields' => ['id', 'name', 'campaign_id', 'status', 'targeting',
                             'daily_budget', 'lifetime_budget', 'optimization_goal'],
                'limit' => 100,
            ];
            if ($filtroActivo) {
                $adsetsQuery['filtering'] = json_encode([[
                    'field' => 'campaign.id',
                    'operator' => 'IN',
                    'value' => array_keys($filtroSet),
                ]]);
            }
            $mapaAdsetCampania = [];
            foreach ($cliente->paginar("act_{$metaAccountId}/adsets", $adsetsQuery) as $a) {
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

            // 3) Ads + creative — filtramos por las mismas campañas si hay filtro.
            $creativeFieldsExpr = 'creative{' . implode(',', self::CREATIVE_FIELDS) . '}';
            $adsQuery = [
                'fields' => [
                    'id', 'name', 'adset_id', 'campaign_id', 'status', 'preview_shareable_link',
                    $creativeFieldsExpr,
                ],
                'limit' => 100,
            ];
            if ($filtroActivo) {
                $adsQuery['filtering'] = json_encode([[
                    'field' => 'campaign.id',
                    'operator' => 'IN',
                    'value' => array_keys($filtroSet),
                ]]);
            }
            foreach ($cliente->paginar("act_{$metaAccountId}/ads", $adsQuery) as $ad) {
                $llamadas++;
                $adsetIdInterno = $mapaAdsetCampania[(string) ($ad['adset_id'] ?? '')] ?? null;
                if ($adsetIdInterno === null) {
                    continue;
                }
                $creativeAnidado = $ad['creative'] ?? [];
                $datosCreative = $this->extraerCreative(is_array($creativeAnidado) ? $creativeAnidado : [], (string) ($ad['preview_shareable_link'] ?? ''));

                // Fallback: si el creative anidado vino sin imagen ni copy útil pero tenemos
                // un creative_id, vamos a buscarlo directamente.
                // Casos comunes: ads con object_story_id (post existente), asset feed dinámico,
                // creatives con permisos limitados.
                if ($this->creativeIncompleto($datosCreative) && $datosCreative['creative_id'] !== null) {
                    try {
                        $creativeFull = $cliente->get($datosCreative['creative_id'], [
                            'fields' => self::CREATIVE_FIELDS,
                        ]);
                        $llamadas++;
                        $datosCreative = $this->extraerCreative($creativeFull, (string) ($ad['preview_shareable_link'] ?? ''));

                        // Segundo fallback: si tenemos un effective_object_story_id (post de FB),
                        // intentamos traer del post el message + full_picture + permalink.
                        if ($this->creativeIncompleto($datosCreative)) {
                            $storyId = (string) ($creativeFull['effective_object_story_id'] ?? '');
                            if ($storyId !== '') {
                                try {
                                    $post = $cliente->get($storyId, [
                                        'fields' => ['message', 'full_picture', 'picture', 'permalink_url', 'attachments{description,title,url}'],
                                    ]);
                                    $llamadas++;
                                    $datosCreative = $this->complementarConPost($datosCreative, $post);
                                } catch (\Throwable) {
                                    // si el post no es accesible (page sin permisos), seguimos con lo que tenemos
                                }
                            }
                        }
                    } catch (\Throwable) {
                        // si el creative no es accesible por permisos, dejamos lo que ya teníamos
                    }
                }

                // Si tenemos image_hash, resolverlo a la URL HD desde /act_{id}/adimages.
                // image_url del creative suele ser thumbnail; adimages devuelve la imagen original.
                if (!empty($datosCreative['image_hash'])) {
                    $hash = (string) $datosCreative['image_hash'];
                    if (!array_key_exists($hash, $cacheHashUrl)) {
                        try {
                            $resp = $cliente->get("act_{$metaAccountId}/adimages", [
                                'fields' => ['url', 'permalink_url', 'width', 'height'],
                                'hashes' => json_encode([$hash]),
                            ]);
                            $llamadas++;
                            $primero = $resp['data'][0] ?? null;
                            $cacheHashUrl[$hash] = is_array($primero) && !empty($primero['url'])
                                ? (string) $primero['url'] : null;
                        } catch (\Throwable) {
                            $cacheHashUrl[$hash] = null;
                        }
                    }
                    if ($cacheHashUrl[$hash] !== null) {
                        $datosCreative['image_url'] = $cacheHashUrl[$hash];
                    }
                }

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

            // 4) Insights diarios a nivel ad — filtramos por las mismas campañas si hay filtro.
            $insightsQuery = [
                'level' => 'ad',
                'time_increment' => 1,
                'time_range' => json_encode(['since' => $rangoInicio, 'until' => $rangoFin]),
                'fields' => self::INSIGHTS_FIELDS,
                'limit' => 250,
            ];
            if ($filtroActivo) {
                $insightsQuery['filtering'] = json_encode([[
                    'field' => 'campaign.id',
                    'operator' => 'IN',
                    'value' => array_keys($filtroSet),
                ]]);
            }
            $insightsAntes = $snapshots;
            foreach ($cliente->paginar("act_{$metaAccountId}/insights", $insightsQuery) as $i) {
                $llamadas++;
                $metaAdId = (string) ($i['ad_id'] ?? '');
                $adIdInterno = $this->entidadesRepo->buscarAnuncioPorMetaId($metaAdId);
                if ($adIdInterno === null) {
                    continue;
                }
                $actions = is_array($i['actions'] ?? null) ? $i['actions'] : [];
                $costPerAction = is_array($i['cost_per_action_type'] ?? null) ? $i['cost_per_action_type'] : [];

                $conversaciones = $this->sumarActions($actions, self::ACTION_TYPES['conversaciones']);
                $landingViews = $this->sumarActions($actions, self::ACTION_TYPES['landing_page_views']);
                $leads = $this->sumarActions($actions, self::ACTION_TYPES['leads']);
                $costoPorConv = $this->primerCostoPorAction($costPerAction, self::COST_ACTION_TYPES_CONVERSACION);

                // Guardamos los arrays raw en metricas_extendidas para no perder nada.
                $extendidas = ['actions' => $actions, 'cost_per_action_type' => $costPerAction];

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
                    leads: $leads,
                    costoPorConversacion: $costoPorConv,
                    metricasExtendidas: $extendidas,
                );
                $snapshots++;
            }

            // Si insights no devolvió nada y sí había ads, es probable que Meta no tenga
            // datos para el rango (cuenta sin actividad, mala TZ) o que el token no tenga
            // permisos sobre insights (devuelve data:[] en vez de 403 en algunas configs).
            // Lo dejamos como warning en error_mensaje para que el admin lo vea sin marcar
            // como "fallida".
            if ($snapshots === $insightsAntes && $anuncios > 0) {
                $warnings[] = "Meta no devolvió métricas para {$anuncios} anuncios en el rango {$rangoInicio} → {$rangoFin}. Posibles causas: la cuenta no tuvo actividad en este rango, los anuncios están desactivados, o el token no tiene permisos de insights sobre esta cuenta. Probá un rango anterior o revisá los permisos en Business Manager.";
            }

            $this->cuentasRepo->marcarSincronizada($cuentaId);
            $this->importacionRepo->marcarCompletada(
                $importacionId, $campanias, $adsets, $anuncios, $snapshots, $llamadas,
                $warnings === [] ? null : implode("\n\n", $warnings)
            );
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
     * Devuelve el primer costo encontrado para action_types buscados (no sumamos costos).
     *
     * @param list<array<string,mixed>> $costPerActionType
     * @param list<string> $tiposBuscados
     */
    private function primerCostoPorAction(array $costPerActionType, array $tiposBuscados): ?float
    {
        foreach ($costPerActionType as $c) {
            if (in_array((string) ($c['action_type'] ?? ''), $tiposBuscados, true)) {
                return (float) ($c['value'] ?? 0);
            }
        }

        return null;
    }

    /**
     * Normaliza los datos del creative que vienen anidados.
     *
     * @param array<string,mixed> $creative
     * @return array{creative_id:?string, tipo:?string, thumbnail_url:?string, cuerpo:?string,
     *               titulo:?string, link_url:?string, image_url:?string, image_hash:?string,
     *               call_to_action:?string, permalink_url:?string}
     */
    private function extraerCreative(array $creative, string $permalinkFallback): array
    {
        $creativeId = isset($creative['id']) ? (string) $creative['id'] : null;
        $thumbnail = $creative['thumbnail_url'] ?? null;
        $image = $creative['image_url'] ?? null;
        $imageHash = $creative['image_hash'] ?? null;
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
                $imageHash = $imageHash ?? ($data['image_hash'] ?? null);
                if (isset($data['call_to_action']['type'])) {
                    $cta = $cta ?? $data['call_to_action']['type'];
                }
            }
        }

        // asset_feed_spec (anuncios dinámicos): images[].hash trae el hash en mejor calidad.
        $assetFeed = $creative['asset_feed_spec'] ?? null;
        if (is_array($assetFeed) && $imageHash === null) {
            $firstImg = $assetFeed['images'][0] ?? null;
            if (is_array($firstImg)) {
                $imageHash = $firstImg['hash'] ?? null;
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
            'image_hash' => $imageHash !== null ? (string) $imageHash : null,
            'call_to_action' => $cta !== null ? (string) $cta : null,
            'permalink_url' => $permalinkUrl,
        ];
    }

    /**
     * Heurística para detectar si el creative llegó sin lo mínimo visible (imagen + copy).
     * Si está incompleto, vale la pena hacer fetch extra al creative/post.
     *
     * @param array<string,mixed> $c
     */
    private function creativeIncompleto(array $c): bool
    {
        $tieneVisual = !empty($c['image_url']) || !empty($c['thumbnail_url']);
        $tieneCopy = !empty($c['cuerpo']) || !empty($c['titulo']);

        return !$tieneVisual || !$tieneCopy;
    }

    /**
     * Complementa los datos del creative con los del post original de FB cuando
     * el creative apunta a un post existente (object_story_id) y los fields anidados
     * vienen vacíos.
     *
     * @param array{creative_id:?string, tipo:?string, thumbnail_url:?string, cuerpo:?string,
     *               titulo:?string, link_url:?string, image_url:?string, image_hash:?string,
     *               call_to_action:?string, permalink_url:?string} $datos
     * @param array<string,mixed> $post
     * @return array{creative_id:?string, tipo:?string, thumbnail_url:?string, cuerpo:?string,
     *                titulo:?string, link_url:?string, image_url:?string, image_hash:?string,
     *                call_to_action:?string, permalink_url:?string}
     */
    private function complementarConPost(array $datos, array $post): array
    {
        if (empty($datos['cuerpo']) && !empty($post['message'])) {
            $datos['cuerpo'] = (string) $post['message'];
        }
        // full_picture es siempre la versión HD del post; picture es un thumbnail. Preferir full_picture
        // incluso si ya teníamos image_url (los thumbnails del creative suelen ser de 320px).
        $fullPicture = $post['full_picture'] ?? null;
        if ($fullPicture !== null) {
            $datos['image_url'] = (string) $fullPicture;
        } elseif (empty($datos['image_url']) && !empty($post['picture'])) {
            $datos['image_url'] = (string) $post['picture'];
        }
        if (empty($datos['thumbnail_url']) && ($fullPicture !== null || !empty($post['picture']))) {
            $datos['thumbnail_url'] = (string) ($fullPicture ?? $post['picture']);
        }
        if (empty($datos['permalink_url']) && !empty($post['permalink_url'])) {
            $datos['permalink_url'] = (string) $post['permalink_url'];
        }
        // attachments suele tener title/url de un link adjunto
        $att = $post['attachments']['data'][0] ?? null;
        if (is_array($att)) {
            if (empty($datos['titulo']) && !empty($att['title'])) {
                $datos['titulo'] = (string) $att['title'];
            }
            if (empty($datos['link_url']) && !empty($att['url'])) {
                $datos['link_url'] = (string) $att['url'];
            }
        }
        // si llegó cualquier visual, marcamos tipo
        if ($datos['tipo'] === null && (!empty($datos['image_url']) || !empty($datos['thumbnail_url']))) {
            $datos['tipo'] = 'image';
        }

        return $datos;
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
