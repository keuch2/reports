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

require __DIR__ . '/anuncio_card_datos.php';
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
                <?php foreach ($filasMetricas as $fila): ?>
                    <tr>
                        <?php foreach ($fila as [$label, $valor]): ?>
                            <th><?= $view->e($label) ?></th>
                            <td><?= $view->e($valor) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</article>
