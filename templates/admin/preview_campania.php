<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var array<string,mixed> $campania */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $adsets */
/** @var array<int, list<array<string,mixed>>> $anuncios_por_adset */
/** @var string $desde */
/** @var string $hasta */
/** @var string $preset */

use MisterCo\Reports\Domain\ObjetivoCampania;

$mon = (string) ($campania['moneda'] ?? '');
$objetivo = (string) ($campania['objetivo'] ?? '');
$labelResultados = ObjetivoCampania::nombreResultados($objetivo);
$labelResultadosCorto = ObjetivoCampania::nombreCortoResultados($objetivo);
$labelCostoPorResultado = 'Costo por ' . mb_strtolower($labelResultadosCorto);
$ocultarConversaciones = ObjetivoCampania::conversacionesEsRedundante($objetivo);
$ocultarLeads = ObjetivoCampania::leadsEsRedundante($objetivo);

$fmtMoneda = static fn ($v) => number_format((float) $v, 2, ',', '.');
$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>

<div class="preview-banner">
    <span>👁️ Vista previa de <strong><?= $view->e((string) $cliente['nombre_comercial']) ?></strong>
        — detalle de campaña como lo ve el cliente.</span>
    <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/dashboard') ?>" class="btn btn--link">Volver al dashboard</a>
</div>

<section class="shell__body">
    <p><a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/dashboard') ?>">← Volver al dashboard</a></p>

    <h1><?= $view->e((string) $campania['nombre']) ?></h1>
    <p class="muted">
        Cuenta: <?= $view->e((string) $campania['cuenta_nombre']) ?>
        <?php if ($campania['objetivo']): ?> · Objetivo: <?= $view->e($labelResultados) ?><?php endif; ?>
        <?php if ($campania['estado']): ?> · Estado: <?= $view->e((string) $campania['estado']) ?><?php endif; ?>
    </p>

    <form method="GET" class="dashboard-filters">
        <label class="field">
            <span class="field__label">Rango</span>
            <select class="field__input" name="preset" onchange="this.form.submit()">
                <option value="hoy" <?= $preset === 'hoy' ? 'selected' : '' ?>>Hoy</option>
                <option value="ayer" <?= $preset === 'ayer' ? 'selected' : '' ?>>Ayer</option>
                <option value="ultimos_7_dias" <?= $preset === 'ultimos_7_dias' ? 'selected' : '' ?>>Últimos 7 días</option>
                <option value="ultimos_30_dias" <?= $preset === 'ultimos_30_dias' ? 'selected' : '' ?>>Últimos 30 días</option>
                <option value="mes_actual" <?= $preset === 'mes_actual' ? 'selected' : '' ?>>Mes actual</option>
                <option value="mes_pasado" <?= $preset === 'mes_pasado' ? 'selected' : '' ?>>Mes pasado</option>
            </select>
        </label>
        <span class="muted" style="align-self:center"><?= $view->e($desde) ?> → <?= $view->e($hasta) ?></span>
    </form>

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
        <?php if (((int) ($totales['conversaciones'] ?? 0)) > 0 && !$ocultarConversaciones): ?>
            <div class="kpi"><span class="kpi__label">Conversaciones</span><span class="kpi__value"><?= $fmtNum($totales['conversaciones']) ?></span></div>
            <?php if (isset($totales['costo_por_conversacion']) && $totales['costo_por_conversacion'] !== null): ?>
                <div class="kpi"><span class="kpi__label">Costo por conversación</span><span class="kpi__value"><?= $view->e($mon) ?> <?= $fmtMoneda($totales['costo_por_conversacion']) ?></span></div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (((int) ($totales['leads'] ?? 0)) > 0 && !$ocultarLeads): ?>
            <div class="kpi"><span class="kpi__label">Clientes potenciales</span><span class="kpi__value"><?= $fmtNum($totales['leads']) ?></span></div>
        <?php endif; ?>
        <?php if (((int) ($totales['landing_page_views'] ?? 0)) > 0): ?>
            <div class="kpi"><span class="kpi__label">Visitas página</span><span class="kpi__value"><?= $fmtNum($totales['landing_page_views']) ?></span></div>
        <?php endif; ?>
    </div>

    <article class="card" style="margin-top:1.5rem">
        <h2>Grupos de anuncios (<?= count($adsets) ?>)</h2>
        <?php if ($adsets === []): ?>
            <p class="muted">No hay grupos visibles para este rango.</p>
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
                    $objAdset = (string) ($g['objetivo_campania'] ?? $objetivo);
                    $labelCorto = ObjetivoCampania::nombreCortoResultados($objAdset);
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
            <p class="muted"><?= $totalAds ?> anuncio<?= $totalAds === 1 ? '' : 's' ?> visible<?= $totalAds === 1 ? '' : 's' ?> para el cliente.</p>

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
</section>
