<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $campania */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $anuncios */
/** @var string $desde */
/** @var string $hasta */
/** @var string $preset */

$mon = (string) ($campania['moneda'] ?? '');
$fmtMoneda = static fn ($v) => number_format((float) $v, 2, ',', '.');
$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';
?>
<?= $view->renderPartial('partials/cliente_header', ['usuario' => $usuario]) ?>

<section class="shell__body">
    <p><a href="<?= $view->url('/cliente') ?>">← Volver al dashboard</a></p>

    <h1><?= $view->e((string) $campania['nombre']) ?></h1>
    <p class="muted">
        Cuenta: <?= $view->e((string) $campania['cuenta_nombre']) ?>
        <?php if ($campania['objetivo']): ?> · Objetivo: <?= $view->e((string) $campania['objetivo']) ?><?php endif; ?>
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
        <div class="kpi">
            <span class="kpi__label">Gasto</span>
            <span class="kpi__value"><?= $view->e($mon) ?> <?= $fmtMoneda($totales['gasto'] ?? 0) ?></span>
        </div>
        <div class="kpi">
            <span class="kpi__label">Impresiones</span>
            <span class="kpi__value"><?= $fmtNum($totales['impresiones'] ?? 0) ?></span>
        </div>
        <div class="kpi">
            <span class="kpi__label">Alcance</span>
            <span class="kpi__value"><?= $fmtNum($totales['alcance'] ?? 0) ?></span>
        </div>
        <div class="kpi">
            <span class="kpi__label">Clicks</span>
            <span class="kpi__value"><?= $fmtNum($totales['clicks'] ?? 0) ?></span>
        </div>
        <div class="kpi">
            <span class="kpi__label">CTR</span>
            <span class="kpi__value"><?= $fmtPct($totales['ctr'] ?? null) ?></span>
        </div>
        <div class="kpi">
            <span class="kpi__label">CPC</span>
            <span class="kpi__value"><?= isset($totales['cpc']) && $totales['cpc'] !== null ? $fmtMoneda($totales['cpc']) : '—' ?></span>
        </div>
    </div>

    <article class="card" style="margin-top:1.5rem">
        <h2>Anuncios visibles (<?= count($anuncios) ?>)</h2>
        <?php if ($anuncios === []): ?>
            <p class="muted">No hay anuncios visibles para este rango.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Anuncio</th>
                        <th>Conjunto</th>
                        <th>Estado</th>
                        <th class="num">Gasto</th>
                        <th class="num">Impresiones</th>
                        <th class="num">Clicks</th>
                        <th class="num">CTR</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($anuncios as $a): ?>
                    <tr>
                        <td><?= $view->e((string) $a['nombre']) ?></td>
                        <td><?= $view->e((string) ($a['adset_nombre'] ?? '—')) ?></td>
                        <td><?= $view->e((string) ($a['estado'] ?? '—')) ?></td>
                        <td class="num"><?= $view->e($mon) ?> <?= $fmtMoneda($a['gasto']) ?></td>
                        <td class="num"><?= $fmtNum($a['impresiones']) ?></td>
                        <td class="num"><?= $fmtNum($a['clicks']) ?></td>
                        <td class="num"><?= $fmtPct($a['ctr']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>
