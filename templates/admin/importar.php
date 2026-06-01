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
            No hay un token Meta configurado. <a href="<?= $view->url('/admin/meta') ?>">Conectalo primero</a>.
        </div>
    <?php elseif ($cuentas === []): ?>
        <div class="alert alert--warning">
            No hay cuentas publicitarias sincronizadas todavía. Reconectá el token desde
            <a href="<?= $view->url('/admin/meta') ?>">Cuenta Meta</a>.
        </div>
    <?php else: ?>
        <article class="card">
            <h2>Nueva importación</h2>
            <form method="POST" action="<?= $view->url('/admin/importar') ?>" class="form-stack" id="importar-form">
                <?= $view->csrfField() ?>
                <label class="field">
                    <span class="field__label">Cuenta publicitaria</span>
                    <select class="field__input" name="cuenta_id" id="cuenta-select" required>
                        <option value="">— Elegí una —</option>
                        <?php foreach ($cuentas as $c): ?>
                            <?php $ult = $ultimas_fechas[(int) $c['id']] ?? null; ?>
                            <option value="<?= (int) $c['id'] ?>" data-ultima="<?= $view->e((string) ($ult ?? '')) ?>">
                                <?= $view->e((string) $c['nombre']) ?>
                                (<?= $view->e((string) $c['meta_account_id']) ?>)
                                <?= $ult ? ' · última: ' . $view->e($ult) : ' · sin datos' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p>
                    <button type="button" class="btn btn--link" id="btn-incremental" style="padding-left:0">
                        ⟳ Solo días faltantes
                    </button>
                    <span class="muted" id="incremental-hint"></span>
                </p>
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

    // Importación incremental: precompletar "desde" con el día siguiente a la última fecha importada.
    document.getElementById('btn-incremental')?.addEventListener('click', function () {
        const select = document.getElementById('cuenta-select');
        const opt = select.options[select.selectedIndex];
        const hint = document.getElementById('incremental-hint');
        if (!opt || !opt.value) { hint.textContent = 'Elegí una cuenta primero.'; return; }
        const ultima = opt.dataset.ultima;
        const inputDesde = document.querySelector('input[name="rango_inicio"]');
        const inputHasta = document.querySelector('input[name="rango_fin"]');
        const hoy = new Date().toISOString().slice(0, 10);
        if (!ultima) {
            hint.textContent = 'Esta cuenta no tiene datos previos; se importará el rango completo.';
            return;
        }
        const d = new Date(ultima + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        inputDesde.value = d.toISOString().slice(0, 10);
        inputHasta.value = hoy;
        hint.textContent = `Rango ajustado: ${inputDesde.value} → ${hoy}`;
    });
</script>
