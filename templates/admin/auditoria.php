<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var list<array<string,mixed>> $eventos */
/** @var list<string> $acciones */
/** @var array<string,string> $filtros */
/** @var int $pagina */
/** @var int $por_pagina */
/** @var bool $hay_mas */

$qs = static function (array $extra = []) use ($filtros, $pagina): string {
    $base = array_filter(array_merge($filtros, ['pagina' => $pagina], $extra), static fn ($v) => $v !== '' && $v !== null);
    return $base === [] ? '' : '?' . http_build_query($base);
};
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'auditoria']) ?>

<section class="shell__body">
    <h1>Auditoría</h1>
    <p class="muted">Registro cronológico de acciones sensibles. Sólo lectura.</p>

    <article class="card">
        <form method="GET" class="filters-row">
            <label class="field">
                <span class="field__label">Acción</span>
                <select class="field__input" name="accion">
                    <option value="">— Todas —</option>
                    <?php foreach ($acciones as $a): ?>
                        <option value="<?= $view->e($a) ?>" <?= $filtros['accion'] === $a ? 'selected' : '' ?>>
                            <?= $view->e($a) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span class="field__label">Rol</span>
                <select class="field__input" name="rol">
                    <option value="">— Todos —</option>
                    <option value="admin" <?= $filtros['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="cliente" <?= $filtros['rol'] === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                </select>
            </label>
            <label class="field">
                <span class="field__label">Desde</span>
                <input class="field__input" type="date" name="desde" value="<?= $view->e($filtros['desde']) ?>">
            </label>
            <label class="field">
                <span class="field__label">Hasta</span>
                <input class="field__input" type="date" name="hasta" value="<?= $view->e($filtros['hasta']) ?>">
            </label>
            <label class="field" style="flex:1">
                <span class="field__label">Buscar</span>
                <input class="field__input" type="text" name="buscar" value="<?= $view->e($filtros['buscar']) ?>" placeholder="recurso, detalles...">
            </label>
            <div class="form-actions">
                <a href="<?= $view->url('/admin/auditoria') ?>" class="btn btn--link">Limpiar</a>
                <button type="submit" class="btn btn--primary">Filtrar</button>
            </div>
        </form>
    </article>

    <article class="card" style="margin-top:1rem">
        <?php if ($eventos === []): ?>
            <p class="muted">No hay eventos con esos filtros.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Cuándo</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Acción</th>
                        <th>Recurso</th>
                        <th>IP</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($eventos as $e): ?>
                    <tr>
                        <td><code style="font-size:0.8rem"><?= $view->e((string) $e['ocurrido_en']) ?></code></td>
                        <td>
                            <?php if ($e['usuario_correo']): ?>
                                <?= $view->e((string) $e['usuario_nombre']) ?>
                                <br><small class="muted"><?= $view->e((string) $e['usuario_correo']) ?></small>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $view->e((string) ($e['rol'] ?? '—')) ?></td>
                        <td><code style="font-size:0.85rem"><?= $view->e((string) $e['accion']) ?></code></td>
                        <td>
                            <?php if ($e['recurso_tipo']): ?>
                                <small><?= $view->e((string) $e['recurso_tipo']) ?>#<?= $view->e((string) ($e['recurso_id'] ?? '')) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small class="muted"><?= $view->e((string) ($e['ip'] ?? '—')) ?></small></td>
                        <td>
                            <?php if ($e['detalles']): ?>
                                <details>
                                    <summary>Ver</summary>
                                    <pre style="margin:0.25rem 0 0;font-size:0.75rem"><?= $view->e((string) $e['detalles']) ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem">
                <span class="muted">Página <?= $pagina ?> · mostrando <?= count($eventos) ?> de <?= $por_pagina ?> máx</span>
                <div style="display:flex;gap:0.5rem">
                    <?php if ($pagina > 1): ?>
                        <a href="<?= $view->e($qs(['pagina' => $pagina - 1])) ?>" class="btn btn--link">← Anterior</a>
                    <?php endif; ?>
                    <?php if ($hay_mas): ?>
                        <a href="<?= $view->e($qs(['pagina' => $pagina + 1])) ?>" class="btn btn--link">Siguiente →</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </article>
</section>

<style>
    .filters-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; align-items: end; }
    .filters-row .form-actions { grid-column: 1 / -1; justify-content: flex-end; }
</style>
