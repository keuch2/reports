<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var bool $habilitado */
/** @var array{secreto:string,otpauth_uri:string}|null $enrolamiento */
/** @var list<string>|null $backup_codes */
/** @var string|null $success */
/** @var string|null $error */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => '2fa']) ?>

<section class="shell__body">
    <h1>Autenticación en 2 pasos (2FA)</h1>

    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert--error"><?= $view->e((string) $error) ?></div><?php endif; ?>

    <article class="card">
        <h2>Estado actual</h2>
        <p>2FA está actualmente:
            <strong style="color:<?= $habilitado ? '#065f46' : '#991b1b' ?>">
                <?= $habilitado ? 'HABILITADO' : 'DESHABILITADO' ?>
            </strong>
        </p>

        <?php if (!$habilitado && $enrolamiento === null): ?>
            <p class="muted">Recomendado para usuarios admin. Compatible con Google Authenticator, Authy, 1Password.</p>
            <form method="POST" action="/admin/2fa/iniciar">
                <?= $view->csrfField() ?>
                <button type="submit" class="btn btn--primary">Habilitar 2FA</button>
            </form>
        <?php endif; ?>

        <?php if (!$habilitado && $enrolamiento !== null): ?>
            <h3>1. Escaneá este QR con tu app de autenticación</h3>
            <p>O ingresá el secreto manualmente: <code><?= $view->e($enrolamiento['secreto']) ?></code></p>
            <p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=<?= urlencode($enrolamiento['otpauth_uri']) ?>"
                     alt="QR 2FA" style="border:1px solid var(--color-border);padding:0.5rem;background:white">
            </p>

            <h3>2. Ingresá el código de 6 dígitos que muestra la app</h3>
            <form method="POST" action="/admin/2fa/confirmar" class="form-stack" style="max-width:320px">
                <?= $view->csrfField() ?>
                <label class="field">
                    <span class="field__label">Código</span>
                    <input class="field__input" type="text" name="codigo" required
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus>
                </label>
                <button type="submit" class="btn btn--primary">Confirmar y habilitar</button>
            </form>
        <?php endif; ?>

        <?php if ($backup_codes !== null): ?>
            <h3 style="margin-top:1.5rem">Códigos de backup</h3>
            <div class="alert alert--warning">
                <strong>Guardalos ahora. No se mostrarán de nuevo.</strong>
                Sirven para entrar si perdés acceso a la app.
            </div>
            <ul style="font-family:monospace;font-size:1.05rem;columns:2;max-width:380px">
                <?php foreach ($backup_codes as $c): ?>
                    <li><?= $view->e($c) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($habilitado): ?>
            <form method="POST" action="/admin/2fa/deshabilitar" style="margin-top:1rem">
                <?= $view->csrfField() ?>
                <button type="submit" class="btn btn--danger"
                        onclick="return confirm('¿Seguro? Tu cuenta quedará sólo protegida por contraseña.');">
                    Deshabilitar 2FA
                </button>
            </form>
        <?php endif; ?>
    </article>
</section>
