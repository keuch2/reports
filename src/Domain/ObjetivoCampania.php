<?php

declare(strict_types=1);

namespace MisterCo\Reports\Domain;

/**
 * Mapea el `objective` de una campaña de Meta al nombre legible de "Resultados".
 *
 * Meta usa la columna "Resultados" como métrica genérica cuya etiqueta cambia
 * según el objetivo de la campaña. Replicamos esa lógica para que el cliente
 * no vea "Resultados: 55" sino "Conversaciones iniciadas: 55" cuando aplica.
 *
 * Referencia: https://developers.facebook.com/docs/marketing-api/reference/ad-campaign-group/
 * objectives: https://www.facebook.com/business/help/438680330170099
 */
final class ObjetivoCampania
{
    /** @var array<string, string> objective/optimization_goal Meta → etiqueta humana del "Resultado" */
    private const ETIQUETAS = [
        // ODAX (Outcome-Driven Ads Experiences, 2022+) — `objective` de campaña
        'OUTCOME_AWARENESS' => 'Personas alcanzadas',
        'OUTCOME_TRAFFIC' => 'Visitas a destino',
        'OUTCOME_ENGAGEMENT' => 'Interacciones',
        'OUTCOME_LEADS' => 'Clientes potenciales',
        'OUTCOME_APP_PROMOTION' => 'Instalaciones',
        'OUTCOME_SALES' => 'Compras',

        // Objetivos legacy (pre-ODAX) — `objective` de campaña
        'BRAND_AWARENESS' => 'Personas alcanzadas',
        'REACH' => 'Personas alcanzadas',
        'LINK_CLICKS' => 'Clicks al enlace',
        'POST_ENGAGEMENT' => 'Interacciones',
        'PAGE_LIKES' => 'Me gusta de la página',
        'EVENT_RESPONSES' => 'Respuestas al evento',
        'VIDEO_VIEWS' => 'Reproducciones de video',
        'MESSAGES' => 'Conversaciones iniciadas',
        'LEAD_GENERATION' => 'Clientes potenciales',
        'CONVERSIONS' => 'Conversiones',
        'CATALOG_SALES' => 'Compras del catálogo',
        'STORE_VISITS' => 'Visitas a la tienda',
        'APP_INSTALLS' => 'Instalaciones de la app',
        'PRODUCT_CATALOG_SALES' => 'Compras del catálogo',

        // optimization_goal del adset (sobrescribe al objective cuando difiere).
        // Bajo una campaña OUTCOME_LEADS el cliente puede tener adsets que optimizan
        // CONVERSATIONS (mensajes WA) o LEAD_GENERATION (formularios) — la métrica
        // mostrada debe coincidir con lo que el adset realmente optimiza.
        'CONVERSATIONS' => 'Conversaciones iniciadas',
        'OFFSITE_CONVERSIONS' => 'Conversiones',
        'QUALITY_LEAD' => 'Clientes potenciales',
        'LEAD' => 'Clientes potenciales',
        'IMPRESSIONS' => 'Impresiones',
        'THRUPLAY' => 'Reproducciones de video',
        'LANDING_PAGE_VIEWS' => 'Visitas a destino',
        'POST_ENGAGEMENT' => 'Interacciones',
        'REPLIES' => 'Conversaciones iniciadas',
    ];

    /**
     * Nombre humano de la métrica "Resultados" según objetivo.
     * Si el objetivo es desconocido o nulo, devuelve "Resultados".
     */
    public static function nombreResultados(?string $objetivo): string
    {
        if ($objetivo === null || $objetivo === '') {
            return 'Resultados';
        }

        return self::ETIQUETAS[strtoupper($objetivo)] ?? 'Resultados';
    }

    /** Versión abreviada para columnas estrechas de tabla. */
    public static function nombreCortoResultados(?string $objetivo): string
    {
        $largo = self::nombreResultados($objetivo);
        // Algunos nombres son muy largos para columnas; los acortamos.
        $cortos = [
            'Conversaciones iniciadas' => 'Conversaciones',
            'Personas alcanzadas' => 'Alcance',
            'Reproducciones de video' => 'Reprod. video',
            'Compras del catálogo' => 'Compras',
            'Visitas a la tienda' => 'Visitas tienda',
            'Instalaciones de la app' => 'Instalaciones',
            'Me gusta de la página' => 'Likes página',
        ];

        return $cortos[$largo] ?? $largo;
    }

    /**
     * Decide si 'Conversaciones' ya está cubierta por 'Resultados' (objetivo de
     * mensajería), para no mostrarla dos veces.
     */
    public static function conversacionesEsRedundante(?string $objetivo): bool
    {
        return in_array('conversaciones', self::metricasRelevantes($objetivo), true);
    }

    /**
     * Decide si 'Clientes potenciales' ya está cubierta por 'Resultados'
     * (objetivo de leads), para no mostrarla dos veces.
     */
    public static function leadsEsRedundante(?string $objetivo): bool
    {
        return in_array('leads', self::metricasRelevantes($objetivo), true);
    }

    /**
     * Decide si 'Visitas' ya está cubierta por 'Resultados' (objetivo de
     * tráfico), para no mostrarla dos veces.
     */
    public static function visitasEsRedundante(?string $objetivo): bool
    {
        return in_array('visitas', self::metricasRelevantes($objetivo), true);
    }

    /**
     * Métricas de resultado ("conversaciones", "leads", "interacciones",
     * "visitas") que son RELEVANTES para el objetivo de la campaña.
     *
     * Meta suele reportar acciones colaterales que no son el objetivo (p. ej.
     * una campaña de AWARENESS puede registrar 2 conversaciones residuales).
     * Mostrarlas confunde al cliente: el dashboard debe reflejar el objetivo.
     * Este método define qué métricas tienen sentido según el objetivo; el
     * resto se oculta como ruido.
     *
     * Awareness/alcance/impresiones no tienen métrica secundaria: su resultado
     * es el Alcance, que ya se muestra como métrica universal.
     *
     * @return list<'conversaciones'|'leads'|'interacciones'|'visitas'>
     */
    public static function metricasRelevantes(?string $objetivo): array
    {
        $o = $objetivo === null ? '' : strtoupper($objetivo);

        return match ($o) {
            'MESSAGES', 'CONVERSATIONS', 'REPLIES' => ['conversaciones'],
            'OUTCOME_LEADS', 'LEAD_GENERATION', 'QUALITY_LEAD', 'LEAD' => ['leads'],
            'OUTCOME_ENGAGEMENT', 'POST_ENGAGEMENT', 'PAGE_LIKES', 'EVENT_RESPONSES' => ['interacciones'],
            'OUTCOME_TRAFFIC', 'LINK_CLICKS', 'LANDING_PAGE_VIEWS' => ['visitas'],
            // Awareness, reach, impresiones, video, ventas/conversiones, app:
            // no tienen una métrica secundaria de mensajería/lead/visita que
            // mostrar; su resultado es alcance (universal) o se maneja aparte.
            default => [],
        };
    }

    /** ¿La métrica secundaria dada es relevante para el objetivo? */
    public static function metricaEsRelevante(?string $objetivo, string $metrica): bool
    {
        return in_array($metrica, self::metricasRelevantes($objetivo), true);
    }
}
