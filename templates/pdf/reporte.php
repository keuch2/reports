<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $cliente */
/** @var array<string,mixed> $cuenta */
/** @var string $desde */
/** @var string $hasta */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $campanias */
/** @var list<array<string,mixed>> $evolucion */
/** @var list<array{tipo:string, cantidad:int, gasto:float, costo:?float}> $resultados_por_tipo */
/** @var list<string> $secciones */
/** @var string|null $comentarios */
/** @var string $generado_en */

$incluir = static fn (string $s): bool => in_array($s, $secciones, true);

$mon = (string) ($cuenta['moneda'] ?? '');

// PYG no usa decimales; se redondea hacia arriba al guaraní entero — mismo
// criterio que el dashboard (templates/cliente/campania_detalle.php y costos).
$fmtMoneda = static fn ($v) => $mon === 'PYG'
    ? number_format((float) ceil((float) $v), 0, ',', '.')
    : number_format((float) $v, 2, ',', '.');
$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';

// Etiquetas de resultado por tipo — idénticas a partials/resultados_por_tipo.php
$labelTipo = [
    'conversaciones' => ['plural' => 'Conversaciones WhatsApp', 'singular' => 'conversación'],
    'leads' => ['plural' => 'Clientes potenciales', 'singular' => 'cliente potencial'],
    'interacciones' => ['plural' => 'Interacciones', 'singular' => 'interacción'],
    'visitas' => ['plural' => 'Visitas a destino', 'singular' => 'visita'],
];

