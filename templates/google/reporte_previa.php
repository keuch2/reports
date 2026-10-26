<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var bool $es_admin */
/** @var string $base */
/** @var string $nombre_fuente */
/** @var string $seccion */
/** @var list<string> $meses_disponibles */
/** @var \MisterCo\Reports\Domain\PeriodoReporte $periodo */

$volver = $view->url($base) . '?' . $periodo->query();
?>
<?= $view->renderPartial('partials/encabezado_cliente', [
    'usuario' => $usuario, 'cliente' => $cliente, 'es_admin' => $es_admin,
    'seccion' => $seccion,
    'descripcion' => 'Reporte PDF de ' . $nombre_fuente,
]) ?>

<section class="shell__body">
    <p><a href="<?= $view->e($volver) ?>">← Volver a <?= $view->e($nombre_fuente) ?></a></p>
    <h1>Generar reporte PDF · <?= $view->e($nombre_fuente) ?></h1>
    <p class="muted">El PDF refleja exactamente los datos de la pantalla de <?= $view->e($nombre_fuente) ?> para el período elegido.</p>

    <?= $view->renderPartial('partials/reporte_form', [
        'action' => $view->url($base . '/reporte.pdf'),
        'cancelar_url' => $volver,
        'periodo' => $periodo,
        'meses_disponibles' => $meses_disponibles,
    ]) ?>
</section>
