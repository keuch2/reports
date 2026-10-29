<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var bool $tiene_token */
/** @var list<array<string,mixed>> $cuentas_ads */
/** @var list<array<string,mixed>> $propiedades_ga4 */
/** @var list<array<string,mixed>> $recientes */
/** @var string $rango_inicio_default */
/** @var string $rango_fin_default */
/** @var string|null $error */
/** @var string|null $success */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'importar']) ?>

<section class="shell__body">
    <?= $view->renderPartial('partials/importar_pestanas', ['activa' => 'google']) ?>

    <?php if ($error): ?><div class="alert alert--error"><?= $view->e((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>

    <article class="card">
        <h2>Nueva importación</h2>
        <?php if (!$tiene_token || ($cuentas_ads === [] && $propiedades_ga4 === [])): ?>
            <p class="muted">Conectá Google y sincronizá las cuentas desde <a href="<?= $view->url('/admin/google') ?>">Cuenta Google</a> para poder importar.</p>
        <?php else: ?>
            <form method="POST" action="<?= $view->url('/admin/google/importar') ?>" class="form-stack" data-importacion="Importando datos de Google">
                <?= $view->csrfField() ?>
                <label class="field">
                    <span class="field__label">Origen</span>
                    <select class="field__input" name="origen" required>
                        <?php if ($cuentas_ads !== []): ?>
                            <optgroup label="Google Ads">
                                <?php foreach ($cuentas_ads as $c): ?>
                                    <option value="ads:<?= (int) $c['id'] ?>"><?= $view->e((string) $c['nombre']) ?> (<?= $view->e((string) $c['customer_id']) ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if ($propiedades_ga4 !== []): ?>
                            <optgroup label="Google Analytics">
                                <?php foreach ($propiedades_ga4 as $p): ?>
                                    <option value="ga4:<?= (int) $p['id'] ?>"><?= $view->e((string) $p['nombre']) ?> (<?= $view->e((string) ($p['cuenta_nombre'] ?? '')) ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </label>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Desde</span>
                        <input class="field__input" type="date" name="rango_inicio" value="<?= $view->e($rango_inicio_default) ?>" required>
                    </label>
                    <label class="field">
                        <span class="field__label">Hasta</span>
                        <input class="field__input" type="date" name="rango_fin" value="<?= $view->e($rango_fin_default) ?>" required>
                    </label>
                </div>
                <p class="muted" style="font-size:0.85rem;margin:0">
                    Re-importar un rango reemplaza los datos de ese rango. Google Ads trae todas las campañas de la cuenta;
                    después asignalas a cada cliente desde su ficha.
                </p>
                <p><button type="submit" class="btn btn--primary">Importar</button></p>
            </form>
        <?php endif; ?>
    </article>

    <article class="card" style="margin-top:1.5rem">
        <h2>Importaciones recientes</h2>
        <?php if ($recientes === []): ?>
            <p class="muted">Todavía no hay importaciones de Google.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>#</th><th>Fuente</th><th>Origen</th><th>Rango</th><th>Estado</th><th class="num">Filas</th><th>Detalle</th></tr></thead>
                <tbody>
                <?php foreach ($recientes as $i): ?>
                    <tr>
                        <td><?= (int) $i['id'] ?></td>
                        <td><?= $i['fuente'] === 'ga4' ? 'Analytics' : 'Google Ads' ?></td>
                        <td><?= $view->e((string) ($i['referencia_nombre'] ?? '—')) ?></td>
                        <td><small><?= $view->e((string) $i['rango_inicio']) ?> → <?= $view->e((string) $i['rango_fin']) ?></small></td>
                        <td><span class="badge badge--<?= $view->e((string) $i['estado']) ?>"><?= $view->e((string) $i['estado']) ?></span></td>
                        <td class="num"><?= (int) $i['filas_afectadas'] ?></td>
                        <td><small class="muted"><?= $view->e((string) ($i['error_mensaje'] ?? '')) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>

</section>

<?= $view->renderPartial('partials/progreso_importacion') ?>
