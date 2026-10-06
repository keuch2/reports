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

require __DIR__ . '/../partials/anuncio_card_datos.php';

// mPDF respeta los saltos de línea del copy; colapsamos los párrafos vacíos
// repetidos para que el texto no estire la tarjeta.
$copyPdf = preg_replace("/(\r?\n\s*){3,}/", "\n\n", trim($cuerpo)) ?? $cuerpo;
if (mb_strlen($copyPdf) > 320) {
    $copyPdf = mb_substr($copyPdf, 0, 320) . '…';
}
?>
<table class="ad-card" autosize="1">
    <tr>
        <td class="ad-card__media">
            <?php if ($thumb !== ''): ?>
                <div class="ad-card__type"><?= $view->e(mb_strtoupper($tipoLabel)) ?></div>
                <img src="<?= $view->e($thumb) ?>" alt="" style="max-width:72mm; max-height:90mm">
            <?php else: ?>
                <div class="muted">Sin preview</div>
            <?php endif; ?>
        </td>
        <td class="ad-card__content">
            <div class="ad-card__title"><?= $view->e((string) $a['nombre']) ?></div>
            <?php if ($titulo !== ''): ?>
                <div class="ad-card__creative-title"><?= $view->e($titulo) ?></div>
            <?php endif; ?>
            <?php if ($copyPdf !== ''): ?>
                <div class="ad-card__copy"><?= nl2br($view->e($copyPdf)) ?></div>
            <?php endif; ?>
            <?php if ($mostrarLink || $permalink !== ''): ?>
                <div class="ad-card__links">
                    <?php if ($permalink !== ''): ?>
                        <a href="<?= $view->e($permalink) ?>">Ver post original</a>
                    <?php endif; ?>
                    <?php if ($mostrarLink): ?>
                        <?= $permalink !== '' ? ' · ' : '' ?><a href="<?= $view->e($linkUrl) ?>"><?= $cta !== '' ? $view->e(ucfirst(str_replace('_', ' ', strtolower($cta)))) : 'Link de destino' ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <table class="ad-card__metrics">
                <?php foreach ($filasMetricas as $fila): ?>
                    <tr>
                        <?php foreach ($fila as [$label, $valor]): ?>
                            <th><?= $view->e($label) ?></th>
                            <td><?= $view->e($valor) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
</table>
