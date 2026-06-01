<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var bool $tiene_token */
/** @var list<array<string,mixed>> $cuentas */
/** @var string|null $error */
/** @var string|null $success */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'meta']) ?>

<section class="shell__body">
    <h1>Conectar cuenta Meta</h1>

    <?php if ($error): ?>
        <div class="alert alert--error"><?= $view->e((string) $error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert--success"><?= $view->e((string) $success) ?></div>
    <?php endif; ?>

    <article class="card">
        <h2>Token de System User</h2>
        <?php if ($tiene_token): ?>
            <p>Hay un token configurado. <span class="muted">(El valor no se muestra por seguridad.)</span></p>
            <form method="POST" action="<?= $view->url('/admin/meta/desconectar') ?>" onsubmit="return confirm('¿Eliminar el token? No podrás importar hasta reconectarlo.');">
                <?= $view->csrfField() ?>
                <button type="submit" class="btn btn--danger">Desconectar</button>
            </form>
        <?php else: ?>
            <p class="muted">Pegá el token de System User del Business Manager. Se validará contra <code>me/adaccounts</code> antes de guardar.</p>
        <?php endif; ?>

        <form method="POST" action="<?= $view->url('/admin/meta/conectar') ?>" class="form-stack" style="margin-top:1rem">
            <?= $view->csrfField() ?>
            <label class="field">
                <span class="field__label"><?= $tiene_token ? 'Reemplazar token' : 'Nuevo token' ?></span>
                <textarea class="field__input" name="token" rows="3" required
                          placeholder="EAAB..."></textarea>
            </label>
            <button type="submit" class="btn btn--primary">Validar y guardar</button>
        </form>
    </article>

    <article class="card" style="margin-top:1.5rem">
        <h2>Cuentas publicitarias sincronizadas (<?= count($cuentas) ?>)</h2>
        <?php if ($cuentas === []): ?>
            <p class="muted">Aún no se importaron cuentas. Conectá el token arriba.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Meta Account ID</th>
                        <th>Moneda</th>
                        <th>Estado</th>
                        <th>Última importación</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cuentas as $c): ?>
                    <tr>
                        <td><?= $view->e((string) $c['nombre']) ?></td>
                        <td><code><?= $view->e((string) $c['meta_account_id']) ?></code></td>
                        <td><?= $view->e((string) ($c['moneda'] ?? '—')) ?></td>
                        <td><?= $view->e((string) ($c['estado'] ?? '—')) ?></td>
                        <td><?= $view->e((string) ($c['ultima_sincronizacion_en'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>
