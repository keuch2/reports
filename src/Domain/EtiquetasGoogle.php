<?php

declare(strict_types=1);

namespace MisterCo\Reports\Domain;

/**
 * Traducciones al español de los enums que devuelven Google Ads y GA4, para
 * mostrarlos igual en el dashboard y en el PDF.
 */
final class EtiquetasGoogle
{
    private const TIPOS_CAMPANIA = [
        'SEARCH' => 'Búsqueda',
        'DISPLAY' => 'Display',
        'VIDEO' => 'Video (YouTube)',
        'SHOPPING' => 'Shopping',
        'PERFORMANCE_MAX' => 'Performance Max',
        'DEMAND_GEN' => 'Demand Gen',
        'MULTI_CHANNEL' => 'Aplicaciones',
        'SMART' => 'Inteligente',
        'LOCAL' => 'Local',
        'HOTEL' => 'Hoteles',
    ];

    private const ESTADOS_CAMPANIA = [
        'ENABLED' => 'Activa',
        'PAUSED' => 'Pausada',
        'REMOVED' => 'Eliminada',
    ];

    private const CANALES = [
        'Direct' => 'Directo',
        'Organic Search' => 'Búsqueda orgánica',
        'Paid Search' => 'Búsqueda pagada',
        'Organic Social' => 'Redes sociales orgánicas',
        'Paid Social' => 'Redes sociales pagadas',
        'Referral' => 'Referencias',
        'Email' => 'Email',
        'Display' => 'Display',
        'Organic Video' => 'Video orgánico',
        'Paid Video' => 'Video pagado',
        'Organic Shopping' => 'Shopping orgánico',
        'Paid Shopping' => 'Shopping pagado',
        'Paid Other' => 'Otros pagados',
        'Cross-network' => 'Multired',
        'Affiliates' => 'Afiliados',
        'Audio' => 'Audio',
        'SMS' => 'SMS',
        'Mobile Push Notifications' => 'Notificaciones push',
        'Unassigned' => 'Sin asignar',
    ];

    public static function tipoCampania(?string $tipo): string
    {
        return self::TIPOS_CAMPANIA[(string) $tipo] ?? ($tipo !== null && $tipo !== '' ? ucfirst(strtolower(str_replace('_', ' ', $tipo))) : '—');
    }

    public static function estadoCampania(?string $estado): string
    {
        return self::ESTADOS_CAMPANIA[(string) $estado] ?? ($estado ?? '—');
    }

    public static function canal(string $canal): string
    {
        return self::CANALES[$canal] ?? $canal;
    }

    /** Segundos → "m:ss" (duración media de interacción). */
    public static function duracion(?float $segundos): string
    {
        if ($segundos === null) {
            return '—';
        }
        $s = (int) round($segundos);

        return intdiv($s, 60) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
    }
}
