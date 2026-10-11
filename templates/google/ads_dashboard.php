<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var bool $es_admin */
/** @var bool $asignado */
/** @var string $base */
/** @var int $campanias_asignadas_count */
/** @var string $moneda */
/** @var \MisterCo\Reports\Domain\PeriodoReporte $periodo */
/** @var list<string> $meses_disponibles */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $campanias */
/** @var list<array<string,mixed>> $evolucion */

use MisterCo\Reports\Domain\EtiquetasGoogle;

$mon = $moneda;
$fmtMoneda = static fn ($v) => $mon === 'PYG'
    ? number_format((float) ceil((float) $v), 0, ',', '.')
    : number_format((float) $v, 2, ',', '.');
$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtDec = static fn ($v) => number_format((float) $v, fmod((float) $v, 1.0) == 0.0 ? 0 : 2, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
$conMoneda = static fn ($v) => $v === null ? '—' : $mon . ' ' . $fmtMoneda($v);
$hayValor = (float) ($totales['valor_conversiones'] ?? 0) > 0;
?>
<?= $view->renderPartial('partials/google_encabezado', [
    'usuario' => $usuario, 'cliente' => $cliente, 'es_admin' => $es_admin,
    'seccion' => 'google_ads', 'descripcion' => 'Vista previa de Google Ads',
]) ?>

<section class="shell__body">
    <div class="header-row">
        <div>
            <h1>Google Ads</h1>
            <p class="muted"><?= $campanias_asignadas_count ?> campaña<?= $campanias_asignadas_count === 1 ? '' : 's' ?> asignada<?= $campanias_asignadas_count === 1 ? '' : 's' ?></p>
        </div>
    </div>

    <?php if (!$asignado): ?>
        <div class="alert alert--warning">
            Este cliente no tiene campañas de Google Ads asignadas.
            <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id'])) ?>">Asignar campañas</a>.
        </div>
    <?php elseif ($meses_disponibles === []): ?>
        <p class="muted">Aún no hay datos importados para las campañas de Google Ads.</p>
    <?php else: ?>
        <?= $view->renderPartial('partials/filtro_periodo', [
            'action' => $view->url($base),
            'periodo' => $periodo,
            'meses_disponibles' => $meses_disponibles,
            'exportar' => ['metodo' => 'get', 'url' => $view->url($base . '/reporte')],
        ]) ?>

        <div class="kpi-grid">
            <div class="kpi"><span class="kpi__label">Inversión</span><span class="kpi__value"><?= $view->e($conMoneda($totales['gasto'])) ?></span></div>
            <div class="kpi"><span class="kpi__label">Impresiones</span><span class="kpi__value"><?= $fmtNum($totales['impresiones']) ?></span></div>
            <div class="kpi"><span class="kpi__label">Clics</span><span class="kpi__value"><?= $fmtNum($totales['clicks']) ?></span></div>
            <div class="kpi"><span class="kpi__label">CTR</span><span class="kpi__value"><?= $fmtPct($totales['ctr']) ?></span></div>
            <div class="kpi"><span class="kpi__label">CPC promedio</span><span class="kpi__value"><?= $view->e($conMoneda($totales['cpc'])) ?></span></div>
            <div class="kpi kpi--resultado">
                <span class="kpi__label">Conversiones</span>
                <span class="kpi__value"><?= $fmtDec($totales['conversiones']) ?></span>
                <?php if ($totales['costo_por_conversion'] !== null): ?>
                    <span class="kpi__sub muted"><?= $view->e($conMoneda($totales['costo_por_conversion'])) ?> por conversión</span>
                <?php endif; ?>
            </div>
            <?php if ($hayValor): ?>
                <div class="kpi"><span class="kpi__label">Valor de conversiones</span><span class="kpi__value"><?= $view->e($conMoneda($totales['valor_conversiones'])) ?></span></div>
            <?php endif; ?>
        </div>

        <?php if ($evolucion !== []): ?>
            <article class="card" style="margin-top:1.5rem">
                <h2>Evolución diaria</h2>
                <canvas id="grafico-google-ads" height="110"></canvas>
            </article>
        <?php endif; ?>

        <article class="card" style="margin-top:1.5rem">
            <h2>Campañas (<?= count($campanias) ?>)</h2>
            <?php if ($campanias === []): ?>
                <p class="muted">No hay datos para el rango <?= $view->e($periodo->desde) ?> → <?= $view->e($periodo->hasta) ?>.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Campaña</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th class="num">Inversión</th>
                            <th class="num">Impresiones</th>
                            <th class="num">Clics</th>
                            <th class="num">CTR</th>
                            <th class="num">CPC</th>
                            <th class="num">Conversiones</th>
                            <th class="num">Costo/conv.</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($campanias as $c): ?>
                        <tr>
                            <td><?= $view->e((string) $c['campania']) ?><br><small class="muted"><?= $view->e((string) $c['cuenta_nombre']) ?></small></td>
                            <td><?= $view->e(EtiquetasGoogle::tipoCampania($c['tipo_canal'])) ?></td>
                            <td><?= $view->e(EtiquetasGoogle::estadoCampania($c['estado'])) ?></td>
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
        </article>

        <?= $view->renderPartial('partials/costos_campania', ['totales' => $totales, 'mon' => $mon]) ?>
    <?php endif; ?>
</section>

<?php if ($asignado && $evolucion !== []): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    const evolucion = <?= json_encode($evolucion, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
    new Chart(document.getElementById('grafico-google-ads'), {
        type: 'line',
        data: {
            labels: evolucion.map(e => e.fecha),
            datasets: [
                { label: 'Inversión (<?= $view->e($mon) ?>)', data: evolucion.map(e => e.gasto), borderColor: '#1a2f6e', backgroundColor: 'rgba(26,47,110,0.08)', tension: 0.3, yAxisID: 'y1', fill: true },
                { label: 'Clics', data: evolucion.map(e => e.clicks), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', tension: 0.3, yAxisID: 'y2', fill: true }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y1: { position: 'left', title: { display: true, text: 'Inversión' }, beginAtZero: true },
                y2: { position: 'right', title: { display: true, text: 'Clics' }, grid: { drawOnChartArea: false }, beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
</script>
<?php endif; ?>
