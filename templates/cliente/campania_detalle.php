<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $campania */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $adsets */
/** @var array<int, list<array<string,mixed>>> $anuncios_por_adset */
/** @var string $desde */
/** @var string $hasta */
/** @var string|null $mes_seleccionado */
/** @var list<string> $meses_disponibles */

use MisterCo\Reports\Domain\ObjetivoCampania;

$mon = (string) ($campania['moneda'] ?? '');
$objetivo = (string) ($campania['objetivo'] ?? '');
$labelResultados = ObjetivoCampania::nombreResultados($objetivo);
$labelResultadosCorto = ObjetivoCampania::nombreCortoResultados($objetivo);
$labelCostoPorResultado = 'Costo por ' . mb_strtolower($labelResultadosCorto);
$ocultarConversaciones = ObjetivoCampania::conversacionesEsRedundante($objetivo);
$ocultarLeads = ObjetivoCampania::leadsEsRedundante($objetivo);

// PYG no usa decimales; redondeamos hacia arriba al guaraní entero.
$fmtMoneda = fn ($v) => $mon === 'PYG'
    ? number_format((float) ceil((float) $v), 0, ',', '.')
    : number_format((float) $v, 2, ',', '.');
$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
?>
<?= $view->renderPartial('partials/cliente_header', ['usuario' => $usuario]) ?>

