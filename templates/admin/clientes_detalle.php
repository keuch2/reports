<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var list<array<string,mixed>> $cuentas_asignadas */
/** @var list<array<string,mixed>> $cuentas_disponibles */
/** @var string|null $success */
/** @var string|null $error */

$idsAsignadas = array_column($cuentas_asignadas, 'id');
$noAsignadas = array_filter($cuentas_disponibles, fn ($c) => !in_array((int) $c['id'], array_map('intval', $idsAsignadas), true));
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>

<section class="shell__body">
    <p><a href="/admin/clientes">← Volver a clientes</a></p>
    <div class="header-row">
        <div>
            <h1><?= $view->e((string) $cliente['nombre_comercial']) ?></h1>
            <p class="muted">
                <?= $view->e((string) ($cliente['correo_contacto'] ?? '')) ?>
                <?php if ($cliente['telefono']): ?> · <?= $view->e((string) $cliente['telefono']) ?><?php endif; ?>
            </p>
        </div>
        <a href="/admin/clientes/<?= (int) $cliente['id'] ?>/permisos" class="btn btn--primary">Configurar permisos</a>
    </div>

    <?php if ($error): ?><div class="alert alert--error"><?= $view->e((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>

    <article class="card">
        <h2>Cuentas publicitarias asignadas (<?= count($cuentas_asignadas) ?>)</h2>
        <?php if ($cuentas_asignadas === []): ?>
            <p class="muted">Este cliente aún no tiene cuentas asignadas.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Cuenta</th><th>Meta ID</th><th>Moneda</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($cuentas_asignadas as $c): ?>
                    <tr>
                        <td><?= $view->e((string) $c['nombre']) ?></td>
                        <td><code><?= $view->e((string) $c['meta_account_id']) ?></code></td>
                        <td><?= $view->e((string) ($c['moneda'] ?? '—')) ?></td>
                        <td>
                            <form method="POST" action="/admin/clientes/<?= (int) $cliente['id'] ?>/desasignar" style="display:inline">
                                <?= $view->csrfField() ?>
                                <input type="hidden" name="cuenta_id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn btn--link" onclick="return confirm('¿Quitar acceso?');">Quitar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>

    <?php if ($noAsignadas !== []): ?>
    <article class="card" style="margin-top:1.5rem">
        <h2>Asignar nueva cuenta</h2>
        <form method="POST" action="/admin/clientes/<?= (int) $cliente['id'] ?>/asignar" class="form-stack">
            <?= $view->csrfField() ?>
            <label class="field">
                <span class="field__label">Cuenta publicitaria</span>
                <select class="field__input" name="cuenta_id" required>
                    <option value="">— Elegí —</option>
                    <?php foreach ($noAsignadas as $c): ?>
                        <option value="<?= (int) $c['id'] ?>">
                            <?= $view->e((string) $c['nombre']) ?> (<?= $view->e((string) $c['meta_account_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn--primary">Asignar</button>
        </form>
    </article>
    <?php endif; ?>
</section>
