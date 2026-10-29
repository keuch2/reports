<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Meta;

use DateTimeImmutable;
use DateTimeZone;
use MisterCo\Reports\Repositories\RedesSocialesRepository;
use RuntimeException;
use Throwable;

/**
 * Analíticas orgánicas de Meta: sincroniza las páginas de Facebook (y sus
 * cuentas de Instagram vinculadas) que ve el token, e importa métricas
 * diarias y publicaciones on-demand.
 *
 * - Las consultas usan el token de la página (requisito de Page Insights).
 * - Meta cierra los días en hora del Pacífico: las fechas se calculan en esa zona.
 * - Meta da de baja métricas seguido. Cada métrica de página se pide por
 *   separado y, si Meta la rechaza, se saltea y queda como aviso.
 * - Instagram solo da totales por rango para casi todas sus métricas, así que
 *   se consulta día por día y cada día se guarda apenas llega: si Meta limita
 *   las consultas, lo ya importado queda.
 * - La importación va por tramos (iniciar → un tramo por petición → finalizar)
 *   para que ninguna petición web supere el timeout del servidor.
 */
final class RedesSocialesService
{
    private const ZONA_META = 'America/Los_Angeles';

    /**
     * Versión propia de Graph API para las analíticas orgánicas: las métricas
     * vigentes de Instagram (views) y de páginas (media views) no existen en
     * versiones viejas. La importación de anuncios sigue con META_API_VERSION.
     */
    private const API_VERSION = 'v25.0';

    /** Page Insights acepta hasta 90 días por consulta. */
    private const DIAS_POR_CONSULTA_FACEBOOK = 90;

    /**
     * Días por tramo (una petición web cada uno). Facebook pide pocas consultas
     * por tramo; Instagram hace ~2 por día, así que va de a una semana.
     */
    private const DIAS_POR_TRAMO_FACEBOOK = 90;
    private const DIAS_POR_TRAMO_INSTAGRAM = 7;

    /** Métrica de Page Insights => columna. */
    private const METRICAS_FACEBOOK = [
        'page_media_view' => 'visualizaciones',
        'page_total_media_view_unique' => 'espectadores',
        'page_post_engagements' => 'interacciones',
        'page_daily_follows_unique' => 'nuevos_seguidores',
        'page_daily_unfollows_unique' => 'dejaron_de_seguir',
        'page_views_total' => 'visitas_perfil',
        'page_follows' => 'seguidores_total',
    ];

    /** Métrica de Instagram (total_value por día) => columna. */
    private const METRICAS_INSTAGRAM = [
        'views' => 'visualizaciones',
        'reach' => 'alcance',
        'accounts_engaged' => 'cuentas_interactuaron',
        'total_interactions' => 'interacciones',
        'likes' => 'me_gusta',
        'comments' => 'comentarios',
        'shares' => 'compartidos',
        'saves' => 'guardados',
        'profile_links_taps' => 'toques_enlaces',
    ];

    /** Código de Meta para parámetro/métrica inválida. */
    private const CODIGO_PARAMETRO_INVALIDO = 100;

    /** Códigos de Meta para permiso faltante en el token. */
    private const CODIGOS_SIN_PERMISO = [10, 200];

    private int $llamadas = 0;

    public function __construct(
        private readonly MetaTokenService $tokenService,
        private readonly RedesSocialesRepository $repo,
    ) {
    }

