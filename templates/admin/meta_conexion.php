<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var bool $tiene_token */
/** @var bool $oauth_disponible */
/** @var string $oauth_redirect_uri */
/** @var \DateTimeImmutable|null $vence_en */
/** @var int|null $dias_vencimiento */
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

    <?php if ($tiene_token && $dias_vencimiento !== null && $dias_vencimiento <= 10): ?>
        <div class="alert <?= $dias_vencimiento < 0 ? 'alert--error' : 'alert--warning' ?>">
            <?php if ($dias_vencimiento < 0): ?>
                ⚠ El acceso a Meta <strong>venció</strong>. Reconectá la cuenta para poder importar datos.
            <?php else: ?>
                ⚠ El acceso a Meta vence en <strong><?= (int) $dias_vencimiento ?> día<?= $dias_vencimiento === 1 ? '' : 's' ?></strong>
                (el <?= $view->e($vence_en?->format('d/m/Y') ?? '') ?>). Reconectá para renovarlo.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <article class="card">
        <h2>Conexión</h2>

        <?php if ($tiene_token): ?>
            <p>
                ✅ Hay una conexión activa.
                <?php if ($vence_en !== null): ?>
                    <span class="muted">El acceso vence el <?= $view->e($vence_en->format('d/m/Y')) ?>.</span>
                <?php else: ?>
                    <span class="muted">(Token de System User: no vence.)</span>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="muted">Todavía no hay ninguna cuenta Meta conectada.</p>
        <?php endif; ?>

        <?php if ($oauth_disponible): ?>
            <p style="margin-top:1rem">
                <a href="<?= $view->url('/admin/meta/oauth/iniciar') ?>" class="btn btn--primary" style="background:#1877F2;border-color:#1877F2">
                    <?= $tiene_token ? '🔄 Reconectar con Facebook' : 'Conectar con Facebook' ?>
                </a>
            </p>
            <p class="muted" style="font-size:0.85rem">
                Se abrirá Facebook para autorizar el acceso de solo lectura a tus campañas
                (<code>ads_read</code>, <code>business_management</code>). El acceso dura ~60 días
                y podés renovarlo con un click desde acá.
            </p>
        <?php else: ?>
            <div class="alert alert--warning" style="margin-top:1rem">
                Para habilitar el botón "Conectar con Facebook" hay que configurar la App de Meta:
                <ol style="margin:0.5rem 0 0 1.25rem">
                    <li>Creá una app tipo <strong>Empresa</strong> en <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">developers.facebook.com</a>.</li>
                    <li>Agregá el producto <strong>Inicio de sesión de Facebook para empresas</strong> y registrá este redirect URI:<br>
                        <code><?= $view->e($oauth_redirect_uri) ?></code></li>
                    <li>Copiá el App ID y el App Secret a las variables <code>META_APP_ID</code> y <code>META_APP_SECRET</code> del <code>.env</code> del servidor.</li>
                </ol>
                Mientras tanto podés usar el token de System User (abajo).
            </div>
        <?php endif; ?>

        <?php if ($tiene_token): ?>
            <form method="POST" action="<?= $view->url('/admin/meta/desconectar') ?>" style="margin-top:1rem"
                  onsubmit="return confirm('¿Eliminar la conexión? No podrás importar hasta reconectar.');">
                <?= $view->csrfField() ?>
                <button type="submit" class="btn btn--danger">Desconectar</button>
            </form>
        <?php endif; ?>

        <details style="margin-top:1.5rem">
            <summary class="muted" style="cursor:pointer">Avanzado: conectar con token de System User</summary>
            <p class="muted" style="margin-top:0.75rem;font-size:0.85rem">
                Alternativa técnica: generá un token de System User en el Business Manager
                (Configuración del negocio → Usuarios → Usuarios del sistema) con permisos
                <code>ads_read</code> y <code>business_management</code>, y pegalo acá.
                A diferencia de la conexión con Facebook, este token <strong>no vence</strong>.
            </p>
            <form method="POST" action="<?= $view->url('/admin/meta/conectar') ?>" class="form-stack" style="margin-top:0.5rem">
                <?= $view->csrfField() ?>
                <label class="field">
                    <span class="field__label"><?= $tiene_token ? 'Reemplazar token' : 'Nuevo token' ?></span>
                    <textarea class="field__input" name="token" rows="3" placeholder="EAAB..."></textarea>
                </label>
                <button type="submit" class="btn">Validar y guardar</button>
            </form>
        </details>
    </article>

    <article class="card" style="margin-top:1.5rem">
        <h2>Cuentas publicitarias sincronizadas (<?= count($cuentas) ?>)</h2>
        <?php if ($cuentas === []): ?>
            <p class="muted">Aún no se importaron cuentas. Conectá la cuenta arriba.</p>
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