// Columnas de resultado de la tabla de campañas: se muestran solo si alguna
// campaña tiene datos — misma lógica que templates/cliente/dashboard_meta.php.
$sumColumna = static fn (string $k): int => (int) array_sum(array_map(static fn ($c) => (int) ($c[$k] ?? 0), $campanias));
$colConv = $sumColumna('conversaciones') > 0;
$colLeads = $sumColumna('leads') > 0;
$colInter = $sumColumna('interacciones') > 0;
$colVisit = $sumColumna('visitas') > 0;
?>
<style>
    body { font-family: sans-serif; color: #1a1d24; font-size: 10pt; }
    h1 { font-size: 20pt; margin: 0 0 4pt; color: #1f3a8a; }
    h2 { font-size: 13pt; margin: 18pt 0 6pt; color: #1f3a8a; border-bottom: 1pt solid #e5e7eb; padding-bottom: 4pt; }
    .portada { padding-top: 80pt; text-align: center; }
    .portada h1 { font-size: 28pt; }
    .portada .sub { font-size: 14pt; color: #6b7280; margin-top: 8pt; }
    .portada .rango { margin-top: 24pt; font-size: 12pt; }
    .muted { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 6pt; }
    th, td { padding: 5pt 6pt; border-bottom: 0.5pt solid #e5e7eb; text-align: left; font-size: 9pt; }
    th { background: #f5f6f8; font-weight: 600; }
    td.num, th.num { text-align: right; }
    .kpi-row { width: 100%; margin-top: 8pt; }
    .kpi-row td { width: 33%; padding: 8pt; border: 0.5pt solid #e5e7eb; background: #f9fafb; vertical-align: top; }
    .kpi-label { font-size: 8pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
    .kpi-value { font-size: 16pt; font-weight: 700; margin-top: 2pt; }
    .kpi-sub { font-size: 8pt; color: #6b7280; margin-top: 2pt; }
    .costos td, .costos th { font-size: 10pt; }
    .costos .total td, .costos .total th { font-weight: 700; border-top: 1pt solid #1f3a8a; }
</style>

<div class="portada">
    <h1>Mister Co.</h1>
    <p class="sub">Reporte Meta Ads</p>
    <h2 style="border:none;margin-top:24pt;color:#1a1d24"><?= $view->e((string) $cliente['nombre_comercial']) ?></h2>
    <p class="muted">Cuenta: <?= $view->e((string) $cuenta['nombre']) ?></p>
    <p class="rango"><strong>Del <?= $view->e($desde) ?> al <?= $view->e($hasta) ?></strong></p>
    <p class="muted" style="margin-top:60pt">Generado el <?= $view->e($generado_en) ?></p>
</div>

<pagebreak/>

<?php if ($incluir('resumen_ejecutivo')): ?>
<h2>Resumen ejecutivo</h2>
<table class="kpi-row">
    <tr>
        <td>
            <div class="kpi-label">Gasto total</div>
            <div class="kpi-value"><?= $view->e($mon) ?> <?= $fmtMoneda($totales['gasto'] ?? 0) ?></div>
        </td>
        <td>
            <div class="kpi-label">Impresiones</div>
            <div class="kpi-value"><?= $fmtNum($totales['impresiones'] ?? 0) ?></div>
        </td>
        <td>
            <div class="kpi-label">Alcance</div>
            <div class="kpi-value"><?= $fmtNum($totales['alcance'] ?? 0) ?></div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="kpi-label">Clicks</div>
            <div class="kpi-value"><?= $fmtNum($totales['clicks_totales'] ?? 0) ?></div>
        </td>
        <td>
            <div class="kpi-label">CTR</div>
            <div class="kpi-value"><?= $fmtPct($totales['ctr'] ?? null) ?></div>
        </td>
        <td>
            <div class="kpi-label">CPC promedio</div>
            <div class="kpi-value"><?= isset($totales['cpc']) && $totales['cpc'] !== null ? $view->e($mon) . ' ' . $fmtMoneda($totales['cpc']) : '—' ?></div>
        </td>
    </tr>
</table>
<?php endif; // resumen_ejecutivo ?>

<?php if ($incluir('resultados_por_tipo') && $resultados_por_tipo !== []): ?>
<h2>Resultados</h2>
<table class="kpi-row">
    <tr>
    <?php foreach ($resultados_por_tipo as $i => $r):
        $info = $labelTipo[$r['tipo']] ?? ['plural' => ucfirst($r['tipo']), 'singular' => $r['tipo']];
    ?>
        <td>
            <div class="kpi-label"><?= $view->e($info['plural']) ?></div>
            <div class="kpi-value"><?= $fmtNum($r['cantidad']) ?></div>
            <?php if ($r['costo'] !== null): ?>
                <div class="kpi-sub"><?= $view->e($mon) ?> <?= $fmtMoneda($r['costo']) ?> por <?= $view->e($info['singular']) ?></div>
            <?php endif; ?>
        </td>
        <?php if (($i + 1) % 3 === 0): ?></tr><tr><?php endif; ?>
    <?php endforeach; ?>
    </tr>
</table>
<?php endif; // resultados_por_tipo ?>

<?php if ($incluir('tabla_campanias')): ?>
<h2>Desempeño por campaña</h2>
<?php if ($campanias === []): ?>
    <p class="muted">No se registraron datos en el rango seleccionado.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Campaña</th>
                <th>Cuenta</th>
                <th>Estado</th>
                <th class="num">Gasto</th>
                <th class="num">Impresiones</th>
                <th class="num">Clicks</th>
                <th class="num">CTR</th>
                <th class="num">CPC</th>
                <?php if ($colConv): ?><th class="num">Conversaciones</th><?php endif; ?>
                <?php if ($colLeads): ?><th class="num">Clientes pot.</th><?php endif; ?>
                <?php if ($colInter): ?><th class="num">Interacciones</th><?php endif; ?>
                <?php if ($colVisit): ?><th class="num">Visitas</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($campanias as $c): ?>
            <tr>
                <td><?= $view->e((string) $c['campania']) ?></td>
                <td><?= $view->e((string) ($c['cuenta_nombre'] ?? '')) ?></td>
                <td><?= $view->e((string) ($c['estado'] ?? '—')) ?></td>
                <td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($c['gasto']) ?></td>
                <td class="num"><?= $fmtNum($c['impresiones']) ?></td>
                <td class="num"><?= $fmtNum($c['clicks']) ?></td>
                <td class="num"><?= $fmtPct($c['ctr']) ?></td>
                <td class="num"><?= $c['cpc'] !== null ? $view->e($mon) . ' ' . $fmtMoneda($c['cpc']) : '—' ?></td>
                <?php if ($colConv): ?><td class="num"><?= ((int) ($c['conversaciones'] ?? 0)) > 0 ? $fmtNum($c['conversaciones']) : '—' ?></td><?php endif; ?>
                <?php if ($colLeads): ?><td class="num"><?= ((int) ($c['leads'] ?? 0)) > 0 ? $fmtNum($c['leads']) : '—' ?></td><?php endif; ?>
                <?php if ($colInter): ?><td class="num"><?= ((int) ($c['interacciones'] ?? 0)) > 0 ? $fmtNum($c['interacciones']) : '—' ?></td><?php endif; ?>
                <?php if ($colVisit): ?><td class="num"><?= ((int) ($c['visitas'] ?? 0)) > 0 ? $fmtNum($c['visitas']) : '—' ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php endif; // tabla_campanias ?>

<?php if ($incluir('evolucion_diaria')): ?>
<h2>Evolución diaria</h2>
<?php if ($evolucion === []): ?>
    <p class="muted">Sin datos diarios.</p>
<?php else: ?>
    <table>
        <thead>
            <tr><th>Fecha</th><th class="num">Gasto</th><th class="num">Impresiones</th><th class="num">Clicks</th></tr>
        </thead>
        <tbody>
        <?php foreach ($evolucion as $e): ?>
            <tr>
                <td><?= $view->e((string) $e['fecha']) ?></td>
                <td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($e['gasto']) ?></td>
                <td class="num"><?= $fmtNum($e['impresiones']) ?></td>
                <td class="num"><?= $fmtNum($e['clicks']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php endif; // evolucion_diaria ?>

<?php
// Cuadro de costos — misma fórmula que partials/costos_campania.php
// (comisión 20% + IVA 10% sobre el subtotal).
$gastoCostos = (float) ($totales['gasto'] ?? 0);
if ($incluir('costos') && $gastoCostos > 0):
    $comision = $gastoCostos * 0.20;
    $subtotal = $gastoCostos + $comision;
    $iva = $subtotal * 0.10;
    $total = $subtotal + $iva;
?>
<h2>Costos del período</h2>
<table class="costos">
    <tbody>
        <tr><th>Costo neto</th><td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($gastoCostos) ?></td></tr>
        <tr><th>Comisión agencia (20%)</th><td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($comision) ?></td></tr>
        <tr><th>Subtotal sin IVA</th><td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($subtotal) ?></td></tr>
        <tr><th>IVA (10%)</th><td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($iva) ?></td></tr>
        <tr class="total"><th>Total con IVA</th><td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($total) ?></td></tr>
    </tbody>
</table>
<?php endif; // costos ?>

<?php if ($incluir('comentarios') && $comentarios !== null && trim($comentarios) !== ''): ?>
<h2>Comentarios estratégicos</h2>
<div style="white-space:pre-wrap;font-size:10pt;line-height:1.5"><?= $view->e($comentarios) ?></div>
<?php endif; ?>