<section class="shell__body">
    <p><a href="<?= $view->url('/cliente') ?>">← Volver al dashboard</a></p>

    <h1><?= $view->e((string) $campania['nombre']) ?></h1>
    <p class="muted">
        Cuenta: <?= $view->e((string) $campania['cuenta_nombre']) ?>
        <?php if ($campania['objetivo']): ?> · Objetivo: <?= $view->e($labelResultados) ?><?php endif; ?>
        <?php if ($campania['estado']): ?> · Estado: <?= $view->e((string) $campania['estado']) ?><?php endif; ?>
    </p>

    <?php
    $mesesNombre = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $formatMes = static function (string $yyyymm) use ($mesesNombre): string {
        $partes = explode('-', $yyyymm);
        if (count($partes) !== 2) return $yyyymm;
        $mes = (int) $partes[1];
        return ucfirst($mesesNombre[$mes - 1] ?? '') . ' ' . $partes[0];
    };
    ?>
    <?php if ($meses_disponibles === []): ?>
        <p class="muted">Aún no hay datos importados para esta campaña.</p>
    <?php else: ?>
        <div class="dashboard-filters">
            <form method="GET" class="dashboard-filters" style="margin:0">
                <label class="field">
                    <span class="field__label">Mes</span>
                    <select class="field__input" name="mes" onchange="this.form.submit()">
                        <?php foreach ($meses_disponibles as $m): ?>
                            <option value="<?= $view->e($m) ?>" <?= $m === $mes_seleccionado ? 'selected' : '' ?>>
                                <?= $view->e($formatMes($m)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <span class="muted" style="align-self:center"><?= $view->e($desde) ?> → <?= $view->e($hasta) ?></span>
            </form>
            <form method="POST" action="<?= $view->url('/cliente/campanias/' . ((int) $campania['id']) . '/reporte.pdf') ?>" style="align-self:center;margin:0">
                <?= $view->csrfField() ?>
                <input type="hidden" name="mes" value="<?= $view->e((string) $mes_seleccionado) ?>">
                <button type="submit" class="btn btn--primary">📄 Exportar PDF</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (!empty($analisis ?? '')): ?>
        <p class="campania-analisis"><?= $view->e($analisis) ?></p>
    <?php endif; ?>

    <div class="kpi-grid">
        <div class="kpi"><span class="kpi__label">Gasto</span><span class="kpi__value"><?= $view->e($mon) ?> <?= $fmtMoneda($totales['gasto'] ?? 0) ?></span></div>
        <div class="kpi"><span class="kpi__label">Impresiones</span><span class="kpi__value"><?= $fmtNum($totales['impresiones'] ?? 0) ?></span></div>
        <div class="kpi"><span class="kpi__label">Alcance</span><span class="kpi__value"><?= $fmtNum($totales['alcance'] ?? 0) ?></span></div>
        <div class="kpi"><span class="kpi__label">Clicks</span><span class="kpi__value"><?= $fmtNum($totales['clicks'] ?? 0) ?></span></div>
        <div class="kpi"><span class="kpi__label">CTR</span><span class="kpi__value"><?= $fmtPct($totales['ctr'] ?? null) ?></span></div>
        <div class="kpi"><span class="kpi__label">CPC</span><span class="kpi__value"><?= isset($totales['cpc']) && $totales['cpc'] !== null ? $fmtMoneda($totales['cpc']) : '—' ?></span></div>
        <?php if (((int) ($totales['resultados'] ?? 0)) > 0): ?>
            <div class="kpi"><span class="kpi__label"><?= $view->e($labelResultados) ?></span><span class="kpi__value"><?= $fmtNum($totales['resultados']) ?></span></div>
            <?php if (isset($totales['costo_por_resultado']) && $totales['costo_por_resultado'] !== null): ?>
                <div class="kpi"><span class="kpi__label"><?= $view->e($labelCostoPorResultado) ?></span><span class="kpi__value"><?= $view->e($mon) ?> <?= $fmtMoneda($totales['costo_por_resultado']) ?></span></div>
            <?php endif; ?>
        <?php endif; ?>
        <?php
        // El resultado del objetivo ya se muestra arriba como el KPI de resultados.
        // Las métricas conversaciones/leads/visitas solo se muestran como KPI aparte
        // cuando son RELEVANTES al objetivo y NO son ya el resultado (para no duplicar).
        // Meta reporta acciones colaterales — p. ej. 2 conversaciones en una campana de
        // awareness — que NO son el objetivo; esas se ocultan para no confundir.
        ?>
        <?php if (ObjetivoCampania::metricaEsRelevante($objetivo, 'conversaciones') && !$ocultarConversaciones && ((int) ($totales['conversaciones'] ?? 0)) > 0): ?>
            <div class="kpi"><span class="kpi__label">Conversaciones</span><span class="kpi__value"><?= $fmtNum($totales['conversaciones']) ?></span></div>
            <?php if (isset($totales['costo_por_conversacion']) && $totales['costo_por_conversacion'] !== null): ?>
                <div class="kpi"><span class="kpi__label">Costo por conversación</span><span class="kpi__value"><?= $view->e($mon) ?> <?= $fmtMoneda($totales['costo_por_conversacion']) ?></span></div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (ObjetivoCampania::metricaEsRelevante($objetivo, 'leads') && !$ocultarLeads && ((int) ($totales['leads'] ?? 0)) > 0): ?>
            <div class="kpi"><span class="kpi__label">Clientes potenciales</span><span class="kpi__value"><?= $fmtNum($totales['leads']) ?></span></div>
        <?php endif; ?>
        <?php if (ObjetivoCampania::metricaEsRelevante($objetivo, 'visitas') && !ObjetivoCampania::visitasEsRedundante($objetivo) && ((int) ($totales['landing_page_views'] ?? 0)) > 0): ?>
            <div class="kpi"><span class="kpi__label">Visitas página</span><span class="kpi__value"><?= $fmtNum($totales['landing_page_views']) ?></span></div>
        <?php endif; ?>
    </div>

    <?php
    // Filtramos el desglose por tipo a las métricas relevantes al objetivo,
    // para no mostrar tipos residuales (p. ej. conversaciones en awareness).
    $tiposRelevantes = ObjetivoCampania::metricasRelevantes($objetivo);
    $resultadosPorTipoFiltrado = $tiposRelevantes === []
        ? []
        : array_values(array_filter(
            $resultados_por_tipo ?? [],
            static fn ($r) => in_array($r['tipo'], $tiposRelevantes, true)
        ));
    ?>
    <?php if ($resultadosPorTipoFiltrado !== []): ?>
        <?= $view->renderPartial('partials/resultados_por_tipo', [
            'resultados_por_tipo' => $resultadosPorTipoFiltrado,
            'mon' => $mon,
            'fmtMoneda' => $fmtMoneda,
            'fmtNum' => $fmtNum,
        ]) ?>
    <?php endif; ?>

    <?php if (!empty($evolucion ?? [])): ?>
        <article class="card" style="margin-top:1.5rem">
            <h2>Evolución diaria</h2>
            <canvas id="grafico-evolucion-campania" height="120"></canvas>
        </article>
    <?php endif; ?>

    <article class="card" style="margin-top:1.5rem">
        <h2>Grupos de anuncios (<?= count($adsets) ?>)</h2>
        <?php if ($adsets === []): ?>
            <p class="muted">No hay grupos de anuncios visibles para este rango.</p>
        <?php else: ?>
            <table class="table">
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
                        <th class="num">Costo p/resultado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($adsets as $g):
                    $sublabelSrc = $g['optimization_goal'] ?: ($g['objetivo_campania'] ?? $objetivo);
                    $labelCorto = ObjetivoCampania::nombreCortoResultados((string) $sublabelSrc);
                ?>
                    <tr>
                        <td><strong><?= $view->e((string) $g['adset_nombre']) ?></strong>
                            <?php if ($g['optimization_goal']): ?>
                                <br><small class="muted"><?= $view->e((string) $g['optimization_goal']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $view->e((string) ($g['estado'] ?? '—')) ?></td>
                        <td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($g['gasto']) ?></td>
                        <td class="num"><?= $fmtNum($g['impresiones']) ?></td>
                        <td class="num"><?= $fmtNum($g['clicks']) ?></td>
                        <td class="num"><?= $fmtPct($g['ctr']) ?></td>
                        <td class="num"><?= $g['cpc'] !== null ? $fmtMoneda($g['cpc']) : '—' ?></td>
                        <td class="num">
                            <?php if (((int) ($g['resultados'] ?? 0)) > 0): ?>
                                <?= $fmtNum($g['resultados']) ?>
                                <br><small class="muted"><?= $view->e(mb_strtolower($labelCorto)) ?></small>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="num">
                            <?php if (isset($g['costo_por_resultado']) && $g['costo_por_resultado'] !== null): ?>
                                <?= $view->e($mon) ?> <?= $fmtMoneda($g['costo_por_resultado']) ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>

    <article class="card" style="margin-top:1.5rem">
        <h2>Anuncios</h2>
        <?php $totalAds = array_sum(array_map('count', $anuncios_por_adset)); ?>
        <?php if ($totalAds === 0): ?>
            <p class="muted">No hay anuncios visibles para este rango.</p>
        <?php else: ?>
            <p class="muted"><?= $totalAds ?> anuncio<?= $totalAds === 1 ? '' : 's' ?> distribuido<?= $totalAds === 1 ? '' : 's' ?> en los grupos de arriba.</p>

            <?php foreach ($adsets as $g): ?>
                <?php $listaAds = $anuncios_por_adset[(int) $g['id']] ?? []; ?>
                <?php if ($listaAds === []) continue; ?>
                <section class="adset-block">
                    <header class="adset-block__header">
                        <h3><?= $view->e((string) $g['adset_nombre']) ?></h3>
                        <span class="muted"><?= count($listaAds) ?> anuncio<?= count($listaAds) === 1 ? '' : 's' ?> · <?= $view->e($mon) ?> <?= $fmtMoneda($g['gasto']) ?> gasto</span>
                    </header>
                    <div class="ads-list">
                        <?php foreach ($listaAds as $a): ?>
                            <?= $view->renderPartial('partials/anuncio_card', [
                                'a' => $a,
                                'mon' => $mon,
                                'fmtMoneda' => $fmtMoneda,
                                'fmtNum' => $fmtNum,
                                'fmtPct' => $fmtPct,
                                'labelResultadosCorto' => $labelResultadosCorto,
                                'ocultarConversaciones' => $ocultarConversaciones,
                                'ocultarLeads' => $ocultarLeads,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </article>

    <?= $view->renderPartial('partials/costos_campania', ['totales' => $totales, 'mon' => $mon]) ?>
</section>

<?php if (!empty($evolucion ?? [])): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    const evolucion = <?= json_encode($evolucion, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
    new Chart(document.getElementById('grafico-evolucion-campania'), {
        type: 'line',
        data: {
            labels: evolucion.map(e => e.fecha),
            datasets: [
                { label: 'Gasto (<?= $view->e($mon) ?>)', data: evolucion.map(e => e.gasto), borderColor: '#1a2f6e', backgroundColor: 'rgba(26,47,110,0.08)', tension: 0.3, yAxisID: 'y1', fill: true },
                { label: <?= json_encode($labelResultadosCorto) ?>, data: evolucion.map(e => e.resultados), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', tension: 0.3, yAxisID: 'y2', fill: true }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y1: { position: 'left', title: { display: true, text: 'Gasto' }, beginAtZero: true },
                y2: { position: 'right', title: { display: true, text: <?= json_encode($labelResultadosCorto) ?> }, grid: { drawOnChartArea: false }, beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
</script>
<?php endif; ?>