    /**
     * Sincroniza las páginas de Facebook y sus cuentas de Instagram vinculadas.
     *
     * @return array{paginas:int, instagram:int}
     */
    public function sincronizar(): array
    {
        $api = $this->tokenService->cliente(self::API_VERSION);
        $paginas = 0;
        $instagram = 0;
        foreach ($api->paginar('me/accounts', [
            'fields' => [
                'id', 'name', 'category', 'followers_count', 'picture{url}',
                'instagram_business_account{id,username,name,profile_picture_url,followers_count}',
            ],
            'limit' => 100,
        ]) as $p) {
            $paginaId = $this->repo->upsertPagina(
                (string) $p['id'],
                (string) ($p['name'] ?? 'Sin nombre'),
                isset($p['category']) ? (string) $p['category'] : null,
                isset($p['picture']['data']['url']) ? (string) $p['picture']['data']['url'] : null,
                isset($p['followers_count']) ? (int) $p['followers_count'] : null,
            );
            $paginas++;

            $ig = $p['instagram_business_account'] ?? null;
            if (is_array($ig) && isset($ig['id'])) {
                $this->repo->upsertInstagram(
                    (string) $ig['id'],
                    (string) ($ig['username'] ?? $ig['id']),
                    isset($ig['name']) ? (string) $ig['name'] : null,
                    isset($ig['profile_picture_url']) ? (string) $ig['profile_picture_url'] : null,
                    isset($ig['followers_count']) ? (int) $ig['followers_count'] : null,
                    $paginaId,
                );
                $instagram++;
            }
        }

        return ['paginas' => $paginas, 'instagram' => $instagram];
    }

    /**
     * Abre una importación y devuelve los tramos en que se va a hacer. Cada
     * tramo se importa en una petición aparte (importarTramo) para no chocar
     * con el timeout del servidor web en rangos largos.
     *
     * @param 'facebook'|'instagram' $plataforma
     * @return array{importacion_id:int, tramos:list<array{0:string, 1:string}>}
     */
    public function iniciarImportacion(string $plataforma, int $referenciaId, string $desde, string $hasta, int $usuarioId): array
    {
        $this->entidad($plataforma, $referenciaId);
        $diasPorTramo = $plataforma === 'facebook' ? self::DIAS_POR_TRAMO_FACEBOOK : self::DIAS_POR_TRAMO_INSTAGRAM;

        return [
            'importacion_id' => $this->repo->crearImportacion($plataforma, $referenciaId, $usuarioId, $desde, $hasta),
            'tramos' => $this->tramos($desde, $hasta, $diasPorTramo),
        ];
    }

    /**
     * Importa las métricas diarias de un tramo y acumula días y avisos en la
     * importación. Si falla, la importación queda fallida y lo ya guardado se
     * conserva.
     *
     * @return array{dias:int, avisos:list<string>}
     */
    public function importarTramo(int $importacionId, string $desde, string $hasta): array
    {
        $imp = $this->importacionEnCurso($importacionId);
        if ($desde < $imp['rango_inicio'] || $hasta > $imp['rango_fin'] || $desde > $hasta) {
            throw new RuntimeException('El tramo está fuera del rango de la importación.');
        }
        $avisos = $imp['avisos'];

        return $this->conRegistroDeFallo($importacionId, function () use ($imp, $desde, $hasta, &$avisos, $importacionId): array {
            $this->llamadas = 0;
            $entidad = $this->entidad((string) $imp['plataforma'], (int) $imp['referencia_id']);
            $dias = $imp['plataforma'] === 'facebook'
                ? $this->metricasFacebook($entidad, $desde, $hasta, $avisos)
                : $this->metricasInstagram($entidad, $desde, $hasta, $avisos);
            $this->repo->registrarTramo($importacionId, $dias, $this->llamadas, $avisos);

            return ['dias' => $dias, 'avisos' => array_values($avisos)];
        });
    }

    /**
     * Último paso: publicaciones del rango completo y seguidores actuales.
     * Cierra la importación como completada.
     *
     * @return array{importacion_id:int, dias:int, publicaciones:int, avisos:list<string>}
     */
    public function finalizarImportacion(int $importacionId): array
    {
        $imp = $this->importacionEnCurso($importacionId);
        $avisos = $imp['avisos'];

        return $this->conRegistroDeFallo($importacionId, function () use ($imp, &$avisos, $importacionId): array {
            $this->llamadas = 0;
            $entidad = $this->entidad((string) $imp['plataforma'], (int) $imp['referencia_id']);
            [$desde, $hasta] = [(string) $imp['rango_inicio'], (string) $imp['rango_fin']];
            $publicaciones = $imp['plataforma'] === 'facebook'
                ? $this->publicacionesFacebook($entidad, $desde, $hasta, $avisos)
                : $this->publicacionesInstagram($entidad, $desde, $hasta);

            $dias = (int) $imp['dias_importados'];
            $mensajes = array_values($avisos);
            if ($dias === 0) {
                $mensajes[] = "Meta no devolvió métricas para {$desde} → {$hasta}. Las páginas necesitan al menos 100 seguidores y Meta solo guarda los últimos 2 años.";
            }
            $this->repo->finalizarImportacion($importacionId, true, $publicaciones, $this->llamadas, $mensajes === [] ? null : implode("\n", $mensajes));

            return ['importacion_id' => $importacionId, 'dias' => $dias, 'publicaciones' => $publicaciones, 'avisos' => $mensajes];
        });
    }

