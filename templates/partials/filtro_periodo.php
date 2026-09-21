<?php
/**
 * Filtro de período compartido por el dashboard y el detalle de campaña
 * (cliente y preview admin): mes con datos o rango de fechas personalizado,
 * más el botón de exportar PDF del mismo período.
 *
 * @var \MisterCo\Reports\Core\View $view
 * @var string $action URL (ya resuelta con $view->url) a la que filtra el form GET.
 * @var \MisterCo\Reports\Domain\PeriodoReporte $periodo
 * @var list<string> $meses_disponibles
 * @var array{metodo:string,url:string}|null $exportar 'get' = link a la vista previa; 'post' = descarga directa.
 */

$mesesNombre = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$formatMes = static function (string $yyyymm) use ($mesesNombre): string {
    $partes = explode('-', $yyyymm);
    if (count($partes) !== 2) return $yyyymm;
    $mes = (int) $partes[1];
    return ucfirst($mesesNombre[$mes - 1] ?? '') . ' ' . $partes[0];
};
$exportar = $exportar ?? null;
?>
<div class="dashboard-filters">
    <form method="GET" action="<?= $view->e($action) ?>" class="dashboard-filters__form" data-filtro-periodo>
        <label class="field">
            <span class="field__label">Mes</span>
            <select class="field__input" name="mes" data-filtro-mes>
                <?php foreach ($meses_disponibles as $m): ?>
                    <option value="<?= $view->e($m) ?>" <?= $m === $periodo->mes ? 'selected' : '' ?>>
                        <?= $view->e($formatMes($m)) ?>
                    </option>
                <?php endforeach; ?>
                <option value="" <?= $periodo->esRango ? 'selected' : '' ?>>Rango personalizado</option>
            </select>
        </label>
        <label class="field">
            <span class="field__label">Desde</span>
            <input class="field__input" type="date" name="desde" value="<?= $view->e($periodo->desde) ?>" data-filtro-fecha required>
        </label>
        <label class="field">
            <span class="field__label">Hasta</span>
            <input class="field__input" type="date" name="hasta" value="<?= $view->e($periodo->hasta) ?>" data-filtro-fecha required>
        </label>
        <button type="submit" class="btn btn--secondary">Aplicar</button>
    </form>

    <?php if ($exportar !== null && $exportar['metodo'] === 'post'): ?>
        <form method="POST" action="<?= $view->e($exportar['url']) ?>" class="dashboard-filters__exportar">
            <?= $view->csrfField() ?>
            <?php if ($periodo->esRango): ?>
                <input type="hidden" name="desde" value="<?= $view->e($periodo->desde) ?>">
                <input type="hidden" name="hasta" value="<?= $view->e($periodo->hasta) ?>">
            <?php else: ?>
                <input type="hidden" name="mes" value="<?= $view->e((string) $periodo->mes) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn--primary">📄 Exportar PDF</button>
        </form>
    <?php elseif ($exportar !== null): ?>
        <a href="<?= $view->e($exportar['url'] . '?' . $periodo->query()) ?>"
           class="btn btn--primary dashboard-filters__exportar">📄 Exportar PDF</a>
    <?php endif; ?>
</div>
<script>
(function () {
    // Elegir un mes filtra al instante; tocar una fecha pasa a "Rango personalizado"
    // y se aplica con el botón. Sin JS el form funciona igual con "Aplicar".
    document.querySelectorAll('[data-filtro-periodo]').forEach(function (form) {
        var mes = form.querySelector('[data-filtro-mes]');
        var fechas = form.querySelectorAll('[data-filtro-fecha]');
        mes.addEventListener('change', function () {
            if (mes.value === '') return;
            fechas.forEach(function (f) { f.disabled = true; });
            form.submit();
        });
        fechas.forEach(function (f) {
            f.addEventListener('input', function () { mes.value = ''; });
        });
    });
})();
</script>
