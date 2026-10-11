<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $cliente */
/** @var string $desde */
/** @var string $hasta */
/** @var list<array<string,mixed>> $propiedades_asignadas */
/** @var string $moneda */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $por_propiedad */
/** @var list<array<string,mixed>> $canales */
/** @var list<array<string,mixed>> $evolucion */
/** @var string|null $comentarios */
/** @var string $generado_en */

use MisterCo\Reports\Domain\EtiquetasGoogle;

$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtDec = static fn ($v) => number_format((float) $v, fmod((float) $v, 1.0) == 0.0 ? 0 : 2, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
$fmtMoneda = static fn ($v) => trim($moneda . ' ' . number_format((float) $v, $moneda === 'PYG' ? 0 : 2, ',', '.'));
$hayIngresos = (float) $totales['ingresos'] > 0;
$totalSesionesCanal = array_sum(array_map(static fn ($c) => (int) $c['sesiones'], $canales));
$detalle = count($propiedades_asignadas) === 1
    ? 'Sitio: ' . $propiedades_asignadas[0]['nombre']
    : count($propiedades_asignadas) . ' propiedades';
?>
<?= $view->renderPartial('pdf/portada', [
    'cliente' => $cliente,
    'subtitulo' => 'Reporte Google Analytics',
    'detalle' => $detalle,
    'desde' => $desde,
    'hasta' => $hasta,
    'generado_en' => $generado_en,
]) ?>

<h2>Resumen del tráfico</h2>
<table class="kpi-row">
    <tr>
        <td><div class="kpi-label">Sesiones</div><div class="kpi-value"><?= $fmtNum($totales['sesiones']) ?></div></td>
        <td><div class="kpi-label">Usuarios</div><div class="kpi-value"><?= $fmtNum($totales['usuarios']) ?></div></td>
        <td><div class="kpi-label">Usuarios nuevos</div><div class="kpi-value"><?= $fmtNum($totales['usuarios_nuevos']) ?></div></td>
    </tr>
    <tr>
        <td><div class="kpi-label">Páginas vistas</div><div class="kpi-value"><?= $fmtNum($totales['vistas']) ?></div></td>
        <td><div class="kpi-label">Tasa de interacción</div><div class="kpi-value"><?= $fmtPct($totales['tasa_interaccion']) ?></div></td>
        <td><div class="kpi-label">Tiempo medio por sesión</div><div class="kpi-value"><?= $view->e(EtiquetasGoogle::duracion($totales['duracion_media_seg'])) ?></div></td>
    </tr>
    <tr>
        <td><div class="kpi-label">Eventos clave</div><div class="kpi-value"><?= $fmtDec($totales['eventos_clave']) ?></div></td>
        <td><?php if ($hayIngresos): ?><div class="kpi-label">Ingresos</div><div class="kpi-value"><?= $view->e($fmtMoneda($totales['ingresos'])) ?></div><?php endif; ?></td>
        <td></td>
    </tr>
</table>
<p class="muted" style="font-size:8pt">Los usuarios se suman día a día: una misma persona que visita el sitio varios días cuenta más de una vez.</p>

<h2>Canales de tráfico</h2>
<?php if ($canales === []): ?>
    <p class="muted">Sin datos por canal para este período.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Canal</th>
                <th class="num">Sesiones</th>
                <th class="num">% del total</th>
                <th class="num">Usuarios</th>
                <th class="num">Eventos clave</th>
                <?php if ($hayIngresos): ?><th class="num">Ingresos</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($canales as $c): ?>
            <tr>
                <td><?= $view->e(EtiquetasGoogle::canal((string) $c['canal'])) ?></td>
                <td class="num"><?= $fmtNum($c['sesiones']) ?></td>
                <td class="num"><?= $fmtPct($totalSesionesCanal > 0 ? (int) $c['sesiones'] / $totalSesionesCanal * 100 : null) ?></td>
                <td class="num"><?= $fmtNum($c['usuarios']) ?></td>
                <td class="num"><?= $fmtDec($c['eventos_clave']) ?></td>
                <?php if ($hayIngresos): ?><td class="num"><?= $view->e($fmtMoneda($c['ingresos'])) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (count($por_propiedad) > 1): ?>
<h2>Por sitio</h2>
<table>
    <thead>
        <tr><th>Propiedad</th><th class="num">Sesiones</th><th class="num">Usuarios</th><th class="num">Páginas vistas</th><th class="num">Tasa de interacción</th><th class="num">Eventos clave</th></tr>
    </thead>
    <tbody>
    <?php foreach ($por_propiedad as $p): ?>
        <tr>
            <td><?= $view->e((string) $p['propiedad']) ?></td>
            <td class="num"><?= $fmtNum($p['sesiones']) ?></td>
            <td class="num"><?= $fmtNum($p['usuarios']) ?></td>
            <td class="num"><?= $fmtNum($p['vistas']) ?></td>
            <td class="num"><?= $fmtPct($p['tasa_interaccion']) ?></td>
            <td class="num"><?= $fmtDec($p['eventos_clave']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2>Evolución diaria</h2>
<?php if ($evolucion === []): ?>
    <p class="muted">Sin datos diarios.</p>
<?php else: ?>
    <table>
        <thead>
            <tr><th>Fecha</th><th class="num">Sesiones</th><th class="num">Usuarios</th><th class="num">Páginas vistas</th><th class="num">Eventos clave</th></tr>
        </thead>
        <tbody>
        <?php foreach ($evolucion as $e): ?>
            <tr>
                <td><?= $view->e((string) $e['fecha']) ?></td>
                <td class="num"><?= $fmtNum($e['sesiones']) ?></td>
                <td class="num"><?= $fmtNum($e['usuarios']) ?></td>
                <td class="num"><?= $fmtNum($e['vistas']) ?></td>
                <td class="num"><?= $fmtDec($e['eventos_clave']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($comentarios !== null && trim($comentarios) !== ''): ?>
<h2>Comentarios estratégicos</h2>
<div style="white-space:pre-wrap;font-size:10pt;line-height:1.5"><?= $view->e($comentarios) ?></div>
<?php endif; ?>
