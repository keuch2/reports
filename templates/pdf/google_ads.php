<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $cliente */
/** @var string $desde */
/** @var string $hasta */
/** @var int $campanias_asignadas_count */
/** @var string $moneda */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $campanias */
/** @var list<array<string,mixed>> $evolucion */
/** @var string|null $comentarios */
/** @var string $generado_en */

use MisterCo\Reports\Domain\EtiquetasGoogle;

$mon = $moneda;
// PYG no usa decimales; mismo criterio que el dashboard.
$fmtMoneda = static fn ($v) => $mon === 'PYG'
    ? number_format((float) ceil((float) $v), 0, ',', '.')
    : number_format((float) $v, 2, ',', '.');
$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtDec = static fn ($v) => number_format((float) $v, fmod((float) $v, 1.0) == 0.0 ? 0 : 2, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
$conMoneda = static fn ($v) => $v === null ? '—' : $mon . ' ' . $fmtMoneda($v);
?>
<?= $view->renderPartial('pdf/google_estilos', [
    'cliente' => $cliente,
    'subtitulo' => 'Reporte Google Ads',
    'detalle' => $campanias_asignadas_count . ' campaña' . ($campanias_asignadas_count === 1 ? '' : 's') . ' asignada' . ($campanias_asignadas_count === 1 ? '' : 's'),
    'desde' => $desde,
    'hasta' => $hasta,
    'generado_en' => $generado_en,
]) ?>

<h2>Resumen ejecutivo</h2>
<table class="kpi-row">
    <tr>
        <td><div class="kpi-label">Inversión</div><div class="kpi-value"><?= $view->e($conMoneda($totales['gasto'])) ?></div></td>
        <td><div class="kpi-label">Impresiones</div><div class="kpi-value"><?= $fmtNum($totales['impresiones']) ?></div></td>
        <td><div class="kpi-label">Clics</div><div class="kpi-value"><?= $fmtNum($totales['clicks']) ?></div></td>
    </tr>
    <tr>
        <td><div class="kpi-label">CTR</div><div class="kpi-value"><?= $fmtPct($totales['ctr']) ?></div></td>
        <td><div class="kpi-label">CPC promedio</div><div class="kpi-value"><?= $view->e($conMoneda($totales['cpc'])) ?></div></td>
        <td>
            <div class="kpi-label">Conversiones</div>
            <div class="kpi-value"><?= $fmtDec($totales['conversiones']) ?></div>
            <?php if ($totales['costo_por_conversion'] !== null): ?>
                <div class="kpi-sub"><?= $view->e($conMoneda($totales['costo_por_conversion'])) ?> por conversión</div>
            <?php endif; ?>
        </td>
    </tr>
    <?php if ((float) $totales['valor_conversiones'] > 0): ?>
    <tr>
        <td><div class="kpi-label">Valor de conversiones</div><div class="kpi-value"><?= $view->e($conMoneda($totales['valor_conversiones'])) ?></div></td>
        <td></td><td></td>
    </tr>
    <?php endif; ?>
</table>

<h2>Desempeño por campaña</h2>
<?php if ($campanias === []): ?>
    <p class="muted">No se registraron datos en el rango seleccionado.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Campaña</th>
                <th>Tipo</th>
                <th class="num">Inversión</th>
                <th class="num">Impr.</th>
                <th class="num">Clics</th>
                <th class="num">CTR</th>
                <th class="num">CPC</th>
                <th class="num">Conv.</th>
                <th class="num">Costo/conv.</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($campanias as $c): ?>
            <tr>
                <td><?= $view->e((string) $c['campania']) ?></td>
                <td><?= $view->e(EtiquetasGoogle::tipoCampania($c['tipo_canal'])) ?></td>
                <td class="num"><?= $view->e($conMoneda($c['gasto'])) ?></td>
                <td class="num"><?= $fmtNum($c['impresiones']) ?></td>
                <td class="num"><?= $fmtNum($c['clicks']) ?></td>
                <td class="num"><?= $fmtPct($c['ctr']) ?></td>
                <td class="num"><?= $view->e($conMoneda($c['cpc'])) ?></td>
                <td class="num"><?= $c['conversiones'] > 0 ? $fmtDec($c['conversiones']) : '—' ?></td>
                <td class="num"><?= $view->e($conMoneda($c['costo_por_conversion'])) ?></td>
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
            <tr><th>Fecha</th><th class="num">Inversión</th><th class="num">Impresiones</th><th class="num">Clics</th><th class="num">Conversiones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($evolucion as $e): ?>
            <tr>
                <td><?= $view->e((string) $e['fecha']) ?></td>
                <td class="num"><?= $view->e($conMoneda($e['gasto'])) ?></td>
                <td class="num"><?= $fmtNum($e['impresiones']) ?></td>
                <td class="num"><?= $fmtNum($e['clicks']) ?></td>
                <td class="num"><?= $fmtDec($e['conversiones']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
// Cuadro de costos — misma fórmula que partials/costos_campania.php
// (comisión 20% + IVA 10% sobre el subtotal).
$gastoCostos = (float) $totales['gasto'];
if ($gastoCostos > 0):
    $comision = $gastoCostos * 0.20;
    $subtotal = $gastoCostos + $comision;
    $iva = $subtotal * 0.10;
?>
<h2>Costos del período</h2>
<table class="costos">
    <tbody>
        <tr><th>Costo neto</th><td class="num"><?= $view->e($conMoneda($gastoCostos)) ?></td></tr>
        <tr><th>Comisión agencia (20%)</th><td class="num"><?= $view->e($conMoneda($comision)) ?></td></tr>
        <tr><th>Subtotal sin IVA</th><td class="num"><?= $view->e($conMoneda($subtotal)) ?></td></tr>
        <tr><th>IVA (10%)</th><td class="num"><?= $view->e($conMoneda($iva)) ?></td></tr>
        <tr class="total"><th>Total con IVA</th><td class="num"><?= $view->e($conMoneda($subtotal + $iva)) ?></td></tr>
    </tbody>
</table>
<?php endif; ?>

<?php if ($comentarios !== null && trim($comentarios) !== ''): ?>
<h2>Comentarios estratégicos</h2>
<div style="white-space:pre-wrap;font-size:10pt;line-height:1.5"><?= $view->e($comentarios) ?></div>
<?php endif; ?>
