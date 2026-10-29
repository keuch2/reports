<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var bool $tiene_token */
/** @var string|null $email_conectado */
/** @var bool $ads_disponible */
/** @var bool $oauth_disponible */
/** @var string $oauth_redirect_uri */
/** @var list<array<string,mixed>> $cuentas_ads */
/** @var list<array<string,mixed>> $propiedades_ga4 */
/** @var string|null $error */
/** @var string|null $success */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'google']) ?>

<section class="shell__body">
    <h1>Google Ads y Google Analytics</h1>

    <?php if ($error): ?><div class="alert alert--error"><?= $view->e((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>

    <article class="card">
        <h2>Conexión</h2>

        <?php if ($tiene_token): ?>
            <p>✅ Conectado<?= $email_conectado !== null ? ' como <strong>' . $view->e($email_conectado) . '</strong>' : '' ?>.</p>
        <?php else: ?>
            <p class="muted">Todavía no hay ninguna cuenta de Google conectada.</p>
        <?php endif; ?>

        <?php if (!$ads_disponible): ?>
            <div class="alert alert--warning" style="margin-top:1rem">
                Google Ads requiere un <strong>developer token</strong>: pedilo en la cuenta administradora (MCC) →
                Herramientas → Centro de API, y copialo a <code>GOOGLE_ADS_DEVELOPER_TOKEN</code> en el <code>.env</code>.
                Mientras tanto solo se sincroniza Google Analytics.
            </div>
        <?php endif; ?>

        <?php if ($oauth_disponible): ?>
            <p style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="<?= $view->url('/admin/google/oauth/iniciar') ?>" class="btn btn--primary">
                    <?= $tiene_token ? '🔄 Reconectar con Google' : 'Conectar con Google' ?>
                </a>
            </p>
            <p class="muted" style="font-size:0.85rem">
                Se abrirá Google para autorizar acceso de <strong>solo lectura</strong> a Google Ads y Google Analytics.
                Usá una cuenta de Google con acceso a la cuenta administradora (MCC) de Google Ads y a las propiedades de Analytics.
            </p>
        <?php else: ?>
            <div class="alert alert--warning" style="margin-top:1rem">
                Para habilitar el botón "Conectar con Google" hay que configurar un cliente OAuth:
                <ol style="margin:0.5rem 0 0 1.25rem">
                    <li>En <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a> habilitá <strong>Google Ads API</strong>, <strong>Google Analytics Admin API</strong> y <strong>Google Analytics Data API</strong>.</li>
                    <li>Configurá la pantalla de consentimiento OAuth (publicada: en modo "Prueba" el acceso vence a los 7 días).</li>
                    <li>Creá credenciales → ID de cliente OAuth → <strong>Aplicación web</strong> con este redirect URI:<br>
                        <code><?= $view->e($oauth_redirect_uri) ?></code></li>
                    <li>Copiá el Client ID y el Client Secret a <code>GOOGLE_CLIENT_ID</code> y <code>GOOGLE_CLIENT_SECRET</code> del <code>.env</code>.</li>
                </ol>
            </div>
        <?php endif; ?>

        <?php if ($tiene_token): ?>
            <div style="display:flex;gap:0.5rem;margin-top:1rem">
                <form method="POST" action="<?= $view->url('/admin/google/sincronizar') ?>">
                    <?= $view->csrfField() ?>
                    <button type="submit" class="btn btn--secondary">Volver a sincronizar cuentas</button>
                </form>
                <form method="POST" action="<?= $view->url('/admin/google/desconectar') ?>"
                      onsubmit="return confirm('¿Eliminar la conexión con Google? No podrás importar hasta reconectar.');">
                    <?= $view->csrfField() ?>
                    <button type="submit" class="btn btn--danger">Desconectar</button>
                </form>
            </div>
        <?php endif; ?>
    </article>

    <?php if ($tiene_token && ($cuentas_ads !== [] || $propiedades_ga4 !== [])): ?>
        <p style="margin-top:1.5rem">
            <a href="<?= $view->url('/admin/importar/google') ?>" class="btn btn--primary">Importar datos de Google →</a>
        </p>
    <?php endif; ?>

    <article class="card" style="margin-top:1.5rem">
        <h2>Cuentas de Google Ads (<?= count($cuentas_ads) ?>)</h2>
        <?php if ($cuentas_ads === []): ?>
            <p class="muted">Sin cuentas sincronizadas.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Nombre</th><th>Customer ID</th><th>Moneda</th><th>Zona horaria</th><th>Última importación</th></tr></thead>
                <tbody>
                <?php foreach ($cuentas_ads as $c): ?>
                    <tr>
                        <td><?= $view->e((string) $c['nombre']) ?></td>
                        <td><code><?= $view->e((string) $c['customer_id']) ?></code></td>
                        <td><?= $view->e((string) ($c['moneda'] ?? '—')) ?></td>
                        <td><?= $view->e((string) ($c['zona_horaria'] ?? '—')) ?></td>
                        <td><?= $view->e((string) ($c['ultima_sincronizacion_en'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>

    <article class="card" style="margin-top:1.5rem">
        <h2>Propiedades de Google Analytics (<?= count($propiedades_ga4) ?>)</h2>
        <?php if ($propiedades_ga4 === []): ?>
            <p class="muted">Sin propiedades sincronizadas.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Propiedad</th><th>Cuenta</th><th>Property ID</th><th>Moneda</th><th>Última importación</th></tr></thead>
                <tbody>
                <?php foreach ($propiedades_ga4 as $p): ?>
                    <tr>
                        <td><?= $view->e((string) $p['nombre']) ?></td>
                        <td><?= $view->e((string) ($p['cuenta_nombre'] ?? '—')) ?></td>
                        <td><code><?= $view->e((string) $p['property_id']) ?></code></td>
                        <td><?= $view->e((string) ($p['moneda'] ?? '—')) ?></td>
                        <td><?= $view->e((string) ($p['ultima_sincronizacion_en'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>
