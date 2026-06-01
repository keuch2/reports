<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var list<array<string,mixed>> $clientes */
/** @var string|null $success */
/** @var string|null $error */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>

<section class="shell__body">
    <div class="header-row">
        <h1>Clientes</h1>
        <a href="<?= $view->url('/admin/clientes/nuevo') ?>" class="btn btn--primary">+ Nuevo cliente</a>
    </div>

    <?php if ($error): ?><div class="alert alert--error"><?= $view->e((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>

    <article class="card">
        <?php if ($clientes === []): ?>
            <p class="muted">Aún no hay clientes registrados.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Usuario primario</th>
                        <th>Creado</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td>
                            <strong><?= $view->e((string) $c['nombre_comercial']) ?></strong>
                            <?php if ($c['correo_contacto']): ?>
                                <br><span class="muted"><?= $view->e((string) $c['correo_contacto']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['usuario_correo']): ?>
                                <?= $view->e((string) $c['usuario_nombre']) ?>
                                <br><span class="muted"><?= $view->e((string) $c['usuario_correo']) ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $view->e((string) $c['creado_en']) ?></td>
                        <td>
                            <span class="badge badge--<?= ((int) $c['activo']) === 1 ? 'completada' : 'fallida' ?>">
                                <?= ((int) $c['activo']) === 1 ? 'activo' : 'inactivo' ?>
                            </span>
                        </td>
                        <td><a href="<?= $view->url('/admin/clientes/' . ((int) $c['id'])) ?>" class="btn btn--link">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>