    /**
     * Métricas diarias de una página en el rango. Cada métrica se pide por
     * separado: si Meta rechaza una (dada de baja), se saltea con aviso.
     *
     * @param array<string,mixed> $pagina
     * @param array<string,string> $avisos
     */
    private function metricasFacebook(array $pagina, string $desde, string $hasta, array &$avisos): int
    {
        $pageId = (string) $pagina['page_id'];
        $api = $this->clienteDePagina($pageId);

        $porFecha = [];
        foreach ($this->tramos($desde, $hasta, self::DIAS_POR_CONSULTA_FACEBOOK) as [$inicio, $fin]) {
            foreach (self::METRICAS_FACEBOOK as $metrica => $columna) {
                if (isset($avisos[$metrica])) {
                    continue;
                }
                try {
                    $resp = $this->get($api, "{$pageId}/insights", [
                        'metric' => $metrica,
                        'period' => 'day',
                        'since' => $this->inicioDelDia($inicio),
                        'until' => $this->inicioDelDia($fin, 1),
                    ]);
                } catch (MetaApiException $e) {
                    if ($e instanceof MetaRateLimitException || $e->metaCode !== self::CODIGO_PARAMETRO_INVALIDO) {
                        throw $e;
                    }
                    $avisos[$metrica] = "Meta no devolvió «{$metrica}»: {$e->getMessage()}";
                    continue;
                }
                foreach ($resp['data'][0]['values'] ?? [] as $v) {
                    $fecha = $this->fechaDeFinDeDia((string) ($v['end_time'] ?? ''));
                    if ($fecha !== null && $fecha >= $desde && $fecha <= $hasta) {
                        $porFecha[$fecha][$columna] = $this->valorEntero($v['value'] ?? null);
                    }
                }
            }
        }
        foreach ($porFecha as $fecha => $valores) {
            $this->repo->guardarMetricasDia('facebook', (int) $pagina['id'], $fecha, $valores);
        }

        return count($porFecha);
    }

    /**
     * Métricas diarias de una cuenta de Instagram, día por día (casi todas
     * solo existen como total del rango pedido). Cada día se guarda apenas llega.
     *
     * @param array<string,mixed> $cuenta
     * @param array<string,string> $avisos
     */
    private function metricasInstagram(array $cuenta, string $desde, string $hasta, array &$avisos): int
    {
        $igId = (string) $cuenta['ig_user_id'];
        $api = $this->clienteDePagina((string) $cuenta['page_id']);
        // Las métricas que Meta ya rechazó en tramos anteriores no se vuelven a pedir.
        $metricas = array_diff_key(self::METRICAS_INSTAGRAM, $avisos);
        $dias = 0;

        for ($fecha = $desde; $fecha <= $hasta; $fecha = date('Y-m-d', strtotime($fecha . ' +1 day'))) {
            $rangoDia = ['period' => 'day', 'since' => $this->inicioDelDia($fecha), 'until' => $this->inicioDelDia($fecha, 1)];
            $valores = $this->metricasInstagramDelDia($api, $igId, $rangoDia, $metricas, $avisos);

            // Seguidores ganados/perdidos: requiere el desglose por tipo (consulta aparte).
            // Meta lo omite en cuentas con menos de 100 seguidores.
            if (!isset($avisos['follows_and_unfollows'])) {
                try {
                    $resp = $this->get($api, "{$igId}/insights", $rangoDia + [
                        'metric' => 'follows_and_unfollows', 'metric_type' => 'total_value', 'breakdown' => 'follow_type',
                    ]);
                    foreach ($resp['data'][0]['total_value']['breakdowns'][0]['results'] ?? [] as $r) {
                        $tipo = (string) ($r['dimension_values'][0] ?? '');
                        if ($tipo === 'FOLLOWER') {
                            $valores['nuevos_seguidores'] = (int) $r['value'];
                        } elseif ($tipo === 'NON_FOLLOWER') {
                            $valores['dejaron_de_seguir'] = (int) $r['value'];
                        }
                    }
                } catch (MetaApiException $e) {
                    if ($e instanceof MetaRateLimitException || $e->metaCode !== self::CODIGO_PARAMETRO_INVALIDO) {
                        throw $e;
                    }
                    $avisos['follows_and_unfollows'] = "Meta no devolvió seguidores ganados/perdidos: {$e->getMessage()}";
                }
            }

            if ($valores !== []) {
                $this->repo->guardarMetricasDia('instagram', (int) $cuenta['id'], $fecha, $valores);
                $dias++;
            }
        }

        return $dias;
    }

