<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var bool $tiene_token */
/** @var list<array<string,mixed>> $cuentas */
/** @var array<int,string|null> $ultimas_fechas */
/** @var array<int, list<array<string,mixed>>> $campanias_por_cuenta */
/** @var list<array<string,mixed>> $recientes */
/** @var string $rango_inicio_default */
/** @var string $rango_fin_default */
/** @var string|null $error */
/** @var string|null $success */

// JSON con campañas por cuenta para el JS (id=meta_campaign_id para el filtering Meta).
$campaniasJson = [];
foreach ($campanias_por_cuenta as $cuentaId => $camps) {
    $campaniasJson[$cuentaId] = array_map(static fn ($c) => [
        'meta_id' => (string) $c['meta_campaign_id'],
        'nombre' => (string) $c['nombre'],
        'objetivo' => (string) ($c['objetivo'] ?? ''),
        'estado' => (string) ($c['estado'] ?? ''),
    ], $camps);
}
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

                <fieldset class="fieldset" id="campanias-fieldset" style="display:none">
                    <legend>Campañas a importar</legend>
                    <p class="muted" style="margin:0">
                        <span id="campanias-modo">Toda la cuenta</span>
                        — <button type="button" class="btn btn--link" id="btn-todas" style="padding:0">Marcar todas</button>
                        / <button type="button" class="btn btn--link" id="btn-ninguna" style="padding:0">Ninguna</button>
                    </p>
                    <div id="campanias-lista" style="max-height:360px;overflow-y:auto;border:1px solid var(--color-border);border-radius:var(--radius);padding:0.5rem"></div>
                    <p class="muted" style="font-size:0.85rem;margin:0">
                        Tip: si la cuenta es grande o falla por timeout, marcá solo las campañas que querés actualizar.
                        Si no marcás ninguna, se importa la cuenta entera.
                    </p>
                </fieldset>

                <div id="campanias-sin-conocer" class="alert alert--warning" style="display:none;font-size:0.88rem">
                    No tenemos campañas conocidas de esta cuenta todavía. La importación va a traer todas las campañas + métricas del rango. Después podrás importar selectivamente.
                </div>

                <p class="muted">Se traen campañas, conjuntos, anuncios y métricas diarias a nivel anuncio.</p>
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
                        <th class="num">Camp.</th>
                        <th class="num">Anuncios</th>
                        <th class="num">Snapshots</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recientes as $r): ?>
                    <tr>
                        <td>#<?= (int) $r['id'] ?></td>
                        <td><?= $view->e((string) $r['cuenta_nombre']) ?></td>
                        <td><?= $view->e((string) $r['rango_inicio']) ?> → <?= $view->e((string) $r['rango_fin']) ?></td>
                        <td><small><?= $view->e((string) $r['iniciado_en']) ?></small></td>
                        <td>
                            <span class="badge badge--<?= $view->e((string) $r['estado']) ?>">
                                <?= $view->e((string) $r['estado']) ?>
                            </span>
                            <?php if (!empty($r['error_mensaje'])): ?>
                                <details><summary><?= $r['estado'] === 'fallida' ? 'Ver error' : 'Ver aviso' ?></summary>
                                    <pre style="white-space:pre-wrap;font-size:0.78rem;margin:0.25rem 0"><?= $view->e((string) $r['error_mensaje']) ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (int) $r['campanias_afectadas'] ?></td>
                        <td class="num"><?= (int) $r['anuncios_afectados'] ?></td>
                        <td class="num"><?= (int) $r['snapshots_afectados'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>

<script>
const campaniasPorCuenta = <?= json_encode($campaniasJson, JSON_UNESCAPED_UNICODE) ?>;
const cuentaSelect = document.getElementById('cuenta-select');
const fieldset = document.getElementById('campanias-fieldset');
const lista = document.getElementById('campanias-lista');
const modoLabel = document.getElementById('campanias-modo');

function renderCampanias(cuentaId) {
    const camps = campaniasPorCuenta[cuentaId] || [];
    const sinConocer = document.getElementById('campanias-sin-conocer');
    if (camps.length === 0) {
        fieldset.style.display = 'none';
        sinConocer.style.display = cuentaId ? '' : 'none';
        return;
    }
    sinConocer.style.display = 'none';
    fieldset.style.display = '';
    lista.innerHTML = camps.map(c => {
        const obj = c.objetivo ? ` <small style="color:var(--color-muted)">· ${c.objetivo}</small>` : '';
        const est = c.estado ? ` <small style="color:var(--color-muted)">· ${c.estado}</small>` : '';
        return `<label style="display:flex;gap:0.5rem;align-items:flex-start;padding:0.25rem 0">
            <input type="checkbox" name="campanias_meta_ids[]" value="${c.meta_id}">
            <span><strong>${c.nombre}</strong>${obj}${est}</span>
        </label>`;
    }).join('');
    actualizarModoLabel();
}

function actualizarModoLabel() {
    const marcadas = lista.querySelectorAll('input[type=checkbox]:checked').length;
    const total = lista.querySelectorAll('input[type=checkbox]').length;
    modoLabel.textContent = marcadas === 0
        ? `Toda la cuenta (${total} campañas conocidas)`
        : `Solo ${marcadas} de ${total} campañas`;
}

cuentaSelect.addEventListener('change', () => renderCampanias(cuentaSelect.value));
lista.addEventListener('change', actualizarModoLabel);

document.getElementById('btn-todas').addEventListener('click', () => {
    lista.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = true);
    actualizarModoLabel();
});
document.getElementById('btn-ninguna').addEventListener('click', () => {
    lista.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false);
    actualizarModoLabel();
});

document.getElementById('importar-form')?.addEventListener('submit', function () {
    const btn = document.getElementById('importar-btn');
    btn.disabled = true;
    btn.textContent = 'Importando... no cierres esta ventana';
});

// Importación incremental: precompletar "desde" con el día siguiente a la última fecha importada.
document.getElementById('btn-incremental')?.addEventListener('click', function () {
    const opt = cuentaSelect.options[cuentaSelect.selectedIndex];
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
