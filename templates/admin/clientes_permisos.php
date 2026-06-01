<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var list<array<string,mixed>> $cuentas */
/** @var int $cuenta_id */
/** @var list<array<string,mixed>> $campanias */
/** @var array<int,list<array<string,mixed>>> $anuncios_por_campania */
/** @var list<int> $campanias_ocultas */
/** @var array<int,list<int>> $anuncios_ocultos */
/** @var array<string,list<array<string,mixed>>> $catalogo */
/** @var list<string> $metricas_deshabilitadas */
/** @var string|null $success */
/** @var string|null $error */

$camsOcultasSet = array_flip($campanias_ocultas);
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>

<section class="shell__body">
    <p><a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id'])) ?>">← Volver al cliente</a></p>
    <h1>Permisos · <?= $view->e((string) $cliente['nombre_comercial']) ?></h1>

    <?php if ($error): ?><div class="alert alert--error"><?= $view->e((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>

    <?php if ($cuentas === []): ?>
        <div class="alert alert--warning">
            Este cliente no tiene cuentas asignadas. <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id'])) ?>">Asigná una primero</a>.
        </div>
    <?php else: ?>

    <article class="card">
        <form method="GET" class="form-stack" style="max-width:520px">
            <label class="field">
                <span class="field__label">Cuenta publicitaria</span>
                <select class="field__input" name="cuenta_id" onchange="this.form.submit()">
                    <?php foreach ($cuentas as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $cuenta_id ? 'selected' : '' ?>>
                            <?= $view->e((string) $c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <p class="muted">Default: <strong>todo visible</strong>. Marcá solo lo que querés <em>ocultar</em>.</p>
        </form>
    </article>

    <?php if ($campanias === []): ?>
        <article class="card" style="margin-top:1.5rem">
            <p class="muted">No hay campañas importadas para esta cuenta todavía.
                <a href="<?= $view->url('/admin/importar') ?>">Importá primero</a>.</p>
        </article>
    <?php else: ?>
        <article class="card" style="margin-top:1.5rem">
            <h2>Campañas (<?= count($campanias) ?>)</h2>
            <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/permisos/campanias') ?>">
                <?= $view->csrfField() ?>
                <input type="hidden" name="cuenta_id" value="<?= $cuenta_id ?>">
                <table class="table">
                    <thead><tr><th style="width:60px">Ocultar</th><th>Campaña</th><th>Objetivo</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($campanias as $c): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="ocultas[]" value="<?= (int) $c['id'] ?>"
                                       <?= isset($camsOcultasSet[(int) $c['id']]) ? 'checked' : '' ?>>
                            </td>
                            <td><?= $view->e((string) $c['nombre']) ?></td>
                            <td><?= $view->e((string) ($c['objetivo'] ?? '—')) ?></td>
                            <td><?= $view->e((string) ($c['estado'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="form-actions" style="margin-top:1rem">
                    <button type="submit" class="btn btn--primary">Guardar campañas ocultas</button>
                </div>
            </form>
        </article>

        <?php foreach ($campanias as $cam): ?>
            <?php
            $cid = (int) $cam['id'];
            $anuncios = $anuncios_por_campania[$cid] ?? [];
            if ($anuncios === []) continue;
            $ocultosSet = array_flip($anuncios_ocultos[$cid] ?? []);
            ?>
            <article class="card" style="margin-top:1rem">
                <details <?= !empty($anuncios_ocultos[$cid]) ? 'open' : '' ?>>
                    <summary style="font-size:0.95rem;font-weight:600">
                        Anuncios de "<?= $view->e((string) $cam['nombre']) ?>" (<?= count($anuncios) ?>)
                        <?php if (!empty($anuncios_ocultos[$cid])): ?>
                            <span class="badge badge--fallida"><?= count($anuncios_ocultos[$cid]) ?> ocultos</span>
                        <?php endif; ?>
                    </summary>
                    <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/permisos/anuncios') ?>" style="margin-top:1rem">
                        <?= $view->csrfField() ?>
                        <input type="hidden" name="cuenta_id" value="<?= $cuenta_id ?>">
                        <input type="hidden" name="campania_id" value="<?= $cid ?>">
                        <table class="table">
                            <thead><tr><th style="width:60px">Ocultar</th><th>Anuncio</th><th>Adset</th><th>Tipo</th><th>Estado</th></tr></thead>
                            <tbody>
                            <?php foreach ($anuncios as $a): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ocultos[]" value="<?= (int) $a['id'] ?>"
                                               <?= isset($ocultosSet[(int) $a['id']]) ? 'checked' : '' ?>>
                                    </td>
                                    <td><?= $view->e((string) $a['nombre']) ?></td>
                                    <td><?= $view->e((string) $a['adset_nombre']) ?></td>
                                    <td><?= $view->e((string) ($a['tipo'] ?? '—')) ?></td>
                                    <td><?= $view->e((string) ($a['estado'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="form-actions" style="margin-top:0.75rem">
                            <button type="submit" class="btn btn--primary">Guardar anuncios ocultos de esta campaña</button>
                        </div>
                    </form>
                </details>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; // cuentas existen ?>

    <article class="card" style="margin-top:1.5rem">
        <h2>Métricas habilitadas para este cliente</h2>
        <p class="muted">Marcá las que querés <strong>deshabilitar</strong>. Aplican a dashboard y PDFs.</p>
        <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/permisos/metricas') ?>">
            <?= $view->csrfField() ?>
            <input type="hidden" name="cuenta_id" value="<?= $cuenta_id ?>">

            <?php foreach ($catalogo as $categoria => $metricas): ?>
                <fieldset class="fieldset" style="margin-top:1rem">
                    <legend><?= $view->e(ucfirst($categoria)) ?></legend>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));gap:0.5rem">
                    <?php foreach ($metricas as $m): ?>
                        <label style="display:flex;gap:0.5rem;align-items:flex-start;cursor:pointer">
                            <input type="checkbox" name="deshabilitadas[]" value="<?= (int) $m['id'] ?>"
                                   <?= in_array((string) $m['codigo'], $metricas_deshabilitadas, true) ? 'checked' : '' ?>>
                            <span>
                                <strong><?= $view->e((string) $m['etiqueta']) ?></strong>
                                <?php if ($m['descripcion']): ?>
                                    <br><small class="muted"><?= $view->e((string) $m['descripcion']) ?></small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <div class="form-actions" style="margin-top:1rem">
                <button type="submit" class="btn btn--primary">Guardar métricas deshabilitadas</button>
            </div>
        </form>
    </article>
</section>