    /**
     * Publicaciones de la página en el rango y seguidores actuales. Leer
     * publicaciones exige además pages_read_user_content: sin ese permiso
     * queda un aviso y la importación se completa igual.
     *
     * @param array<string,mixed> $pagina
     * @param array<string,string> $avisos
     */
    private function publicacionesFacebook(array $pagina, string $desde, string $hasta, array &$avisos): int
    {
        $pageId = (string) $pagina['page_id'];
        $api = $this->clienteDePagina($pageId);
        $publicaciones = 0;
        try {
            foreach ($api->paginar("{$pageId}/published_posts", [
                'fields' => [
                    'id', 'message', 'created_time', 'permalink_url', 'full_picture', 'status_type', 'shares',
                    'reactions.summary(total_count).limit(0)', 'comments.summary(total_count).limit(0)',
                ],
                'since' => $this->inicioDelDia($desde),
                'until' => $this->inicioDelDia($hasta, 1),
                'limit' => 50,
            ]) as $post) {
                $this->repo->guardarPublicacion(
                    'facebook', (int) $pagina['id'], (string) $post['id'], $this->fechaHoraLocal((string) $post['created_time']),
                    isset($post['status_type']) ? (string) $post['status_type'] : null,
                    isset($post['message']) ? (string) $post['message'] : null,
                    isset($post['full_picture']) ? (string) $post['full_picture'] : null,
                    isset($post['permalink_url']) ? (string) $post['permalink_url'] : null,
                    (int) ($post['reactions']['summary']['total_count'] ?? 0),
                    (int) ($post['comments']['summary']['total_count'] ?? 0),
                    (int) ($post['shares']['count'] ?? 0),
                );
                $publicaciones++;
            }
        } catch (MetaApiException $e) {
            if ($e instanceof MetaRateLimitException || !in_array($e->metaCode, self::CODIGOS_SIN_PERMISO, true)) {
                throw $e;
            }
            $avisos['publicaciones'] = 'Meta no permitió leer las publicaciones de la página (falta el permiso pages_read_user_content): ' . $e->getMessage();
        }
        $this->llamadas++;

        $datos = $this->get($api, $pageId, ['fields' => ['followers_count']]);
        if (isset($datos['followers_count'])) {
            $this->repo->actualizarSeguidores('facebook', (int) $pagina['id'], (int) $datos['followers_count']);
        }

        return $publicaciones;
    }

