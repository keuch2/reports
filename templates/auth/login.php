<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var string|null $error */
/** @var string $correo */
?>
<div class="auth-card">
    <header class="auth-card__header">
        <h1 class="auth-card__brand">Mister Co.</h1>
        <p class="auth-card__subtitle">Plataforma de Reportes</p>
    </header>

    <?php if ($error !== null && $error !== ''): ?>
        <div class="alert alert--error" role="alert">
            <?= $view->e((string) $error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $view->url('/login') ?>" class="auth-form" novalidate>
        <?= $view->csrfField() ?>

        <label class="field">
            <span class="field__label">Correo</span>
            <input class="field__input" type="email" name="correo" required autofocus
                   value="<?= $view->e((string) $correo) ?>" autocomplete="email">
        </label>

        <label class="field">
            <span class="field__label">Contraseña</span>
            <input class="field__input" type="password" name="password" required
                   autocomplete="current-password">
        </label>

        <button type="submit" class="btn btn--primary btn--block">Ingresar</button>
    </form>

    <footer class="auth-card__footer">
        <a href="<?= $view->url('/password/solicitar') ?>">¿Olvidaste tu contraseña?</a>
        <br><br>
        <small>© <?= date('Y') ?> Mister Co. · mister.com.py</small>
    </footer>
</div>
