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
     * Decide si mostrar 'Conversaciones' como métrica separada cuando ya está
     * cubierta por 'Resultados' (objetivo MESSAGES). Evita duplicar info.
     */
    public static function conversacionesEsRedundante(?string $objetivo): bool
    {
        return $objetivo !== null && strtoupper($objetivo) === 'MESSAGES';
    }

    /**
     * Decide si mostrar 'Clientes potenciales' como métrica separada cuando
     * ya está cubierta por 'Resultados' (objetivo LEAD_GENERATION / OUTCOME_LEADS).
     */
    public static function leadsEsRedundante(?string $objetivo): bool
    {
        return $objetivo !== null && in_array(
            strtoupper($objetivo),
            ['LEAD_GENERATION', 'OUTCOME_LEADS'],
            true
        );
    }
}
