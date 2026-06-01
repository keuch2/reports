<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var list<string> $errores */
/** @var array<string,string> $form */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'usuarios']) ?>

<section class="shell__body">
    <p><a href="<?= $view->url('/admin/usuarios') ?>">← Volver a usuarios admin</a></p>
    <h1>Nuevo admin</h1>

    <?php if ($errores !== []): ?>
        <div class="alert alert--error">
            <ul style="margin:0;padding-left:1.25rem"><?php foreach ($errores as $e): ?><li><?= $view->e($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <article class="card">
        <form method="POST" action="<?= $view->url('/admin/usuarios') ?>" class="form-stack">
            <?= $view->csrfField() ?>
            <label class="field">
                <span class="field__label">Nombre completo *</span>
                <input class="field__input" type="text" name="nombre_completo" required
                       value="<?= $view->e((string) ($form['nombre_completo'] ?? '')) ?>">
            </label>
            <label class="field">
                <span class="field__label">Correo (login) *</span>
                <input class="field__input" type="email" name="correo" required
                       value="<?= $view->e((string) ($form['correo'] ?? '')) ?>">
            </label>
            <label class="field">
                <span class="field__label">Contraseña inicial *</span>
                <input class="field__input" type="text" name="password" required minlength="12" autocomplete="off">
            </label>
            <p class="muted" style="font-size:0.85rem">
                Mín. 12 caracteres con mayúscula, minúscula, número y carácter especial.
                Compartila por canal seguro — el admin podrá cambiarla desde su perfil.
            </p>
            <div class="form-actions">
                <a href="<?= $view->url('/admin/usuarios') ?>" class="btn btn--link">Cancelar</a>
                <button type="submit" class="btn btn--primary">Crear admin</button>
            </div>
        </form>
    </article>
</section>
