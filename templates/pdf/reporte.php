<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $cliente */
/** @var array<string,mixed> $cuenta */
/** @var string $desde */
/** @var string $hasta */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $campanias */
/** @var list<array<string,mixed>> $evolucion */
/** @var string $generado_en */

$fmtMoneda = static fn ($v) => number_format((float) $v, 2, ',', '.');
$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';

$mon = (string) ($cuenta['moneda'] ?? '');
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

<h2>Desempeño por campaña</h2>
<?php if ($campanias === []): ?>
    <p class="muted">No se registraron datos en el rango seleccionado.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Campaña</th>
                <th>Estado</th>
                <th class="num">Gasto</th>
                <th class="num">Impresiones</th>
                <th class="num">Clicks</th>
                <th class="num">CTR</th>
                <th class="num">CPC</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($campanias as $c): ?>
            <tr>
                <td><?= $view->e((string) $c['campania']) ?></td>
                <td><?= $view->e((string) ($c['estado'] ?? '—')) ?></td>
                <td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($c['gasto']) ?></td>
                <td class="num"><?= $fmtNum($c['impresiones']) ?></td>
                <td class="num"><?= $fmtNum($c['clicks']) ?></td>
                <td class="num"><?= $fmtPct($c['ctr']) ?></td>
                <td class="num"><?= $c['cpc'] !== null ? $view->e($mon) . ' ' . $fmtMoneda($c['cpc']) : '—' ?></td>
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
