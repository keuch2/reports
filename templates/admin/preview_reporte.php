<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var list<string> $meses_disponibles */
/** @var \MisterCo\Reports\Domain\PeriodoReporte $periodo */

$base = '/admin/clientes/' . ((int) $cliente['id']);
$volver = $view->url($base . '/dashboard') . '?' . $periodo->query();
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>

<div class="preview-banner">
    <span>📄 Reporte PDF de <strong><?= $view->e((string) $cliente['nombre_comercial']) ?></strong>
        — mismo PDF que genera el cliente, con su plantilla y permisos.</span>
    <a href="<?= $view->e($volver) ?>" class="btn btn--link">Volver al dashboard</a>
</div>

<section class="shell__body">
    <p><a href="<?= $view->e($volver) ?>">← Volver al dashboard</a></p>
    <h1>Generar reporte PDF</h1>
    <p class="muted">El PDF refleja exactamente los datos del dashboard del cliente para el período elegido.</p>

    <?= $view->renderPartial('partials/reporte_form', [
        'action' => $view->url($base . '/reporte.pdf'),
        'cancelar_url' => $volver,
        'periodo' => $periodo,
        'meses_disponibles' => $meses_disponibles,
    ]) ?>
</section>
