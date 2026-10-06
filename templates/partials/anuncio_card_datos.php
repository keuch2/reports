<?php
/**
 * Lógica compartida de la tarjeta de anuncio (web: partials/anuncio_card,
 * PDF: pdf/anuncio_card). Se incluye con require dentro del scope del partial.
 */
/** @var array<string,mixed> $a */
/** @var string $labelResultadosCorto */
/** @var bool $ocultarConversaciones */
/** @var bool $ocultarLeads */
/** @var string $mon */
/** @var \Closure $fmtMoneda */
/** @var \Closure $fmtNum */
/** @var \Closure $fmtPct */

use MisterCo\Reports\Domain\ObjetivoCampania;

// "Resultados" replica el criterio de Meta Ads Manager: optimization_goal del
// adset gana sobre objective de campaña. La columna Resultados representa lo
// que realmente se está optimizando (conversaciones WA, leads, etc.).
$optGoal = strtoupper((string) ($a['optimization_goal'] ?? ''));
$objetivoCam = strtoupper((string) ($a['objetivo_campania'] ?? ''));

$esLeads = in_array($optGoal, ['LEAD_GENERATION', 'QUALITY_LEAD', 'LEAD'], true)
    || ($optGoal === '' && in_array($objetivoCam, ['OUTCOME_LEADS', 'LEAD_GENERATION'], true));
$esMensajes = in_array($optGoal, ['CONVERSATIONS', 'REPLIES'], true)
    || ($optGoal === '' && $objetivoCam === 'MESSAGES');
$esEngagement = in_array($optGoal, ['POST_ENGAGEMENT', 'PAGE_LIKES', 'EVENT_RESPONSES'], true)
    || in_array($objetivoCam, ['OUTCOME_ENGAGEMENT', 'POST_ENGAGEMENT', 'PAGE_LIKES', 'EVENT_RESPONSES'], true);

// No duplicamos la métrica: si Resultados ya es conversaciones, no mostramos
// la fila Conversaciones aparte; idem leads. Y para campañas de engagement
// suprimimos conversaciones aisladas (clicks-to-WA residuales que confunden).
// Además: solo mostramos conversaciones/leads si son RELEVANTES al objetivo
// efectivo del anuncio — Meta reporta acciones colaterales (p. ej. 2
// conversaciones en awareness) que no son el objetivo y confunden al cliente.
// objetivoEfectivo: goals genéricos (REACH/IMPRESSIONS) caen al objetivo de campaña.
$objEfectivo = (string) ObjetivoCampania::objetivoEfectivo($optGoal, $objetivoCam);
$convEsRelevante = ObjetivoCampania::metricaEsRelevante($objEfectivo, 'conversaciones');
$leadsEsRelevante = ObjetivoCampania::metricaEsRelevante($objEfectivo, 'leads');
$mostrarConversaciones = $convEsRelevante && !$esMensajes && !$esLeads && !$esEngagement && !($ocultarConversaciones ?? false);
$mostrarLeads = $leadsEsRelevante && !$esLeads && !$esMensajes && !$esEngagement && !($ocultarLeads ?? false);

$thumb = (string) ($a['image_url'] ?? $a['thumbnail_url'] ?? '');
$cuerpo = (string) ($a['cuerpo'] ?? '');
$titulo = (string) ($a['titulo'] ?? '');
$linkUrl = (string) ($a['link_url'] ?? '');
$permalink = (string) ($a['permalink_url'] ?? '');
$cta = (string) ($a['call_to_action'] ?? '');
$tipo = (string) ($a['tipo'] ?? '');

$tipoLabel = match ($tipo) {
    'video' => 'Video',
    'image' => 'Imagen',
    'link' => 'Link',
    default => 'Anuncio',
};

// Etiqueta de Resultados: usa el objetivo EFECTIVO ya calculado arriba (los
// goals genéricos como REACH caen al objetivo de campaña, igual que el CASE
// que calcula el número — así "159" se etiqueta interacciones, no alcance).
$labelAdset = $objEfectivo !== ''
    ? ObjetivoCampania::nombreCortoResultados($objEfectivo)
    : ($labelResultadosCorto ?? 'Resultados');
$labelCortoLower = mb_strtolower($labelAdset);

// Ocultamos el link cuando es un deep-link de WhatsApp/Messenger (no
// tiene destino visitable desde un browser) o cuando el CTA lo confirma.
$linkEsMensaje = $linkUrl !== '' && (
    stripos($cta, 'WHATSAPP') !== false
    || stripos($cta, 'MESSAGE_PAGE') !== false
    || stripos($linkUrl, 'wa.me') !== false
    || stripos($linkUrl, 'whatsapp.com') !== false
    || stripos($linkUrl, 'applinks://') === 0
    || stripos($linkUrl, 'm.me/') !== false
);
$mostrarLink = $linkUrl !== '' && !$linkEsMensaje;

// Filas de la tabla de métricas: cada fila son 2 pares [label, valor]
// (un par vacío deja la celda en blanco para mantener la grilla de 4 columnas).
$vacio = ['', ''];
$filasMetricas = [
    [['Gasto', $mon . ' ' . $fmtMoneda($a['gasto'])], ['Impresiones', $fmtNum($a['impresiones'])]],
    [['Clicks', $fmtNum($a['clicks'])], ['CTR', $fmtPct($a['ctr'])]],
];
if (((int) ($a['resultados'] ?? 0)) > 0) {
    $filasMetricas[] = [
        [$labelAdset, $fmtNum($a['resultados'])],
        isset($a['costo_por_resultado']) && $a['costo_por_resultado'] !== null
            ? ['Costo p/' . $labelCortoLower, $mon . ' ' . $fmtMoneda($a['costo_por_resultado'])]
            : $vacio,
    ];
}
if (((int) ($a['conversaciones'] ?? 0)) > 0 && $mostrarConversaciones) {
    $filasMetricas[] = [
        ['Conversaciones', $fmtNum($a['conversaciones'])],
        isset($a['costo_por_conversacion']) && $a['costo_por_conversacion'] !== null
            ? ['Costo p/conv.', $mon . ' ' . $fmtMoneda($a['costo_por_conversacion'])]
            : $vacio,
    ];
}
if (((int) ($a['leads'] ?? 0)) > 0 && $mostrarLeads) {
    $filasMetricas[] = [
        ['Leads', $fmtNum($a['leads'])],
        ((int) ($a['landing_page_views'] ?? 0)) > 0 ? ['Visitas pág.', $fmtNum($a['landing_page_views'])] : $vacio,
    ];
} elseif (ObjetivoCampania::metricaEsRelevante($objEfectivo, 'visitas')
    && !ObjetivoCampania::visitasEsRedundante($objEfectivo)
    && ((int) ($a['landing_page_views'] ?? 0)) > 0) {
    $filasMetricas[] = [['Visitas pág.', $fmtNum($a['landing_page_views'])], $vacio];
}
