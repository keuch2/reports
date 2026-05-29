<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var list<array<string,mixed>> $importaciones */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'importar']) ?>

<section class="shell__body">
    <div class="header-row">
        <h1>Histórico de importaciones</h1>
        <a href="/admin/importar" class="btn btn--primary">+ Nueva importación</a>
    </div>

    <article class="card">
        <?php if ($importaciones === []): ?>
            <p class="muted">Aún no se ejecutó ninguna importación.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cuenta</th>
                        <th>Rango</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Estado</th>
                        <th class="num">Camp.</th>
                        <th class="num">Adsets</th>
                        <th class="num">Anuncios</th>
                        <th class="num">Snapshots</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($importaciones as $i): ?>
                    <tr>
                        <td>#<?= (int) $i['id'] ?></td>
                        <td><?= $view->e((string) $i['cuenta_nombre']) ?></td>
                        <td><?= $view->e((string) $i['rango_inicio']) ?> → <?= $view->e((string) $i['rango_fin']) ?></td>
                        <td><small><?= $view->e((string) $i['iniciado_en']) ?></small></td>
                        <td><small><?= $view->e((string) ($i['finalizado_en'] ?? '—')) ?></small></td>
                        <td>
                            <span class="badge badge--<?= $view->e((string) $i['estado']) ?>">
                                <?= $view->e((string) $i['estado']) ?>
                            </span>
                            <?php if ($i['estado'] === 'fallida' && $i['error_mensaje']): ?>
                                <details><summary>Error</summary>
                                    <pre style="font-size:0.75rem;margin:0.25rem 0"><?= $view->e((string) $i['error_mensaje']) ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (int) $i['campanias_afectadas'] ?></td>
                        <td class="num"><?= (int) $i['adsets_afectados'] ?></td>
                        <td class="num"><?= (int) $i['anuncios_afectados'] ?></td>
                        <td class="num"><?= (int) $i['snapshots_afectados'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>
