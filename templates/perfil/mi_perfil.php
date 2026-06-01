<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $datos */
/** @var list<string> $errores_perfil */
/** @var list<string> $errores_pass */
/** @var string|null $success */

$headerPartial = $usuario->esAdmin() ? 'partials/admin_header' : 'partials/cliente_header';
?>
<?= $view->renderPartial($headerPartial, ['usuario' => $usuario, 'seccion' => 'perfil']) ?>

<section class="shell__body">
    <h1>Mi perfil</h1>
    <p class="muted">Cuenta: <strong><?= $view->e((string) $datos['correo']) ?></strong> · creada <?= $view->e((string) $datos['creado_en']) ?></p>

    <?php if ($success): ?>
        <div class="alert alert--success"><?= $view->e((string) $success) ?></div>
    <?php endif; ?>

    <article class="card">
        <h2>Datos personales</h2>
        <?php if ($errores_perfil !== []): ?>
            <div class="alert alert--error">
                <ul style="margin:0;padding-left:1.25rem"><?php foreach ($errores_perfil as $e): ?><li><?= $view->e($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $view->url('/mi-perfil') ?>" class="form-stack">
            <?= $view->csrfField() ?>
            <label class="field">
                <span class="field__label">Nombre completo</span>
                <input class="field__input" type="text" name="nombre_completo" required
                       value="<?= $view->e((string) $datos['nombre_completo']) ?>">
            </label>
            <label class="field">
                <span class="field__label">Correo (también es tu login)</span>
                <input class="field__input" type="email" name="correo" required
                       value="<?= $view->e((string) $datos['correo']) ?>">
            </label>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Guardar cambios</button>
            </div>
        </form>
    </article>

    <article class="card" style="margin-top:1.5rem">
        <h2>Cambiar contraseña</h2>
        <?php if ($errores_pass !== []): ?>
            <div class="alert alert--error">
                <ul style="margin:0;padding-left:1.25rem"><?php foreach ($errores_pass as $e): ?><li><?= $view->e($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $view->url('/mi-perfil/password') ?>" class="form-stack">
            <?= $view->csrfField() ?>
            <label class="field">
                <span class="field__label">Contraseña actual</span>
                <input class="field__input" type="password" name="password_actual" required autocomplete="current-password">
            </label>
            <div class="field-row">
                <label class="field">
                    <span class="field__label">Nueva contraseña</span>
                    <input class="field__input" type="password" name="password_nueva" required minlength="12" autocomplete="new-password">
                </label>
                <label class="field">
                    <span class="field__label">Repetir nueva</span>
                    <input class="field__input" type="password" name="password_confirmacion" required minlength="12" autocomplete="new-password">
                </label>
            </div>
            <p class="muted" style="font-size:0.85rem">
                Mín. 12 caracteres con mayúscula, minúscula, número y carácter especial.
            </p>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Actualizar contraseña</button>
            </div>
        </form>
    </article>

    <?php if ($usuario->esAdmin()): ?>
    <article class="card" style="margin-top:1.5rem">
        <h2>Seguridad adicional</h2>
        <p class="muted">Recomendamos activar autenticación en 2 pasos.</p>
        <p>
            <a href="<?= $view->url('/admin/2fa') ?>" class="btn btn--link">Configurar 2FA →</a>
        </p>
    </article>
    <?php endif; ?>
</section>
