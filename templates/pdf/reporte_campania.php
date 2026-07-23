<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $cliente */
/** @var array<string,mixed> $campania */
/** @var string $desde */
/** @var string $hasta */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $adsets */
/** @var list<array{tipo:string, cantidad:int, gasto:float, costo:?float}> $resultados_por_tipo */
/** @var list<array<string,mixed>> $evolucion */
/** @var string|null $comentarios */
/** @var string $generado_en */

use MisterCo\Reports\Domain\ObjetivoCampania;

$mon = (string) ($campania['moneda'] ?? '');
// El objetivo EFECTIVO prioriza el optimization_goal del adset sobre el objetivo
// de campaña (igual que Meta): una campaña OUTCOME_AWARENESS con adsets THRUPLAY
// reporta "Reproducciones de video", no "Personas alcanzadas".
$optGoal = (string) ($campania['optimization_goal_predominante'] ?? '');
$objetivoCampania = (string) ($campania['objetivo'] ?? '');
$objetivo = (string) (ObjetivoCampania::objetivoEfectivo($optGoal, $objetivoCampania) ?? '');
$labelResultados = ObjetivoCampania::nombreResultados($objetivo);
$labelResultadosCorto = ObjetivoCampania::nombreCortoResultados($objetivo);
$labelCostoPorResultado = 'Costo por ' . mb_strtolower($labelResultadosCorto);
$ocultarConversaciones = ObjetivoCampania::conversacionesEsRedundante($objetivo);
$ocultarLeads = ObjetivoCampania::leadsEsRedundante($objetivo);

// PYG sin decimales, redondeo hacia arriba — igual que campania_detalle.php.
$fmtMoneda = static fn ($v) => $mon === 'PYG'
    ? number_format((float) ceil((float) $v), 0, ',', '.')
    : number_format((float) $v, 2, ',', '.');
$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';

$labelTipo = [
    'conversaciones' => ['plural' => 'Conversaciones WhatsApp', 'singular' => 'conversación'],
    'leads' => ['plural' => 'Clientes potenciales', 'singular' => 'cliente potencial'],
    'interacciones' => ['plural' => 'Interacciones', 'singular' => 'interacción'],
    'visitas' => ['plural' => 'Visitas a destino', 'singular' => 'visita'],
];

