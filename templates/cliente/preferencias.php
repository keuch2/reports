<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array{widgets:list<string>, rango_default:string} $preferencias */
/** @var array<string, string> $widgets_disponibles */
/** @var array<string, string> $presets */
/** @var list<string> $metricas_deshabilitadas_por_admin */
/** @var string|null $success */

$widgetsAOrdenar = array_unique(array_merge($preferencias['widgets'], array_keys($widgets_disponibles)));
?>
<?= $view->renderPartial('partials/cliente_header', ['usuario' => $usuario]) ?>

<section class="shell__body">
    <p><a href="<?= $view->url('/cliente') ?>">← Volver al dashboard</a></p>
    <h1>Mis preferencias</h1>

    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>

    <article class="card">
        <h2>Widgets del dashboard</h2>
        <p class="muted">Arrastrá para reordenar. Desmarcá los que no quieras ver.</p>
        <form method="POST" action="<?= $view->url('/cliente/preferencias') ?>" id="prefs-form">
            <?= $view->csrfField() ?>

            <ul class="widgets-orden" id="widgets-orden">
                <?php foreach ($widgetsAOrdenar as $codigo): ?>
                    <li class="widget-item" data-codigo="<?= $view->e($codigo) ?>">
                        <span class="drag-handle">⠿</span>
                        <label>
                            <input type="checkbox" class="widget-check"
                                   <?= in_array($codigo, $preferencias['widgets'], true) ? 'checked' : '' ?>>
                            <?= $view->e($widgets_disponibles[$codigo] ?? $codigo) ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <input type="hidden" name="widgets_orden" id="widgets-orden-input" value="<?= $view->e(implode(',', $preferencias['widgets'])) ?>">

            <fieldset class="fieldset" style="margin-top:1.5rem">
                <legend>Rango por defecto</legend>
                <label class="field">
                    <select class="field__input" name="rango_default">
                        <?php foreach ($presets as $k => $label): ?>
                            <option value="<?= $view->e($k) ?>" <?= $preferencias['rango_default'] === $k ? 'selected' : '' ?>>
                                <?= $view->e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </fieldset>

            <?php if ($metricas_deshabilitadas_por_admin !== []): ?>
                <div class="alert alert--warning" style="margin-top:1rem">
                    <strong>Nota:</strong> tu administrador deshabilitó las siguientes métricas:
                    <em><?= $view->e(implode(', ', $metricas_deshabilitadas_por_admin)) ?></em>.
                    Aunque las habilites acá, no aparecerán hasta que se vuelvan a habilitar.
                </div>
            <?php endif; ?>

            <div class="form-actions" style="margin-top:1.5rem">
                <button type="submit" class="btn btn--primary">Guardar preferencias</button>
            </div>
        </form>
    </article>
</section>

<script>
    const lista = document.getElementById('widgets-orden');
    const hidden = document.getElementById('widgets-orden-input');

    function sincronizarOrden() {
        const codigos = [...lista.querySelectorAll('.widget-item')]
            .filter(li => li.querySelector('.widget-check').checked)
            .map(li => li.dataset.codigo);
        hidden.value = codigos.join(',');
    }

    lista.querySelectorAll('.widget-check').forEach(cb => cb.addEventListener('change', sincronizarOrden));

    // Drag-and-drop minimalista basado en HTML5 Drag and Drop.
    let dragged = null;
    lista.querySelectorAll('.widget-item').forEach(item => {
        item.draggable = true;
        item.addEventListener('dragstart', () => { dragged = item; item.classList.add('dragging'); });
        item.addEventListener('dragend', () => { item.classList.remove('dragging'); sincronizarOrden(); });
        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            const rect = item.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            if (dragged && dragged !== item) {
                lista.insertBefore(dragged, after ? item.nextSibling : item);
            }
        });
    });

    sincronizarOrden();
</script>
