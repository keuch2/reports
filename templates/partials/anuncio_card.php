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

$labelCortoLower = mb_strtolower($labelResultadosCorto ?? 'resultado');
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

        <?php if ($linkUrl !== '' || $permalink !== ''): ?>
            <p class="ad-card__links">
                <?php if ($permalink !== ''): ?>
                    <a href="<?= $view->e($permalink) ?>" target="_blank" rel="noopener noreferrer">Ver post original ↗</a>
                <?php endif; ?>
                <?php if ($linkUrl !== ''): ?>
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
                        <th><?= $view->e($labelResultadosCorto ?? 'Resultados') ?></th>
                        <td><?= $fmtNum($a['resultados']) ?></td>
                        <?php if (isset($a['costo_por_resultado']) && $a['costo_por_resultado'] !== null): ?>
                            <th>Costo p/<?= $view->e($labelCortoLower) ?></th>
                            <td><?= $view->e($mon) ?> <?= $fmtMoneda($a['costo_por_resultado']) ?></td>
                        <?php else: ?>
                            <th></th><td></td>
                        <?php endif; ?>
                    </tr>
                <?php endif; ?>
                <?php if (((int) ($a['conversaciones'] ?? 0)) > 0 && !($ocultarConversaciones ?? false)): ?>
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
                <?php if (((int) ($a['leads'] ?? 0)) > 0 && !($ocultarLeads ?? false)): ?>
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
