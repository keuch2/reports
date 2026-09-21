<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var list<string> $meses_disponibles */
/** @var \MisterCo\Reports\Domain\PeriodoReporte $periodo */

$volver = $view->url('/cliente') . '?' . $periodo->query();
?>
<?= $view->renderPartial('partials/cliente_header', ['usuario' => $usuario]) ?>

<section class="shell__body">
    <p><a href="<?= $view->e($volver) ?>">← Volver al dashboard</a></p>
    <h1>Generar reporte PDF</h1>
    <p class="muted">El PDF refleja exactamente los datos del dashboard para el período elegido.</p>

    <?= $view->renderPartial('partials/reporte_form', [
        'action' => $view->url('/cliente/reporte.pdf'),
        'cancelar_url' => $volver,
        'periodo' => $periodo,
        'meses_disponibles' => $meses_disponibles,
    ]) ?>
</section>
