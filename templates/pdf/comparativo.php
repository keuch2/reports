<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $cliente */
/** @var array<string,mixed> $a */
/** @var array<string,mixed> $b */
/** @var list<array<string,mixed>> $filas */
/** @var bool $misma_moneda */
/** @var string $desde */
/** @var string $hasta */
/** @var string|null $comentarios */
/** @var string $generado_en */

// Mismo formato que templates/comparativo/comparar.php.
$fmtValor = static function (?float $v, string $formato, string $moneda): string {
    if ($v === null) {
        return '—';
    }
    return match ($formato) {
        'moneda' => $moneda . ' ' . ($moneda === 'PYG'
            ? number_format(ceil($v), 0, ',', '.')
            : number_format($v, 2, ',', '.')),
        'pct' => number_format($v, 2, ',', '.') . '%',
        'dec' => number_format($v, fmod($v, 1.0) == 0.0 ? 0 : 2, ',', '.'),
        default => number_format($v, 0, ',', '.'),
    };
};
$fmtVariacion = static fn (?float $v): string => $v === null ? '—' : ($v > 0 ? '+' : '') . number_format($v, 1, ',', '.') . '%';
$fmtFecha = static fn (string $f): string => date('d/m/Y', strtotime($f));
$fmtDia = static fn (float $v, string $moneda) => $moneda === 'PYG' ? number_format(ceil($v), 0, ',', '.') : number_format($v, 2, ',', '.');
?>
<?= $view->renderPartial('pdf/portada', [
    'cliente' => $cliente,
    'subtitulo' => 'Reporte comparativo',
    'detalle' => $a['nombre'] === $b['nombre'] ? $a['nombre'] : $a['nombre'] . ' vs ' . $b['nombre'],
    'desde' => $desde,
    'hasta' => $hasta,
    'generado_en' => $generado_en,
]) ?>

<h2>Qué se compara</h2>
<table class="kpi-row">
    <tr>
        <?php foreach (['A · Referencia' => $a, 'B · Comparar con' => $b] as $titulo => $l): ?>
            <td style="width:50%">
                <div class="kpi-label"><?= $view->e($titulo) ?> · <?= $view->e($l['plataforma']) ?></div>
                <div style="font-size:12pt;font-weight:700;margin-top:2pt"><?= $view->e($l['nombre']) ?></div>
                <div class="kpi-sub"><?= $view->e($l['cuenta']) ?></div>
                <div class="kpi-sub"><?= $fmtFecha($l['desde']) ?> al <?= $fmtFecha($l['hasta']) ?> (<?= (int) $l['dias'] ?> días)</div>
                <div class="kpi-sub">Resultado: <?= $view->e($l['label_resultado']) ?></div>
            </td>
        <?php endforeach; ?>
    </tr>
</table>
<?php if (!$misma_moneda): ?>
    <p class="muted">Las campañas usan monedas distintas (<?= $view->e($a['moneda']) ?> y <?= $view->e($b['moneda']) ?>): los montos no son directamente comparables.</p>
<?php endif; ?>

<h2>Resultados comparados</h2>
<table>
    <thead>
        <tr><th>Métrica</th><th class="num">A</th><th class="num">B</th><th class="num">Variación B vs A</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $f):
        $color = $f['mejor'] === 'b' ? '#047857' : ($f['mejor'] === 'a' ? '#b91c1c' : '#1a1d24');
    ?>
        <tr>
            <td><?= $view->e($f['etiqueta']) ?></td>
            <td class="num"><?= $view->e($fmtValor($f['a'], $f['formato'], $a['moneda'])) ?></td>
            <td class="num"><?= $view->e($fmtValor($f['b'], $f['formato'], $b['moneda'])) ?></td>
            <td class="num" style="color:<?= $color ?>;font-weight:600"><?= $view->e($fmtVariacion($f['variacion'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="muted" style="font-size:8pt">Verde: B mejora respecto de A · Rojo: B empeora. En costos (CPC, CPM, costo por resultado) bajar es mejorar.</p>

<h2>Evolución por día</h2>
<table>
    <thead>
        <tr>
            <th>Día</th>
            <th>Fecha A</th><th class="num">Inversión A</th><th class="num">Resultados A</th>
            <th>Fecha B</th><th class="num">Inversión B</th><th class="num">Resultados B</th>
        </tr>
    </thead>
    <tbody>
    <?php for ($i = 0, $n = max(count($a['evolucion']), count($b['evolucion'])); $i < $n; $i++):
        $ea = $a['evolucion'][$i] ?? null;
        $eb = $b['evolucion'][$i] ?? null;
    ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= $ea ? $fmtFecha($ea['fecha']) : '' ?></td>
            <td class="num"><?= $ea ? $fmtDia($ea['gasto'], $a['moneda']) : '' ?></td>
            <td class="num"><?= $ea ? $fmtValor($ea['resultados'], 'dec', '') : '' ?></td>
            <td><?= $eb ? $fmtFecha($eb['fecha']) : '' ?></td>
            <td class="num"><?= $eb ? $fmtDia($eb['gasto'], $b['moneda']) : '' ?></td>
            <td class="num"><?= $eb ? $fmtValor($eb['resultados'], 'dec', '') : '' ?></td>
        </tr>
    <?php endfor; ?>
    </tbody>
</table>

<?php if ($comentarios !== null && trim($comentarios) !== ''): ?>
<h2>Comentarios</h2>
<div style="white-space:pre-wrap;font-size:10pt;line-height:1.5"><?= $view->e($comentarios) ?></div>
<?php endif; ?>
