<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var bool $es_admin */
/** @var string $base */
/** @var array<string, array{clave:string, nombre:string, plataforma:string, cuenta:string, moneda:string}> $disponibles */
/** @var array{clave:string, desde:string, hasta:string} $spec_a */
/** @var array{clave:string, desde:string, hasta:string} $spec_b */
/** @var array{a: array<string,mixed>, b: array<string,mixed>, filas: list<array<string,mixed>>, misma_moneda: bool}|null $comparacion */
/** @var string|null $error */

$porPlataforma = [];
foreach ($disponibles as $op) {
    $porPlataforma[$op['plataforma']][] = $op;
}

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
$urlDashboard = $view->url($es_admin ? '/admin/clientes/' . ((int) $cliente['id']) . '/dashboard' : '/cliente');
?>
<?= $view->renderPartial('partials/encabezado_cliente', [
    'usuario' => $usuario, 'cliente' => $cliente, 'es_admin' => $es_admin,
    'seccion' => 'comparar', 'descripcion' => 'Comparativo de campañas',
    'volver' => ['url' => $urlDashboard, 'texto' => 'Volver al dashboard'],
]) ?>

<section class="shell__body">
    <p><a href="<?= $view->e($urlDashboard) ?>">← Volver al dashboard</a></p>
    <h1>Comparar campañas</h1>
    <p class="muted">Elegí una campaña y un rango de fechas para cada lado. Podés comparar la misma campaña en dos períodos o dos campañas distintas.</p>

    <?php if ($disponibles === []): ?>
        <div class="empty-state"><p><?= $es_admin ? 'Este cliente no tiene campañas asignadas.' : 'Todavía no tenés campañas asignadas.' ?></p></div>
    <?php else: ?>

    <?php if ($error): ?><div class="alert alert--error"><?= $view->e($error) ?></div><?php endif; ?>

    <article class="card">
        <form method="GET" action="<?= $view->url($base) ?>" class="form-stack" data-comparativo>
            <div class="comparativo-grid">
                <?php foreach (['a' => $spec_a, 'b' => $spec_b] as $lado => $spec): ?>
                    <fieldset class="fieldset">
                        <legend><?= $lado === 'a' ? 'A · Referencia' : 'B · Comparar con' ?></legend>
                        <label class="field">
                            <span class="field__label">Campaña</span>
                            <select class="field__input" name="<?= $lado ?>" data-campania="<?= $lado ?>" required>
                                <?php foreach ($porPlataforma as $plataforma => $opciones): ?>
                                    <optgroup label="<?= $view->e($plataforma) ?>">
                                        <?php foreach ($opciones as $op): ?>
                                            <option value="<?= $view->e($op['clave']) ?>" <?= $op['clave'] === $spec['clave'] ? 'selected' : '' ?>>
                                                <?= $view->e($op['nombre']) ?> — <?= $view->e($op['cuenta']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="field-row">
                            <label class="field">
                                <span class="field__label">Desde</span>
                                <input class="field__input" type="date" name="<?= $lado ?>_desde" value="<?= $view->e($spec['desde']) ?>" data-desde="<?= $lado ?>" required>
                            </label>
                            <label class="field">
                                <span class="field__label">Hasta</span>
                                <input class="field__input" type="date" name="<?= $lado ?>_hasta" value="<?= $view->e($spec['hasta']) ?>" data-hasta="<?= $lado ?>" required>
                            </label>
                        </div>
                        <?php if ($lado === 'b'): ?>
                            <p style="margin:0;display:flex;gap:0.5rem;flex-wrap:wrap">
                                <button type="button" class="btn btn--link" data-atajo="anterior">Misma campaña, período anterior</button>
                                <button type="button" class="btn btn--link" data-atajo="mismo-rango">Mismas fechas que A</button>
                            </p>
                        <?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <p><button type="submit" class="btn btn--primary">Comparar</button></p>
        </form>
    </article>

    <?php if ($comparacion !== null):
        $a = $comparacion['a'];
        $b = $comparacion['b'];
    ?>
        <?php if (!$comparacion['misma_moneda']): ?>
            <div class="alert alert--warning" style="margin-top:1.5rem">
                Las campañas usan monedas distintas (<?= $view->e($a['moneda']) ?> y <?= $view->e($b['moneda']) ?>):
                los montos no son directamente comparables.
            </div>
        <?php endif; ?>

        <div class="comparativo-grid" style="margin-top:1.5rem">
            <?php foreach (['A' => $a, 'B' => $b] as $letra => $l): ?>
                <article class="card">
                    <span class="kpi__label"><?= $letra ?> · <?= $view->e($l['plataforma']) ?></span>
                    <h2 style="margin:0.25rem 0"><?= $view->e($l['nombre']) ?></h2>
                    <p class="muted" style="margin:0">
                        <?= $view->e($l['cuenta']) ?> · <?= $fmtFecha($l['desde']) ?> al <?= $fmtFecha($l['hasta']) ?>
                        (<?= (int) $l['dias'] ?> día<?= $l['dias'] === 1 ? '' : 's' ?>)
                    </p>
                    <p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem">Resultado: <?= $view->e($l['label_resultado']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>

        <article class="card" style="margin-top:1.5rem">
            <h2>Resultados comparados</h2>
            <table class="table">
                <thead>
                    <tr><th>Métrica</th><th class="num">A</th><th class="num">B</th><th class="num">Variación B vs A</th></tr>
                </thead>
                <tbody>
                <?php foreach ($comparacion['filas'] as $f):
                    $claseVar = $f['mejor'] === 'b' ? 'variacion--mejor' : ($f['mejor'] === 'a' ? 'variacion--peor' : '');
                ?>
                    <tr>
                        <td><?= $view->e($f['etiqueta']) ?></td>
                        <td class="num"><?= $view->e($fmtValor($f['a'], $f['formato'], $a['moneda'])) ?></td>
                        <td class="num"><?= $view->e($fmtValor($f['b'], $f['formato'], $b['moneda'])) ?></td>
                        <td class="num <?= $claseVar ?>"><?= $view->e($fmtVariacion($f['variacion'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="muted" style="font-size:0.8rem;margin-top:0.5rem">
                Verde: B mejora respecto de A · Rojo: B empeora. En costos (CPC, CPM, costo por resultado) bajar es mejorar.
                <?php if ($a['label_resultado'] !== $b['label_resultado']): ?>
                    Los resultados miden cosas distintas (<?= $view->e($a['label_resultado']) ?> vs <?= $view->e($b['label_resultado']) ?>).
                <?php endif; ?>
            </p>
        </article>

        <div class="comparativo-grid" style="margin-top:1.5rem">
            <article class="card"><h2>Inversión por día</h2><canvas id="grafico-comp-gasto" height="160"></canvas></article>
            <article class="card"><h2>Resultados por día</h2><canvas id="grafico-comp-resultados" height="160"></canvas></article>
        </div>

        <article class="card" style="margin-top:1.5rem">
            <h2>Exportar PDF</h2>
            <form method="POST" action="<?= $view->url($base . '/reporte.pdf') ?>" class="form-stack">
                <?= $view->csrfField() ?>
                <?php foreach (['a' => $a, 'b' => $b] as $lado => $l): ?>
                    <input type="hidden" name="<?= $lado ?>" value="<?= $view->e($l['clave']) ?>">
                    <input type="hidden" name="<?= $lado ?>_desde" value="<?= $view->e($l['desde']) ?>">
                    <input type="hidden" name="<?= $lado ?>_hasta" value="<?= $view->e($l['hasta']) ?>">
                <?php endforeach; ?>
                <label class="field">
                    <span class="field__label">Comentarios (opcional)</span>
                    <textarea class="field__input" name="comentarios" rows="4" placeholder="Conclusiones de la comparación, recomendaciones..."></textarea>
                </label>
                <label style="display:flex;gap:0.5rem;align-items:center;cursor:pointer">
                    <input type="checkbox" name="marca_de_agua" value="1">
                    <span>Marca de agua "CONFIDENCIAL" en el PDF</span>
                </label>
                <p><button type="submit" class="btn btn--primary">📄 Descargar PDF</button></p>
            </form>
        </article>
    <?php endif; ?>
    <?php endif; ?>
</section>

<script>
(function () {
    // Atajos del lado B. Fechas en UTC para no correr un día por zona horaria.
    var form = document.querySelector('[data-comparativo]');
    if (!form) return;
    var campo = function (attr, lado) { return form.querySelector('[data-' + attr + '="' + lado + '"]'); };
    var aFecha = function (s) { return new Date(s + 'T00:00:00Z'); };
    var iso = function (d) { return d.toISOString().slice(0, 10); };
    form.querySelectorAll('[data-atajo]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var desde = campo('desde', 'a').value, hasta = campo('hasta', 'a').value;
            if (!desde || !hasta) return;
            if (btn.dataset.atajo === 'anterior') {
                var dias = Math.round((aFecha(hasta) - aFecha(desde)) / 86400000) + 1;
                var finB = new Date(aFecha(desde).getTime() - 86400000);
                var inicioB = new Date(finB.getTime() - (dias - 1) * 86400000);
                campo('campania', 'b').value = campo('campania', 'a').value;
                campo('desde', 'b').value = iso(inicioB);
                campo('hasta', 'b').value = iso(finB);
            } else {
                campo('desde', 'b').value = desde;
                campo('hasta', 'b').value = hasta;
            }
        });
    });
})();
</script>

<?php if ($comparacion !== null): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    // Los dos períodos se superponen por número de día (día 1, día 2...).
    var a = <?= json_encode($comparacion['a']['evolucion'], JSON_NUMERIC_CHECK) ?>;
    var b = <?= json_encode($comparacion['b']['evolucion'], JSON_NUMERIC_CHECK) ?>;
    var n = Math.max(a.length, b.length);
    var labels = Array.from({ length: n }, function (_, i) { return 'Día ' + (i + 1); });
    var fechas = function (serie) { return serie.map(function (e) { return e.fecha; }); };
    var grafico = function (id, campo) {
        new Chart(document.getElementById(id), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'A', data: a.map(function (e) { return e[campo]; }), borderColor: '#1a2f6e', backgroundColor: 'rgba(26,47,110,0.08)', tension: 0.3, fill: true, fechas: fechas(a) },
                    { label: 'B', data: b.map(function (e) { return e[campo]; }), borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.3, fill: true, fechas: fechas(b) }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true } },
                plugins: { tooltip: { callbacks: {
                    label: function (ctx) {
                        var fecha = ctx.dataset.fechas[ctx.dataIndex] || '';
                        return ctx.dataset.label + ' (' + fecha + '): ' + ctx.formattedValue;
                    }
                } } }
            }
        });
    };
    grafico('grafico-comp-gasto', 'gasto');
    grafico('grafico-comp-resultados', 'resultados');
})();
</script>
<?php endif; ?>
