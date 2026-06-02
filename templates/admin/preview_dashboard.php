<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var int $campanias_asignadas_count */
/** @var string $moneda */
/** @var string $desde */
/** @var string $hasta */
/** @var string $preset */
/** @var array<string,mixed> $totales */
/** @var list<array<string,mixed>> $campanias */
/** @var list<array<string,mixed>> $evolucion */
/** @var list<string> $widgets_visibles */
/** @var array<string,string> $widgets_disponibles */

$fmtMoneda = static fn ($v) => number_format((float) $v, 2, ',', '.');
$fmtNum = static fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtPct = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . '%';

$mon = $moneda;
$valorWidget = static function (string $codigo) use ($totales, $fmtMoneda, $fmtNum, $fmtPct, $mon): string {
    return match ($codigo) {
        'gasto' => $mon . ' ' . $fmtMoneda($totales['gasto'] ?? 0),
        'impresiones' => $fmtNum($totales['impresiones'] ?? 0),
        'clicks' => $fmtNum($totales['clicks_totales'] ?? 0),
        'ctr' => $fmtPct($totales['ctr'] ?? null),
        'cpc' => isset($totales['cpc']) && $totales['cpc'] !== null ? $mon . ' ' . $fmtMoneda($totales['cpc']) : '—',
        'cpm' => isset($totales['cpm']) && $totales['cpm'] !== null ? $mon . ' ' . $fmtMoneda($totales['cpm']) : '—',
        'alcance' => $fmtNum($totales['alcance'] ?? 0),
        default => '—',
    };
};
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>

<div class="preview-banner">
    <span>👁️ Vista previa del dashboard de <strong><?= $view->e((string) $cliente['nombre_comercial']) ?></strong>
        — exactamente lo que ve el cliente.</span>
    <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id'])) ?>" class="btn btn--link">Volver al cliente</a>
</div>

<section class="shell__body">
    <div class="header-row">
        <div>
            <h1>Mi dashboard <span class="muted" style="font-weight:normal;font-size:0.9rem">(como lo ve <?= $view->e((string) $cliente['nombre_comercial']) ?>)</span></h1>
            <p class="muted"><?= $campanias_asignadas_count ?> campaña<?= $campanias_asignadas_count === 1 ? '' : 's' ?> asignada<?= $campanias_asignadas_count === 1 ? '' : 's' ?></p>
        </div>
    </div>

    <?php if ($campanias_asignadas_count === 0): ?>
        <div class="alert alert--warning">
            Este cliente no tiene campañas asignadas todavía.
            <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id'])) ?>">Asignar campañas</a>.
        </div>
    <?php else: ?>

    <form method="GET" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/dashboard') ?>" class="dashboard-filters">
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
        <span class="muted" style="align-self:center;font-size:0.85rem"><?= $view->e($desde) ?> → <?= $view->e($hasta) ?></span>
    </form>

    <div class="kpi-grid">
        <?php foreach ($widgets_visibles as $codigo): ?>
            <div class="kpi">
                <span class="kpi__label"><?= $view->e($widgets_disponibles[$codigo] ?? $codigo) ?></span>
                <span class="kpi__value"><?= $view->e($valorWidget($codigo)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>


    <article class="card" style="margin-top:1.5rem">
        <h2>Campañas (<?= count($campanias) ?>)</h2>
        <?php if ($campanias === []): ?>
            <p class="muted">No hay datos para el rango <?= $view->e($desde) ?> → <?= $view->e($hasta) ?>.</p>
        <?php else: ?>
            <table class="table">
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
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($campanias as $c): ?>
                    <tr>
                        <td>
                            <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/campanias/' . ((int) $c['campania_id'])) ?>">
                                <?= $view->e((string) $c['campania']) ?>
                            </a>
                        </td>
                        <td><small class="muted"><?= $view->e((string) ($c['cuenta_nombre'] ?? '')) ?></small></td>
                        <td><?= $view->e((string) ($c['estado'] ?? '—')) ?></td>
                        <td class="num"><?= $view->e((string) ($c['moneda'] ?? '')) ?> <?= $fmtMoneda($c['gasto']) ?></td>
                        <td class="num"><?= $fmtNum($c['impresiones']) ?></td>
                        <td class="num"><?= $fmtNum($c['clicks']) ?></td>
                        <td class="num"><?= $fmtPct($c['ctr']) ?></td>
                        <td class="num"><?= $c['cpc'] !== null ? $fmtMoneda($c['cpc']) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>

    <?php endif; ?>
</section>

