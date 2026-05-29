<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var int $cuenta_id */
/** @var string $preset */
?>
<?= $view->renderPartial('partials/cliente_header', ['usuario' => $usuario]) ?>

<section class="shell__body">
    <p><a href="/cliente">← Volver al dashboard</a></p>
    <h1>Generar reporte PDF</h1>

    <article class="card">
        <form method="POST" action="/cliente/reporte.pdf" class="form-stack">
            <?= $view->csrfField() ?>
            <input type="hidden" name="cuenta_id" value="<?= (int) $cuenta_id ?>">
            <input type="hidden" name="preset" value="<?= $view->e($preset) ?>">

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
                <a href="/cliente" class="btn btn--link">Cancelar</a>
                <button type="submit" class="btn btn--primary">📄 Descargar PDF</button>
            </div>
        </form>
    </article>
</section>
