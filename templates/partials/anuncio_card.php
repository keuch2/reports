<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $a anuncio con métricas + datos del creative */
/** @var string $mon */
/** @var \Closure $fmtMoneda */
/** @var \Closure $fmtNum */
/** @var \Closure $fmtPct */

$thumb = (string) ($a['image_url'] ?? $a['thumbnail_url'] ?? '');
$cuerpo = (string) ($a['cuerpo'] ?? '');
$titulo = (string) ($a['titulo'] ?? '');
$linkUrl = (string) ($a['link_url'] ?? '');
$permalink = (string) ($a['permalink_url'] ?? '');
$cta = (string) ($a['call_to_action'] ?? '');
$tipo = (string) ($a['tipo'] ?? '');

$tipoIcon = match ($tipo) {
    'video' => '▶️',
    'image' => '🖼️',
    'link' => '🔗',
    default => '📄',
};
?>
<article class="ad-card">
    <div class="ad-card__media">
        <?php if ($thumb !== ''): ?>
            <img src="<?= $view->e($thumb) ?>" alt="" loading="lazy">
            <span class="ad-card__type"><?= $tipoIcon ?></span>
        <?php else: ?>
            <div class="ad-card__placeholder"><?= $tipoIcon ?> Sin preview</div>
        <?php endif; ?>
    </div>
    <div class="ad-card__content">
        <h3 class="ad-card__title"><?= $view->e((string) $a['nombre']) ?></h3>
        <?php if ($titulo !== ''): ?>
            <p class="ad-card__creative-title"><?= $view->e($titulo) ?></p>
        <?php endif; ?>
        <?php if ($cuerpo !== ''): ?>
            <p class="ad-card__copy"><?= nl2br($view->e(mb_strlen($cuerpo) > 220 ? mb_substr($cuerpo, 0, 220) . '…' : $cuerpo)) ?></p>
        <?php endif; ?>
        <?php if ($linkUrl !== '' || $permalink !== ''): ?>
            <p class="ad-card__links">
                <?php if ($permalink !== ''): ?>
                    <a href="<?= $view->e($permalink) ?>" target="_blank" rel="noopener noreferrer">Ver post original ↗</a>
                <?php endif; ?>
                <?php if ($linkUrl !== ''): ?>
                    <a href="<?= $view->e($linkUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <?= $cta !== '' ? $view->e(str_replace('_', ' ', strtolower($cta))) : 'Link de destino' ?> ↗
                    </a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <dl class="ad-card__metrics">
            <div><dt>Gasto</dt><dd><?= $view->e($mon) ?> <?= $fmtMoneda($a['gasto']) ?></dd></div>
            <div><dt>Impresiones</dt><dd><?= $fmtNum($a['impresiones']) ?></dd></div>
            <div><dt>Clicks</dt><dd><?= $fmtNum($a['clicks']) ?></dd></div>
            <div><dt>CTR</dt><dd><?= $fmtPct($a['ctr']) ?></dd></div>
            <?php if (((int) ($a['resultados'] ?? 0)) > 0): ?>
                <div><dt>Resultados</dt><dd><?= $fmtNum($a['resultados']) ?></dd></div>
                <?php if (isset($a['costo_por_resultado']) && $a['costo_por_resultado'] !== null): ?>
                    <div><dt>Costo p/result.</dt><dd><?= $view->e($mon) ?> <?= $fmtMoneda($a['costo_por_resultado']) ?></dd></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (((int) ($a['conversaciones'] ?? 0)) > 0): ?>
                <div><dt>Conversac.</dt><dd><?= $fmtNum($a['conversaciones']) ?></dd></div>
                <?php if (isset($a['costo_por_conversacion']) && $a['costo_por_conversacion'] !== null): ?>
                    <div><dt>Costo p/conv.</dt><dd><?= $view->e($mon) ?> <?= $fmtMoneda($a['costo_por_conversacion']) ?></dd></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (((int) ($a['leads'] ?? 0)) > 0): ?>
                <div><dt>Leads</dt><dd><?= $fmtNum($a['leads']) ?></dd></div>
            <?php endif; ?>
            <?php if (((int) ($a['landing_page_views'] ?? 0)) > 0): ?>
                <div><dt>Visitas pág.</dt><dd><?= $fmtNum($a['landing_page_views']) ?></dd></div>
            <?php endif; ?>
        </dl>
    </div>
</article>
