<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var string $token */
/** @var bool $token_valido */
/** @var string|null $usuario_correo */
/** @var list<string> $errores */
?>
<div class="auth-card">
    <header class="auth-card__header">
        <h1 class="auth-card__brand">Mister Co.</h1>
        <p class="auth-card__subtitle">Nueva contraseña</p>
    </header>

    <?php if (!$token_valido): ?>
        <div class="alert alert--error">
            El enlace es inválido o expiró. <a href="/password/solicitar">Solicitá uno nuevo</a>.
        </div>
    <?php else: ?>
        <?php if ($errores !== []): ?>
            <div class="alert alert--error">
                <ul style="margin:0;padding-left:1.25rem">
                    <?php foreach ($errores as $e): ?>
                        <li><?= $view->e((string) $e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/password/reset" class="auth-form">
            <?= $view->csrfField() ?>
            <input type="hidden" name="token" value="<?= $view->e($token) ?>">

            <p class="muted">Restableciendo contraseña para: <strong><?= $view->e((string) $usuario_correo) ?></strong></p>

            <label class="field">
                <span class="field__label">Nueva contraseña</span>
                <input class="field__input" type="password" name="password" required minlength="12" autocomplete="new-password">
            </label>
            <label class="field">
                <span class="field__label">Repetir contraseña</span>
                <input class="field__input" type="password" name="password_confirmacion" required minlength="12" autocomplete="new-password">
            </label>
            <p class="muted" style="font-size:0.85rem">
                Mín. 12 caracteres con mayúscula, minúscula, número y carácter especial.
            </p>
            <button type="submit" class="btn btn--primary btn--block">Actualizar contraseña</button>
        </form>
    <?php endif; ?>

    <footer class="auth-card__footer">
        <a href="/login">← Volver al ingreso</a>
    </footer>
</div>
