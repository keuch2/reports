<?php
/**
 * Bloque de anuncios agrupados por grupo de anuncios — misma estructura que la
 * sección "Anuncios" de cliente/campania_detalle.php.
 */
/** @var \MisterCo\Reports\Core\View $view */
/** @var list<array<string,mixed>> $adsets */
/** @var array<int, list<array<string,mixed>>> $anuncios_por_adset */
/** @var string $mon */
/** @var \Closure $fmtMoneda */
/** @var \Closure $fmtNum */
/** @var \Closure $fmtPct */
/** @var string $labelResultadosCorto */
/** @var bool $ocultarConversaciones */
/** @var bool $ocultarLeads */
?>
<?php foreach ($adsets as $g): ?>
    <?php $listaAds = $anuncios_por_adset[(int) $g['id']] ?? []; ?>
    <?php if ($listaAds === []) continue; ?>
    <div class="adset-block__header">
        <?= $view->e((string) $g['adset_nombre']) ?>
        <span>&nbsp; <?= count($listaAds) ?> anuncio<?= count($listaAds) === 1 ? '' : 's' ?> · <?= $view->e($mon) ?> <?= $fmtMoneda($g['gasto']) ?> gasto</span>
    </div>
    <?php foreach ($listaAds as $a): ?>
        <?= $view->renderPartial('pdf/anuncio_card', [
            'a' => $a,
            'mon' => $mon,
            'fmtMoneda' => $fmtMoneda,
            'fmtNum' => $fmtNum,
            'fmtPct' => $fmtPct,
            'labelResultadosCorto' => $labelResultadosCorto,
            'ocultarConversaciones' => $ocultarConversaciones,
            'ocultarLeads' => $ocultarLeads,
        ]) ?>
    <?php endforeach; ?>
<?php endforeach; ?>