// KPIs a mostrar, replicando la grilla de la pantalla de detalle.
$kpis = [
    ['Gasto', $mon . ' ' . $fmtMoneda($totales['gasto'] ?? 0)],
    ['Impresiones', $fmtNum($totales['impresiones'] ?? 0)],
    ['Alcance', $fmtNum($totales['alcance'] ?? 0)],
    ['Clicks', $fmtNum($totales['clicks'] ?? 0)],
    ['CTR', $fmtPct($totales['ctr'] ?? null)],
    ['CPC', isset($totales['cpc']) && $totales['cpc'] !== null ? $mon . ' ' . $fmtMoneda($totales['cpc']) : '—'],
];
if (((int) ($totales['resultados'] ?? 0)) > 0) {
    $kpis[] = [$labelResultados, $fmtNum($totales['resultados'])];
    if (isset($totales['costo_por_resultado']) && $totales['costo_por_resultado'] !== null) {
        $kpis[] = [$labelCostoPorResultado, $mon . ' ' . $fmtMoneda($totales['costo_por_resultado'])];
    }
}
// Solo métricas secundarias relevantes al objetivo (mismo criterio que la
// pantalla de detalle): evita mostrar conversaciones/leads/visitas residuales
// que Meta reporta pero no son el objetivo de la campaña.
$convRelevante = ObjetivoCampania::metricaEsRelevante($objetivo, 'conversaciones');
$leadsRelevante = ObjetivoCampania::metricaEsRelevante($objetivo, 'leads');
$visitasRelevante = ObjetivoCampania::metricaEsRelevante($objetivo, 'visitas');
if ($convRelevante && ((int) ($totales['conversaciones'] ?? 0)) > 0 && !$ocultarConversaciones) {
    $kpis[] = ['Conversaciones', $fmtNum($totales['conversaciones'])];
    if (isset($totales['costo_por_conversacion']) && $totales['costo_por_conversacion'] !== null) {
        $kpis[] = ['Costo por conversación', $mon . ' ' . $fmtMoneda($totales['costo_por_conversacion'])];
    }
}
if ($leadsRelevante && ((int) ($totales['leads'] ?? 0)) > 0 && !$ocultarLeads) {
    $kpis[] = ['Clientes potenciales', $fmtNum($totales['leads'])];
}
if ($visitasRelevante && !ObjetivoCampania::visitasEsRedundante($objetivo) && ((int) ($totales['landing_page_views'] ?? 0)) > 0) {
    $kpis[] = ['Visitas página', $fmtNum($totales['landing_page_views'])];
}
?>
<style>
    body { font-family: sans-serif; color: #1a1d24; font-size: 10pt; }
    h1 { font-size: 20pt; margin: 0 0 4pt; color: #1f3a8a; }
    h2 { font-size: 13pt; margin: 18pt 0 6pt; color: #1f3a8a; border-bottom: 1pt solid #e5e7eb; padding-bottom: 4pt; }
    .portada { padding-top: 80pt; text-align: center; }
    .portada h1 { font-size: 26pt; }
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
    .kpi-value { font-size: 15pt; font-weight: 700; margin-top: 2pt; }
    .kpi-sub { font-size: 8pt; color: #6b7280; margin-top: 2pt; }
    .costos .total td, .costos .total th { font-weight: 700; border-top: 1pt solid #1f3a8a; }
</style>

<div class="portada">
    <h1>Mister Co.</h1>
    <p class="sub">Reporte de campaña</p>
    <h2 style="border:none;margin-top:24pt;color:#1a1d24"><?= $view->e((string) $campania['nombre']) ?></h2>
    <p class="muted"><?= $view->e((string) $cliente['nombre_comercial']) ?> · Cuenta: <?= $view->e((string) $campania['cuenta_nombre']) ?></p>
    <p class="muted">
        <?php if ($objetivo !== ''): ?>Objetivo: <?= $view->e($labelResultados) ?><?php endif; ?>
        <?php if (!empty($campania['estado'])): ?> · Estado: <?= $view->e((string) $campania['estado']) ?><?php endif; ?>
    </p>
    <p class="rango"><strong>Del <?= $view->e($desde) ?> al <?= $view->e($hasta) ?></strong></p>
    <p class="muted" style="margin-top:50pt">Generado el <?= $view->e($generado_en) ?></p>
</div>

<pagebreak/>

<h2>Resumen de la campaña</h2>
<table class="kpi-row">
    <tr>
    <?php foreach ($kpis as $i => [$label, $valor]): ?>
        <td>
            <div class="kpi-label"><?= $view->e($label) ?></div>
            <div class="kpi-value"><?= $view->e($valor) ?></div>
        </td>
        <?php if (($i + 1) % 3 === 0): ?></tr><tr><?php endif; ?>
    <?php endforeach; ?>
    </tr>
</table>

<?php
// Filtrar el desglose por tipo a las métricas relevantes al objetivo.
$tiposRelevantes = ObjetivoCampania::metricasRelevantes($objetivo);
$resultadosPorTipoFiltrado = $tiposRelevantes === []
    ? []
    : array_values(array_filter(
        $resultados_por_tipo,
        static fn ($r) => in_array($r['tipo'], $tiposRelevantes, true)
    ));
?>
<?php if ($resultadosPorTipoFiltrado !== []): ?>
<h2>Resultados por tipo</h2>
<table class="kpi-row">
    <tr>
    <?php foreach ($resultadosPorTipoFiltrado as $i => $r):
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
<?php endif; ?>

<?php if ($evolucion !== []): ?>
<h2>Evolución diaria</h2>
<table>
    <thead>
        <tr><th>Fecha</th><th class="num">Gasto</th><th class="num"><?= $view->e($labelResultadosCorto) ?></th></tr>
    </thead>
    <tbody>
    <?php foreach ($evolucion as $e): ?>
        <tr>
            <td><?= $view->e((string) $e['fecha']) ?></td>
            <td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($e['gasto']) ?></td>
            <td class="num"><?= $fmtNum($e['resultados'] ?? 0) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2>Grupos de anuncios (<?= count($adsets) ?>)</h2>
<?php if ($adsets === []): ?>
    <p class="muted">No hay grupos de anuncios con datos en este rango.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Grupo</th>
                <th>Estado</th>
                <th class="num">Gasto</th>
                <th class="num">Impresiones</th>
                <th class="num">Clicks</th>
                <th class="num">CTR</th>
                <th class="num">CPC</th>
                <th class="num">Resultados</th>
                <th class="num">Costo p/result.</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($adsets as $g):
            // Objetivo efectivo POR FILA: goals genéricos (REACH/IMPRESSIONS)
            // caen al objetivo de campaña — igual que el CASE del número.
            $sublabelSrc = ObjetivoCampania::objetivoEfectivo(
                (string) ($g['optimization_goal'] ?? ''),
                (string) ($g['objetivo_campania'] ?? $objetivoCampania)
            );
            $labelCorto = ObjetivoCampania::nombreCortoResultados((string) $sublabelSrc);
        ?>
            <tr>
                <td><?= $view->e((string) $g['adset_nombre']) ?></td>
                <td><?= $view->e((string) ($g['estado'] ?? '—')) ?></td>
                <td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($g['gasto']) ?></td>
                <td class="num"><?= $fmtNum($g['impresiones']) ?></td>
                <td class="num"><?= $fmtNum($g['clicks']) ?></td>
                <td class="num"><?= $fmtPct($g['ctr']) ?></td>
                <td class="num"><?= $g['cpc'] !== null ? $fmtMoneda($g['cpc']) : '—' ?></td>
                <td class="num"><?php if (((int) ($g['resultados'] ?? 0)) > 0): ?><?= $fmtNum($g['resultados']) ?> <?= $view->e(mb_strtolower($labelCorto)) ?><?php else: ?>—<?php endif; ?></td>
                <td class="num"><?= isset($g['costo_por_resultado']) && $g['costo_por_resultado'] !== null ? $view->e($mon) . ' ' . $fmtMoneda($g['costo_por_resultado']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
// Mismo cuadro de costos que la pantalla (comisión 20% + IVA 10%).
$gastoCostos = (float) ($totales['gasto'] ?? 0);
if ($gastoCostos > 0):
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
<?php endif; ?>

<?php if ($comentarios !== null && trim($comentarios) !== ''): ?>
<h2>Comentarios estratégicos</h2>
<div style="white-space:pre-wrap;font-size:10pt;line-height:1.5"><?= $view->e($comentarios) ?></div>
<?php endif; ?>
