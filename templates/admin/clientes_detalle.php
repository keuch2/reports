<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var list<array<string,mixed>> $campanias_asignadas */
/** @var list<int> $ids_asignadas */
/** @var list<array{cuenta:array<string,mixed>, campanias:list<array<string,mixed>>}> $cuentas_con_campanias */
/** @var string|null $success */
/** @var string|null $error */

$asignadasSet = array_flip($ids_asignadas);
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>

<section class="shell__body">
    <p><a href="<?= $view->url('/admin/clientes') ?>">← Volver a clientes</a></p>

    <div class="header-row">
        <div>
            <h1><?= $view->e((string) $cliente['nombre_comercial']) ?></h1>
            <p class="muted">
                <?= $view->e((string) ($cliente['correo_contacto'] ?? '')) ?>
                <?php if ($cliente['telefono']): ?> · <?= $view->e((string) $cliente['telefono']) ?><?php endif; ?>
            </p>
        </div>
        <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/permisos') ?>" class="btn btn--link">
            Configurar permisos avanzados →
        </a>
    </div>

    <?php if ($error): ?><div class="alert alert--error"><?= $view->e((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>

    <article class="card">
        <h2>Campañas asignadas (<?= count($campanias_asignadas) ?>)</h2>
        <?php if ($campanias_asignadas === []): ?>
            <p class="muted">Aún no le asignaste ninguna campaña a este cliente. Elegí abajo cuáles podrá ver.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Cuenta</th><th>Campaña</th><th>Objetivo</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($campanias_asignadas as $c): ?>
                    <tr>
                        <td><small class="muted"><?= $view->e((string) $c['cuenta_nombre']) ?></small></td>
                        <td><?= $view->e((string) $c['campania']) ?></td>
                        <td><?= $view->e((string) ($c['objetivo'] ?? '—')) ?></td>
                        <td><?= $view->e((string) ($c['estado'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>

    <article class="card" style="margin-top:1.5rem">
        <h2>Asignar campañas</h2>
        <p class="muted">Marcá las campañas que querés que el cliente vea. Las que desmarques se desasignan.</p>

        <?php if ($cuentas_con_campanias === []): ?>
            <p class="muted">No hay cuentas publicitarias importadas. <a href="<?= $view->url('/admin/meta') ?>">Conectá Meta</a> e <a href="<?= $view->url('/admin/importar') ?>">importá una cuenta</a> primero.</p>
        <?php else: ?>
            <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/asignar') ?>">
                <?= $view->csrfField() ?>

                <?php foreach ($cuentas_con_campanias as $bloque): ?>
                    <?php $cuenta = $bloque['cuenta']; $campanias = $bloque['campanias']; ?>
                    <?php if ($campanias === []) continue; ?>
                    <fieldset class="fieldset" style="margin-top:1rem">
                        <legend>
                            <?= $view->e((string) $cuenta['nombre']) ?>
                            <span class="muted" style="font-weight:normal;font-size:0.85rem">
                                · <?= $view->e((string) $cuenta['meta_account_id']) ?> · <?= $view->e((string) ($cuenta['moneda'] ?? '')) ?>
                            </span>
                        </legend>
                        <div class="campania-select-actions">
                            <button type="button" class="btn btn--link" data-toggle-all="<?= (int) $cuenta['id'] ?>">Marcar todas</button>
                        </div>
                        <table class="table" data-cuenta="<?= (int) $cuenta['id'] ?>">
                            <thead><tr><th style="width:50px"></th><th>Campaña</th><th>Objetivo</th><th>Estado</th></tr></thead>
                            <tbody>
                            <?php foreach ($campanias as $c): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="campanias[]" value="<?= (int) $c['id'] ?>"
                                               <?= isset($asignadasSet[(int) $c['id']]) ? 'checked' : '' ?>>
                                    </td>
                                    <td><?= $view->e((string) $c['nombre']) ?></td>
                                    <td><?= $view->e((string) ($c['objetivo'] ?? '—')) ?></td>
                                    <td><?= $view->e((string) ($c['estado'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </fieldset>
                <?php endforeach; ?>

                <div class="form-actions" style="margin-top:1.5rem">
                    <button type="submit" class="btn btn--primary">Guardar asignaciones</button>
                </div>
            </form>
        <?php endif; ?>
    </article>
</section>

<script>
document.querySelectorAll('[data-toggle-all]').forEach(btn => {
    btn.addEventListener('click', () => {
        const cuentaId = btn.dataset.toggleAll;
        const checks = document.querySelectorAll(`table[data-cuenta="${cuentaId}"] input[type="checkbox"]`);
        const total = checks.length;
        const marcados = [...checks].filter(c => c.checked).length;
        const nuevoEstado = marcados < total;
        checks.forEach(c => c.checked = nuevoEstado);
    });
});
</script>
