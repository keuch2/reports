<?php
/**
 * Formulario de generación del PDF global (cliente y admin): período (mes con
 * datos o rango personalizado), comentarios y marca de agua.
 *
 * @var \MisterCo\Reports\Core\View $view
 * @var string $action URL (ya resuelta) del POST que descarga el PDF.
 * @var string $cancelar_url URL (ya resuelta) para volver al dashboard.
 * @var \MisterCo\Reports\Domain\PeriodoReporte $periodo
 * @var list<string> $meses_disponibles
 */

$mesesNombre = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$formatMes = static function (string $yyyymm) use ($mesesNombre): string {
    $partes = explode('-', $yyyymm);
    if (count($partes) !== 2) return $yyyymm;
    $mes = (int) $partes[1];
    return ucfirst($mesesNombre[$mes - 1] ?? '') . ' ' . $partes[0];
};
?>
<article class="card">
    <form method="POST" action="<?= $view->e($action) ?>" class="form-stack" data-reporte-form>
        <?= $view->csrfField() ?>

        <?php if ($meses_disponibles !== []): ?>
            <label class="field">
                <span class="field__label">Período a exportar</span>
                <select class="field__input" name="mes" data-reporte-mes>
                    <?php foreach ($meses_disponibles as $m): ?>
                        <option value="<?= $view->e($m) ?>" <?= $m === $periodo->mes ? 'selected' : '' ?>>
                            <?= $view->e($formatMes($m)) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="" <?= $periodo->esRango ? 'selected' : '' ?>>Rango personalizado</option>
                </select>
            </label>
        <?php endif; ?>

        <div style="display:flex;gap:1rem;flex-wrap:wrap">
            <label class="field" style="flex:1;min-width:10rem">
                <span class="field__label">Desde</span>
                <input class="field__input" type="date" name="desde" value="<?= $view->e($periodo->desde) ?>" data-reporte-fecha required>
            </label>
            <label class="field" style="flex:1;min-width:10rem">
                <span class="field__label">Hasta</span>
                <input class="field__input" type="date" name="hasta" value="<?= $view->e($periodo->hasta) ?>" data-reporte-fecha required>
            </label>
        </div>
        <p class="muted">Elegí un mes completo o ajustá las fechas para un rango personalizado.</p>

        <label class="field">
            <span class="field__label">Comentarios estratégicos (opcional)</span>
            <textarea class="field__input" name="comentarios" rows="6"
                      placeholder="Agregá contexto, logros del período, recomendaciones..."></textarea>
        </label>
        <p class="muted">Se incluirán en el reporte si la plantilla tiene la sección de comentarios.</p>

        <label style="display:flex;gap:0.5rem;align-items:center;cursor:pointer">
            <input type="checkbox" name="marca_de_agua" value="1">
            <span>Marca de agua "CONFIDENCIAL" en el PDF</span>
        </label>

        <div class="form-actions">
            <a href="<?= $view->e($cancelar_url) ?>" class="btn btn--link">Cancelar</a>
            <button type="submit" class="btn btn--primary">📄 Descargar PDF</button>
        </div>
    </form>
</article>
<script>
(function () {
    // Elegir un mes completa las fechas con ese mes; tocar una fecha pasa a
    // "Rango personalizado". El servidor aplica la misma regla sin JS.
    var form = document.querySelector('[data-reporte-form]');
    var mes = form.querySelector('[data-reporte-mes]');
    var fechas = form.querySelectorAll('[data-reporte-fecha]');
    if (!mes) return;
    mes.addEventListener('change', function () {
        if (mes.value === '') return;
        var p = mes.value.split('-');
        var ultimo = new Date(Number(p[0]), Number(p[1]), 0).getDate();
        fechas[0].value = mes.value + '-01';
        fechas[1].value = mes.value + '-' + String(ultimo).padStart(2, '0');
    });
    fechas.forEach(function (f) {
        f.addEventListener('input', function () { mes.value = ''; });
    });
})();
</script>
