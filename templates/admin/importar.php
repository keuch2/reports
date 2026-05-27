<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var bool $tiene_token */
/** @var list<array<string,mixed>> $cuentas */
/** @var list<array<string,mixed>> $recientes */
/** @var string $rango_inicio_default */
/** @var string $rango_fin_default */
/** @var string|null $error */
/** @var string|null $success */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'importar']) ?>

<section class="shell__body">
    <h1>Importar datos desde Meta</h1>

    <?php if ($error): ?>
        <div class="alert alert--error"><?= $view->e((string) $error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert--success"><?= $view->e((string) $success) ?></div>
    <?php endif; ?>

    <?php if (!$tiene_token): ?>
        <div class="alert alert--warning">
            No hay un token Meta configurado. <a href="/admin/meta">Conectalo primero</a>.
        </div>
    <?php elseif ($cuentas === []): ?>
        <div class="alert alert--warning">
            No hay cuentas publicitarias sincronizadas todavía. Reconectá el token desde
            <a href="/admin/meta">Cuenta Meta</a>.
        </div>
    <?php else: ?>
        <article class="card">
            <h2>Nueva importación</h2>
            <form method="POST" action="/admin/importar" class="form-stack" id="importar-form">
                <?= $view->csrfField() ?>
                <label class="field">
                    <span class="field__label">Cuenta publicitaria</span>
                    <select class="field__input" name="cuenta_id" required>
                        <option value="">— Elegí una —</option>
                        <?php foreach ($cuentas as $c): ?>
                            <option value="<?= (int) $c['id'] ?>">
                                <?= $view->e((string) $c['nombre']) ?>
                                (<?= $view->e((string) $c['meta_account_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Desde</span>
                        <input class="field__input" type="date" name="rango_inicio"
                               value="<?= $view->e($rango_inicio_default) ?>" required>
                    </label>
                    <label class="field">
                        <span class="field__label">Hasta</span>
                        <input class="field__input" type="date" name="rango_fin"
                               value="<?= $view->e($rango_fin_default) ?>" required>
                    </label>
                </div>
                <p class="muted">Se traen campañas, conjuntos, anuncios y métricas diarias a nivel anuncio.
                    Puede tardar varios minutos según el volumen.</p>
                <button type="submit" class="btn btn--primary" id="importar-btn">
                    Importar
                </button>
            </form>
        </article>
    <?php endif; ?>

    <article class="card" style="margin-top:1.5rem">
        <h2>Importaciones recientes</h2>
        <?php if ($recientes === []): ?>
            <p class="muted">Aún no hay importaciones registradas.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cuenta</th>
                        <th>Rango</th>
                        <th>Inicio</th>
                        <th>Estado</th>
                        <th>Campañas</th>
                        <th>Anuncios</th>
                        <th>Snapshots</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recientes as $r): ?>
                    <tr>
                        <td>#<?= (int) $r['id'] ?></td>
                        <td><?= $view->e((string) $r['cuenta_nombre']) ?></td>
                        <td><?= $view->e((string) $r['rango_inicio']) ?> → <?= $view->e((string) $r['rango_fin']) ?></td>
                        <td><?= $view->e((string) $r['iniciado_en']) ?></td>
                        <td>
                            <span class="badge badge--<?= $view->e((string) $r['estado']) ?>">
                                <?= $view->e((string) $r['estado']) ?>
                            </span>
                            <?php if ($r['estado'] === 'fallida' && $r['error_mensaje']): ?>
                                <details><summary>Ver error</summary>
                                    <pre><?= $view->e((string) $r['error_mensaje']) ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $r['campanias_afectadas'] ?></td>
                        <td><?= (int) $r['anuncios_afectados'] ?></td>
                        <td><?= (int) $r['snapshots_afectados'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>

<script>
    document.getElementById('importar-form')?.addEventListener('submit', function () {
        const btn = document.getElementById('importar-btn');
        btn.disabled = true;
        btn.textContent = 'Importando... no cierres esta ventana';
    });
</script>