    /**
     * Publicaciones de la cuenta en el rango y seguidores actuales. /media
     * viene de la más nueva a la más vieja: se corta al pasar el inicio.
     *
     * @param array<string,mixed> $cuenta
     */
    private function publicacionesInstagram(array $cuenta, string $desde, string $hasta): int
    {
        $igId = (string) $cuenta['ig_user_id'];
        $api = $this->clienteDePagina((string) $cuenta['page_id']);
        $publicaciones = 0;
        foreach ($api->paginar("{$igId}/media", [
            'fields' => ['id', 'caption', 'media_type', 'media_product_type', 'media_url', 'thumbnail_url', 'permalink', 'timestamp', 'like_count', 'comments_count'],
            'limit' => 50,
        ]) as $m) {
            $publicadoEn = $this->fechaHoraLocal((string) $m['timestamp']);
            if (substr($publicadoEn, 0, 10) > $hasta) {
                continue;
            }
            if (substr($publicadoEn, 0, 10) < $desde) {
                break;
            }
            $this->repo->guardarPublicacion(
                'instagram', (int) $cuenta['id'], (string) $m['id'], $publicadoEn,
                (string) ($m['media_product_type'] ?? $m['media_type'] ?? ''),
                isset($m['caption']) ? (string) $m['caption'] : null,
                isset($m['thumbnail_url']) ? (string) $m['thumbnail_url'] : (isset($m['media_url']) ? (string) $m['media_url'] : null),
                isset($m['permalink']) ? (string) $m['permalink'] : null,
                (int) ($m['like_count'] ?? 0),
                (int) ($m['comments_count'] ?? 0),
                0,
            );
            $publicaciones++;
        }
        $this->llamadas++;

        $datos = $this->get($api, $igId, ['fields' => ['followers_count']]);
        if (isset($datos['followers_count'])) {
            $this->repo->actualizarSeguidores('instagram', (int) $cuenta['id'], (int) $datos['followers_count']);
        }

        return $publicaciones;
    }

    /**
     * Métricas total_value de un día. Si Meta rechaza el grupo por una métrica
     * dada de baja, se prueban de a una y las inválidas se quitan para los días
     * siguientes.
     *
     * @param array<string, int|string> $rangoDia
     * @param array<string, string> $metricas métrica => columna (se ajusta por referencia)
     * @param array<string, string> $avisos
     * @return array<string, int>
     */
    private function metricasInstagramDelDia(MetaApiClient $api, string $igId, array $rangoDia, array &$metricas, array &$avisos): array
    {
        $consulta = fn (array $nombres): array => $this->get($api, "{$igId}/insights", $rangoDia + [
            'metric' => implode(',', $nombres), 'metric_type' => 'total_value',
        ]);
        try {
            $resp = $consulta(array_keys($metricas));
        } catch (MetaApiException $e) {
            if ($e instanceof MetaRateLimitException || $e->metaCode !== self::CODIGO_PARAMETRO_INVALIDO) {
                throw $e;
            }
            $resp = ['data' => []];
            foreach (array_keys($metricas) as $metrica) {
                try {
                    $resp['data'] = array_merge($resp['data'], $consulta([$metrica])['data'] ?? []);
                } catch (MetaApiException $e2) {
                    if ($e2 instanceof MetaRateLimitException || $e2->metaCode !== self::CODIGO_PARAMETRO_INVALIDO) {
                        throw $e2;
                    }
                    $avisos[$metrica] = "Meta no devolvió «{$metrica}»: {$e2->getMessage()}";
                    unset($metricas[$metrica]);
                }
            }
        }

        $valores = [];
        foreach ($resp['data'] ?? [] as $d) {
            $columna = $metricas[(string) ($d['name'] ?? '')] ?? null;
            if ($columna !== null && isset($d['total_value']['value'])) {
                $valores[$columna] = (int) $d['total_value']['value'];
            }
        }

        return $valores;
    }

    /**
     * Página o cuenta de Instagram a importar (la de Instagram con el page_id
     * de su página, cuyo token se usa para consultarla).
     *
     * @return array<string,mixed>
     */
    private function entidad(string $plataforma, int $id): array
    {
        if ($plataforma === 'facebook') {
            return $this->repo->buscarPagina($id) ?? throw new RuntimeException("Página {$id} no existe.");
        }
        $cuenta = $this->repo->buscarInstagram($id) ?? throw new RuntimeException("Cuenta de Instagram {$id} no existe.");
        if ($cuenta['page_id'] === null) {
            throw new RuntimeException('La cuenta de Instagram no tiene una página de Facebook vinculada: volvé a sincronizar.');
        }

        return $cuenta;
    }

