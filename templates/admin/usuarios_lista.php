<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var list<array<string,mixed>> $admins */
/** @var string|null $success */
/** @var string|null $error */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'usuarios']) ?>

<section class="shell__body">
    <div class="header-row">
        <h1>Usuarios admin</h1>
        <a href="<?= $view->url('/admin/usuarios/nuevo') ?>" class="btn btn--primary">+ Nuevo admin</a>
    </div>
    <p class="muted">Cuentas con acceso al panel administrativo de Mister Co. Reports.</p>

    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert--error"><?= $view->e((string) $error) ?></div><?php endif; ?>

    <article class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>2FA</th>
                    <th>Último acceso</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <?php $esYo = ((int) $a['id']) === $usuario->id; ?>
                <tr>
                    <td>
                        <?= $view->e((string) $a['nombre_completo']) ?>
                        <?php if ($esYo): ?><span class="badge badge--en_curso">vos</span><?php endif; ?>
                    </td>
                    <td><code style="font-size:0.85rem"><?= $view->e((string) $a['correo']) ?></code></td>
                    <td>
                        <?php if ((int) $a['twofa_habilitado'] === 1): ?>
                            <span class="badge badge--completada">ON</span>
                        <?php else: ?>
                            <span class="badge badge--fallida">OFF</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="muted"><?= $view->e((string) ($a['ultimo_acceso_en'] ?? 'nunca')) ?></small></td>
                    <td>
                        <?php if ((int) $a['activo'] === 1): ?>
                            <span class="badge badge--completada">activo</span>
                        <?php else: ?>
                            <span class="badge badge--fallida">inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <?php if (!$esYo): ?>
                            <?php if ((int) $a['activo'] === 1): ?>
                                <form method="POST" action="<?= $view->url('/admin/usuarios/' . ((int) $a['id']) . '/desactivar') ?>" style="display:inline">
                                    <?= $view->csrfField() ?>
                                    <button type="submit" class="btn btn--link" onclick="return confirm('¿Desactivar este admin?');">Desactivar</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="<?= $view->url('/admin/usuarios/' . ((int) $a['id']) . '/activar') ?>" style="display:inline">
                                    <?= $view->csrfField() ?>
                                    <button type="submit" class="btn btn--link">Reactivar</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?= $view->url('/mi-perfil') ?>" class="btn btn--link">Mi perfil</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</section>
