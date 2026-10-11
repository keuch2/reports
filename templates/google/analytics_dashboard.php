<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var bool $es_admin */
/** @var bool $asignado */
/** @var string $base */
/** @var list<array<string,mixed>> $propiedades_asignadas */
/** @var string $moneda */
/** @var \MisterCo\Reports\Domain\PeriodoReporte $periodo */
/** @var list<string> $meses_disponibles */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $por_propiedad */
/** @var list<array<string,mixed>> $canales */
/** @var list<array<string,mixed>> $evolucion */

use MisterCo\Reports\Domain\EtiquetasGoogle;

$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtDec = static fn ($v) => number_format((float) $v, fmod((float) $v, 1.0) == 0.0 ? 0 : 2, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
$fmtMoneda = static fn ($v) => trim($moneda . ' ' . number_format((float) $v, $moneda === 'PYG' ? 0 : 2, ',', '.'));
$hayIngresos = (float) ($totales['ingresos'] ?? 0) > 0;
$totalSesionesCanal = array_sum(array_map(static fn ($c) => (int) $c['sesiones'], $canales));
$nPropiedades = count($propiedades_asignadas);
?>
<?= $view->renderPartial('partials/encabezado_cliente', [
    'usuario' => $usuario, 'cliente' => $cliente, 'es_admin' => $es_admin,
    'seccion' => 'analytics', 'descripcion' => 'Vista previa de Google Analytics',
]) ?>

<section class="shell__body">
    <div class="header-row">
        <div>
            <h1>Google Analytics</h1>
            <p class="muted">
                <?php if ($nPropiedades === 1): ?>
                    Sitio: <?= $view->e((string) $propiedades_asignadas[0]['nombre']) ?>
                <?php else: ?>
                    <?= $nPropiedades ?> propiedades asignadas
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (!$asignado): ?>
        <div class="alert alert--warning">
            Este cliente no tiene propiedades de Google Analytics asignadas.
            <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id'])) ?>">Asignar propiedades</a>.
        </div>
    <?php elseif ($meses_disponibles === []): ?>
        <p class="muted">Aún no hay datos importados de Google Analytics.</p>
    <?php else: ?>
        <?= $view->renderPartial('partials/filtro_periodo', [
            'action' => $view->url($base),
            'periodo' => $periodo,
            'meses_disponibles' => $meses_disponibles,
            'exportar' => ['metodo' => 'get', 'url' => $view->url($base . '/reporte')],
        ]) ?>

        <div class="kpi-grid">
            <div class="kpi"><span class="kpi__label">Sesiones</span><span class="kpi__value"><?= $fmtNum($totales['sesiones']) ?></span></div>
            <div class="kpi"><span class="kpi__label">Usuarios</span><span class="kpi__value"><?= $fmtNum($totales['usuarios']) ?></span></div>
            <div class="kpi"><span class="kpi__label">Usuarios nuevos</span><span class="kpi__value"><?= $fmtNum($totales['usuarios_nuevos']) ?></span></div>
            <div class="kpi"><span class="kpi__label">Páginas vistas</span><span class="kpi__value"><?= $fmtNum($totales['vistas']) ?></span></div>
            <div class="kpi"><span class="kpi__label">Tasa de interacción</span><span class="kpi__value"><?= $fmtPct($totales['tasa_interaccion']) ?></span></div>
            <div class="kpi"><span class="kpi__label">Tiempo medio por sesión</span><span class="kpi__value"><?= $view->e(EtiquetasGoogle::duracion($totales['duracion_media_seg'])) ?></span></div>
            <div class="kpi kpi--resultado"><span class="kpi__label">Eventos clave</span><span class="kpi__value"><?= $fmtDec($totales['eventos_clave']) ?></span></div>
            <?php if ($hayIngresos): ?>
                <div class="kpi"><span class="kpi__label">Ingresos</span><span class="kpi__value"><?= $view->e($fmtMoneda($totales['ingresos'])) ?></span></div>
            <?php endif; ?>
        </div>
        <p class="muted" style="font-size:0.8rem;margin-top:0.5rem">
            Los usuarios se suman día a día: una misma persona que visita el sitio varios días cuenta más de una vez.
        </p>

        <?php if ($evolucion !== []): ?>
            <article class="card" style="margin-top:1.5rem">
                <h2>Evolución diaria</h2>
                <canvas id="grafico-analytics" height="110"></canvas>
            </article>
        <?php endif; ?>

        <article class="card" style="margin-top:1.5rem">
            <h2>Canales de tráfico</h2>
            <?php if ($canales === []): ?>
                <p class="muted">Sin datos por canal para este período.</p>
            <?php else: ?>
                <table class="table">
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
        </article>

        <?php if (count($por_propiedad) > 1): ?>
            <article class="card" style="margin-top:1.5rem">
                <h2>Por sitio</h2>
                <table class="table">
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
            </article>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($asignado && $evolucion !== []): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    const evolucion = <?= json_encode($evolucion, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
    new Chart(document.getElementById('grafico-analytics'), {
        type: 'line',
        data: {
            labels: evolucion.map(e => e.fecha),
            datasets: [
                { label: 'Sesiones', data: evolucion.map(e => e.sesiones), borderColor: '#1a2f6e', backgroundColor: 'rgba(26,47,110,0.08)', tension: 0.3, yAxisID: 'y1', fill: true },
                { label: 'Eventos clave', data: evolucion.map(e => e.eventos_clave), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', tension: 0.3, yAxisID: 'y2', fill: true }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y1: { position: 'left', title: { display: true, text: 'Sesiones' }, beginAtZero: true, ticks: { precision: 0 } },
                y2: { position: 'right', title: { display: true, text: 'Eventos clave' }, grid: { drawOnChartArea: false }, beginAtZero: true }
            }
        }
    });
</script>
<?php endif; ?>
