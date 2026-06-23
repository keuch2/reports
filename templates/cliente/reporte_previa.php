<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var list<string> $meses_disponibles */
/** @var string|null $mes_seleccionado */
/** @var string $desde */
/** @var string $hasta */

$mesesNombre = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$formatMes = static function (string $yyyymm) use ($mesesNombre): string {
    $partes = explode('-', $yyyymm);
    if (count($partes) !== 2) return $yyyymm;
    $mes = (int) $partes[1];
    return ucfirst($mesesNombre[$mes - 1] ?? '') . ' ' . $partes[0];
};
?>
<?= $view->renderPartial('partials/cliente_header', ['usuario' => $usuario]) ?>

<section class="shell__body">
    <p><a href="<?= $view->url('/cliente?mes=' . $view->e((string) $mes_seleccionado)) ?>">← Volver al dashboard</a></p>
    <h1>Generar reporte PDF</h1>
    <p class="muted">El PDF refleja exactamente los datos del dashboard para el período elegido.</p>

    <article class="card">
        <form method="POST" action="<?= $view->url('/cliente/reporte.pdf') ?>" class="form-stack">
            <?= $view->csrfField() ?>

            <?php if ($meses_disponibles === []): ?>
                <input type="hidden" name="desde" value="<?= $view->e($desde) ?>">
                <input type="hidden" name="hasta" value="<?= $view->e($hasta) ?>">
                <p class="muted">Período: <?= $view->e($desde) ?> → <?= $view->e($hasta) ?></p>
            <?php else: ?>
                <label class="field">
                    <span class="field__label">Mes a exportar</span>
                    <select class="field__input" name="mes">
                        <?php foreach ($meses_disponibles as $m): ?>
                            <option value="<?= $view->e($m) ?>" <?= $m === $mes_seleccionado ? 'selected' : '' ?>>
                                <?= $view->e($formatMes($m)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p class="muted">Período actual: <?= $view->e($desde) ?> → <?= $view->e($hasta) ?></p>
            <?php endif; ?>

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
                <a href="<?= $view->url('/cliente?mes=' . $view->e((string) $mes_seleccionado)) ?>" class="btn btn--link">Cancelar</a>
                <button type="submit" class="btn btn--primary">📄 Descargar PDF</button>
            </div>
        </form>
    </article>
</section>
