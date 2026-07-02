<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $a anuncio con métricas + datos del creative */
/** @var string $mon */
/** @var \Closure $fmtMoneda */
/** @var \Closure $fmtNum */
/** @var \Closure $fmtPct */
/** @var string $labelResultadosCorto */
/** @var bool $ocultarConversaciones */
/** @var bool $ocultarLeads */

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
$objEfectivo = $optGoal !== '' ? $optGoal : $objetivoCam;
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

// Etiqueta de Resultados: prioriza optimization_goal del adset (lo que se
// está optimizando), fallback al objetivo de la campaña.
$labelAdset = $optGoal !== ''
    ? ObjetivoCampania::nombreCortoResultados($optGoal)
    : ($objetivoCam !== ''
        ? ObjetivoCampania::nombreCortoResultados($objetivoCam)
        : ($labelResultadosCorto ?? 'Resultados'));
$labelCortoLower = mb_strtolower($labelAdset);
?>
<article class="ad-card">
    <div class="ad-card__media">
        <?php if ($thumb !== ''): ?>
            <a href="<?= $view->e($permalink !== '' ? $permalink : $thumb) ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?= $view->e($thumb) ?>" alt="<?= $view->e((string) $a['nombre']) ?>" loading="lazy">
            </a>
            <span class="ad-card__type"><?= $view->e($tipoLabel) ?></span>
        <?php else: ?>
            <div class="ad-card__placeholder">Sin preview</div>
        <?php endif; ?>
    </div>
    <div class="ad-card__content">
        <header class="ad-card__header">
            <h3 class="ad-card__title"><?= $view->e((string) $a['nombre']) ?></h3>
            <?php if ($titulo !== ''): ?>
                <p class="ad-card__creative-title"><?= $view->e($titulo) ?></p>
            <?php endif; ?>
        </header>

        <?php if ($cuerpo !== ''): ?>
            <p class="ad-card__copy"><?= nl2br($view->e(mb_strlen($cuerpo) > 320 ? mb_substr($cuerpo, 0, 320) . '…' : $cuerpo)) ?></p>
        <?php endif; ?>

        <?php
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
        ?>
        <?php if ($mostrarLink || $permalink !== ''): ?>
            <p class="ad-card__links">
                <?php if ($permalink !== ''): ?>
                    <a href="<?= $view->e($permalink) ?>" target="_blank" rel="noopener noreferrer">Ver post original ↗</a>
                <?php endif; ?>
                <?php if ($mostrarLink): ?>
                    <a href="<?= $view->e($linkUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <?= $cta !== '' ? $view->e(ucfirst(str_replace('_', ' ', strtolower($cta)))) : 'Link de destino' ?> ↗
                    </a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <table class="ad-card__metrics-table">
            <tbody>
                <tr>
                    <th>Gasto</th>
                    <td><?= $view->e($mon) ?> <?= $fmtMoneda($a['gasto']) ?></td>
                    <th>Impresiones</th>
                    <td><?= $fmtNum($a['impresiones']) ?></td>
                </tr>
                <tr>
                    <th>Clicks</th>
                    <td><?= $fmtNum($a['clicks']) ?></td>
                    <th>CTR</th>
                    <td><?= $fmtPct($a['ctr']) ?></td>
                </tr>
                <?php if (((int) ($a['resultados'] ?? 0)) > 0): ?>
                    <tr>
                        <th><?= $view->e($labelAdset) ?></th>
                        <td><?= $fmtNum($a['resultados']) ?></td>
                        <?php if (isset($a['costo_por_resultado']) && $a['costo_por_resultado'] !== null): ?>
                            <th>Costo p/<?= $view->e($labelCortoLower) ?></th>
                            <td><?= $view->e($mon) ?> <?= $fmtMoneda($a['costo_por_resultado']) ?></td>
                        <?php else: ?>
                            <th></th><td></td>
                        <?php endif; ?>
                    </tr>
                <?php endif; ?>
                <?php if (((int) ($a['conversaciones'] ?? 0)) > 0 && $mostrarConversaciones): ?>
                    <tr>
                        <th>Conversaciones</th>
                        <td><?= $fmtNum($a['conversaciones']) ?></td>
                        <?php if (isset($a['costo_por_conversacion']) && $a['costo_por_conversacion'] !== null): ?>
                            <th>Costo p/conv.</th>
                            <td><?= $view->e($mon) ?> <?= $fmtMoneda($a['costo_por_conversacion']) ?></td>
                        <?php else: ?>
                            <th></th><td></td>
                        <?php endif; ?>
                    </tr>
                <?php endif; ?>
                <?php if (((int) ($a['leads'] ?? 0)) > 0 && $mostrarLeads): ?>
                    <tr>
                        <th>Leads</th>
                        <td><?= $fmtNum($a['leads']) ?></td>
                        <?php if (((int) ($a['landing_page_views'] ?? 0)) > 0): ?>
                            <th>Visitas pág.</th>
                            <td><?= $fmtNum($a['landing_page_views']) ?></td>
                        <?php else: ?>
                            <th></th><td></td>
                        <?php endif; ?>
                    </tr>
                <?php elseif (((int) ($a['landing_page_views'] ?? 0)) > 0): ?>
                    <tr>
                        <th>Visitas pág.</th>
                        <td><?= $fmtNum($a['landing_page_views']) ?></td>
                        <th></th><td></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
