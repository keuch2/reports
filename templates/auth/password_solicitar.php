<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var string|null $success */
/** @var string|null $error */
?>
<div class="auth-card">
    <header class="auth-card__header">
        <h1 class="auth-card__brand">Mister Co.</h1>
        <p class="auth-card__subtitle">Recuperar contraseña</p>
    </header>

    <?php if ($success): ?>
        <div class="alert alert--success"><?= $view->e((string) $success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert--error"><?= $view->e((string) $error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/password/solicitar" class="auth-form">
        <?= $view->csrfField() ?>
        <label class="field">
            <span class="field__label">Correo de tu cuenta</span>
            <input class="field__input" type="email" name="correo" required autofocus autocomplete="email">
        </label>
        <button type="submit" class="btn btn--primary btn--block">Enviar enlace</button>
    </form>

    <footer class="auth-card__footer">
        <a href="/login">← Volver al ingreso</a>
    </footer>
</div>