    /** @return array<string,mixed> */
    private function importacionEnCurso(int $importacionId): array
    {
        $imp = $this->repo->buscarImportacion($importacionId) ?? throw new RuntimeException("Importación {$importacionId} no existe.");
        if ($imp['estado'] !== 'en_curso') {
            throw new RuntimeException("La importación #{$importacionId} ya terminó ({$imp['estado']}).");
        }

        return $imp;
    }

    /**
     * Ejecuta un paso de la importación; si falla, la marca como fallida (lo
     * ya guardado queda) y relanza el error.
     *
     * @template T
     * @param callable(): T $paso
     * @return T
     */
    private function conRegistroDeFallo(int $importacionId, callable $paso): mixed
    {
        try {
            return $paso();
        } catch (MetaRateLimitException $e) {
            $this->repo->finalizarImportacion($importacionId, false, 0, $this->llamadas,
                'Meta limitó la cantidad de consultas. Los días ya importados quedaron guardados; importá el resto más tarde. ' . $e->getMessage());
            throw $e;
        } catch (Throwable $e) {
            $this->repo->finalizarImportacion($importacionId, false, 0, $this->llamadas, $e->getMessage());
            throw $e;
        }
    }

    /** Cliente de la API con el token de la página (lo exige Page Insights). */
    private function clienteDePagina(string $pageId): MetaApiClient
    {
        $datos = $this->get($this->tokenService->cliente(self::API_VERSION), $pageId, ['fields' => ['access_token']]);
        if (!isset($datos['access_token'])) {
            throw new MetaApiException('Meta no devolvió el token de la página. Reconectá Meta con los permisos de páginas e Instagram.');
        }

        return $this->tokenService->clienteCon((string) $datos['access_token'], self::API_VERSION);
    }

    /**
     * @param array<string, scalar|array<int,string>> $query
     * @return array<string, mixed>
     */
    private function get(MetaApiClient $api, string $endpoint, array $query): array
    {
        $this->llamadas++;

        return $api->get($endpoint, $query);
    }

    /**
     * Divide [desde, hasta] en tramos de hasta $dias días.
     *
     * @return list<array{0:string, 1:string}>
     */
    private function tramos(string $desde, string $hasta, int $dias): array
    {
        $tramos = [];
        for ($inicio = $desde; $inicio <= $hasta; $inicio = date('Y-m-d', strtotime($fin . ' +1 day'))) {
            $fin = min($hasta, date('Y-m-d', strtotime($inicio . ' +' . ($dias - 1) . ' days')));
            $tramos[] = [$inicio, $fin];
        }

        return $tramos;
    }

    /** Unix del inicio del día (hora del Pacífico), desplazado $mas días. */
    private function inicioDelDia(string $fecha, int $mas = 0): int
    {
        return (new DateTimeImmutable($fecha . ' 00:00:00', new DateTimeZone(self::ZONA_META)))
            ->modify("+{$mas} day")
            ->getTimestamp();
    }

    /** Meta informa cada día con el end_time del cierre: el día es el anterior (hora del Pacífico). */
    private function fechaDeFinDeDia(string $endTime): ?string
    {
        if ($endTime === '') {
            return null;
        }

        return (new DateTimeImmutable($endTime))
            ->setTimezone(new DateTimeZone(self::ZONA_META))
            ->modify('-1 day')
            ->format('Y-m-d');
    }

    /** Fecha y hora ISO de Meta convertida a la zona horaria de la app. */
    private function fechaHoraLocal(string $iso): string
    {
        return (new DateTimeImmutable($iso))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s');
    }

    /** Algunas métricas llegan como objeto (desglose): se suman sus valores. */
    private function valorEntero(mixed $valor): ?int
    {
        if (is_array($valor)) {
            return (int) array_sum(array_map('floatval', $valor));
        }

        return $valor === null ? null : (int) $valor;
    }
}
