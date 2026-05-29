<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var string|null $error */
?>
<div class="auth-card">
    <header class="auth-card__header">
        <h1 class="auth-card__brand">Mister Co.</h1>
        <p class="auth-card__subtitle">Verificación en 2 pasos</p>
    </header>

    <?php if ($error): ?>
        <div class="alert alert--error"><?= $view->e((string) $error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/2fa" class="auth-form">
        <?= $view->csrfField() ?>
        <p class="muted">Ingresá el código de 6 dígitos de tu app de autenticación (o un código de backup).</p>
        <label class="field">
            <span class="field__label">Código</span>
            <input class="field__input" type="text" name="codigo" required autofocus
                   inputmode="text" autocomplete="one-time-code" maxlength="16">
        </label>
        <button type="submit" class="btn btn--primary btn--block">Verificar</button>
    </form>

    <footer class="auth-card__footer">
        <a href="/login">← Cancelar</a>
    </footer>
</div>
