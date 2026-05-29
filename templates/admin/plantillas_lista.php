<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var list<array<string,mixed>> $plantillas */
/** @var string|null $success */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'plantillas']) ?>

<section class="shell__body">
    <div class="header-row">
        <h1>Plantillas PDF</h1>
        <a href="/admin/plantillas/nueva" class="btn btn--primary">+ Nueva plantilla</a>
    </div>

    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>

    <article class="card">
        <?php if ($plantillas === []): ?>
            <p class="muted">Aún no hay plantillas. La generación de PDF usa una estructura por defecto si no hay plantilla.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Nombre</th><th>Aplica a</th><th>Secciones</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($plantillas as $p): ?>
                    <?php $secs = \MisterCo\Reports\Repositories\PlantillaPdfRepository::seccionesDe($p); ?>
                    <tr>
                        <td>
                            <strong><?= $view->e((string) $p['nombre']) ?></strong>
                            <?php if ($p['descripcion']): ?><br><span class="muted"><?= $view->e((string) $p['descripcion']) ?></span><?php endif; ?>
                        </td>
                        <td><?= $p['cliente_nombre'] ? $view->e((string) $p['cliente_nombre']) : '<span class="muted">Todos</span>' ?></td>
                        <td><?= count($secs) ?> secciones</td>
                        <td style="white-space:nowrap">
                            <a href="/admin/plantillas/<?= (int) $p['id'] ?>/editar" class="btn btn--link">Editar</a>
                            <form method="POST" action="/admin/plantillas/<?= (int) $p['id'] ?>/eliminar" style="display:inline">
                                <?= $view->csrfField() ?>
                                <button type="submit" class="btn btn--link" onclick="return confirm('¿Eliminar plantilla?');">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>
