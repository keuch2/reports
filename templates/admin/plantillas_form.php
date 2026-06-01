<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed>|null $plantilla */
/** @var list<string> $secciones_actuales */
/** @var array<string,string> $secciones_disponibles */
/** @var list<array<string,mixed>> $clientes */

$esEdicion = $plantilla !== null;
$action = $esEdicion ? '/admin/plantillas/' . (int) $plantilla['id'] : '/admin/plantillas';
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'plantillas']) ?>

<section class="shell__body">
    <p><a href="<?= $view->url('/admin/plantillas') ?>">← Volver a plantillas</a></p>
    <h1><?= $esEdicion ? 'Editar plantilla' : 'Nueva plantilla' ?></h1>

    <article class="card">
        <form method="POST" action="<?= $view->e($action) ?>" class="form-stack">
            <?= $view->csrfField() ?>

            <label class="field">
                <span class="field__label">Nombre *</span>
                <input class="field__input" type="text" name="nombre" required
                       value="<?= $view->e((string) ($plantilla['nombre'] ?? '')) ?>">
            </label>

            <label class="field">
                <span class="field__label">Descripción</span>
                <input class="field__input" type="text" name="descripcion"
                       value="<?= $view->e((string) ($plantilla['descripcion'] ?? '')) ?>">
            </label>

            <label class="field">
                <span class="field__label">Aplica a</span>
                <select class="field__input" name="cliente_id">
                    <option value="0">Todos los clientes (genérica)</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($plantilla['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= $view->e((string) $c['nombre_comercial']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <fieldset class="fieldset">
                <legend>Secciones del reporte</legend>
                <p class="muted">Marcá las secciones a incluir.</p>
                <?php foreach ($secciones_disponibles as $codigo => $label): ?>
                    <label style="display:flex;gap:0.5rem;align-items:center;cursor:pointer">
                        <input type="checkbox" name="secciones[]" value="<?= $view->e($codigo) ?>"
                               <?= in_array($codigo, $secciones_actuales, true) ? 'checked' : '' ?>>
                        <?= $view->e($label) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="form-actions">
                <a href="<?= $view->url('/admin/plantillas') ?>" class="btn btn--link">Cancelar</a>
                <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear plantilla' ?></button>
            </div>
        </form>
    </article>
</section>
