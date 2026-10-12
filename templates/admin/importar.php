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

// La lista de campañas para selección se carga EN VIVO desde Meta vía
// /admin/importar/campanias (ver JS abajo), no desde $campanias_por_cuenta,
// para incluir campañas nuevas/completadas aún no importadas.
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
                        <input class="field__input" type="date" name="rango_inicio" id="rango-inicio"
                               value="<?= $view->e($rango_inicio_default) ?>" required>
                    </label>
                    <label class="field">
                        <span class="field__label">Hasta</span>
                        <input class="field__input" type="date" name="rango_fin" id="rango-fin"
                               value="<?= $view->e($rango_fin_default) ?>" required>
                    </label>
                </div>

                <div id="importar-todo-box" style="display:none;background:var(--color-surface-alt,#f5f6f8);border:1px solid var(--color-border);border-radius:var(--radius);padding:0.75rem 1rem;margin-bottom:0.5rem">
                    <label style="display:flex;gap:0.6rem;align-items:flex-start;cursor:pointer;margin:0">
                        <input type="checkbox" name="importar_todo" id="importar-todo" value="1" checked style="margin-top:0.2rem">
                        <span>
                            <strong>Importar toda la cuenta</strong> (recomendado)
                            <br><small class="muted">Trae <em>todas</em> las campañas de Meta, incluidas las nuevas y las completadas que todavía no están en el sistema. Desmarcá esta opción solo si querés elegir campañas específicas.</small>
                        </span>
                    </label>
                </div>

                <fieldset class="fieldset" id="campanias-fieldset" style="display:none">
                    <legend>Campañas a importar</legend>
                    <p class="muted" style="margin:0">
                        <span id="campanias-modo">Toda la cuenta</span>
                        — <button type="button" class="btn btn--link" id="btn-todas" style="padding:0">Marcar todas</button>
                        / <button type="button" class="btn btn--link" id="btn-ninguna" style="padding:0">Ninguna</button>
                    </p>
                    <label style="display:flex;gap:0.5rem;align-items:center;cursor:pointer;margin:0">
                        <input type="checkbox" id="solo-activas" checked>
                        <span>Solo campañas con gasto entre las fechas elegidas <small class="muted">(incluye pausadas y archivadas)</small></span>
                    </label>
                    <input type="search" id="campanias-filtro" class="field__input" autocomplete="off"
                           placeholder="Filtrar por nombre… (ej. Agrodinamica 2023)" aria-label="Filtrar campañas por nombre">
                    <div id="campanias-lista" style="max-height:360px;overflow-y:auto;border:1px solid var(--color-border);border-radius:var(--radius);padding:0.5rem"></div>
                    <p class="muted" style="font-size:0.85rem;margin:0">
                        Solo se importarán las campañas marcadas. Las campañas nuevas de Meta que todavía no
                        aparezcan acá no se traerán en modo selectivo: para eso usá "Importar toda la cuenta".
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
const cuentaSelect = document.getElementById('cuenta-select');
const fieldset = document.getElementById('campanias-fieldset');
const lista = document.getElementById('campanias-lista');
const modoLabel = document.getElementById('campanias-modo');
const importarTodoBox = document.getElementById('importar-todo-box');
const importarTodo = document.getElementById('importar-todo');
const filtro = document.getElementById('campanias-filtro');
const soloActivas = document.getElementById('solo-activas');
const rangoInicio = document.getElementById('rango-inicio');
const rangoFin = document.getElementById('rango-fin');
const urlCampanias = '<?= $view->url('/admin/importar/campanias') ?>';

// Cache de campañas en vivo por cuenta + rango (evita re-consultar Meta al re-tildar).
const cacheEnVivo = {};
let monedaCuenta = '';

// Rango a consultar: solo si "Solo con actividad" está marcado y las fechas son válidas.
function rangoActividad() {
    const desde = rangoInicio.value, hasta = rangoFin.value;
    return soloActivas.checked && desde && hasta && desde <= hasta ? { desde, hasta } : null;
}

// Los nombres vienen de Meta: se escapan antes de insertarlos como HTML.
function esc(s) {
    return String(s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

// Minúsculas y sin tildes, para que "interaccion" encuentre "Interacción".
function normalizar(s) {
    return String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function pintarLista(camps) {
    // Al recargar (otro rango) se conservan las campañas que ya estaban marcadas.
    const marcadas = new Set([...lista.querySelectorAll('input[type=checkbox]:checked')].map(c => c.value));
    lista.innerHTML = camps.map(c => {
        const obj = c.objetivo ? ` <small style="color:var(--color-muted)">· ${esc(c.objetivo)}</small>` : '';
        const est = c.estado ? ` <small style="color:var(--color-muted)">· ${esc(c.estado)}</small>` : '';
        const gasto = c.gasto ? ` <small style="color:var(--color-muted)">· gasto ${esc(monedaCuenta)} ${Number(c.gasto).toLocaleString('es-PY', { maximumFractionDigits: monedaCuenta === 'PYG' ? 0 : 2 })}</small>` : '';
        const res = c.resultados ? ` <small style="color:var(--color-muted)">· ${Number(c.resultados).toLocaleString('es-PY')} resultados</small>` : '';
        return `<label data-nombre="${esc(normalizar(c.nombre))}" style="display:flex;gap:0.5rem;align-items:flex-start;padding:0.25rem 0">
            <input type="checkbox" name="campanias_meta_ids[]" value="${esc(c.meta_id)}">
            <span><strong>${esc(c.nombre)}</strong>${obj}${est}${gasto}${res}</span>
        </label>`;
    }).join('');
    lista.querySelectorAll('input[type=checkbox]').forEach(c => { c.checked = marcadas.has(c.value); });
    aplicarFiltro();
}

// Cada palabra del filtro tiene que aparecer en el nombre, en cualquier orden.
// Las campañas ocultas por el filtro conservan su marca y se importan igual.
function aplicarFiltro() {
    const palabras = normalizar(filtro.value).split(/\s+/).filter(Boolean);
    lista.querySelectorAll('label[data-nombre]').forEach(l => {
        l.style.display = palabras.every(p => l.dataset.nombre.includes(p)) ? 'flex' : 'none';
    });
    actualizarModoLabel();
}

function checkboxesVisibles() {
    return [...lista.querySelectorAll('label[data-nombre]')]
        .filter(l => l.style.display !== 'none')
        .map(l => l.querySelector('input[type=checkbox]'));
}

// Carga las campañas EN VIVO desde Meta para la cuenta elegida y las muestra
// para selección. Así aparecen también las campañas nuevas/completadas que
// todavía no fueron importadas al sistema.
async function cargarCampaniasEnVivo(cuentaId) {
    if (!cuentaId) return;
    fieldset.style.display = '';
    const rango = rangoActividad();
    const clave = cuentaId + '|' + (rango ? rango.desde + '|' + rango.hasta : 'todas');
    if (cacheEnVivo[clave]) { monedaCuenta = cacheEnVivo[clave].moneda; mostrarCampanias(cacheEnVivo[clave].campanias, rango); return; }

    const marcadasAntes = new Set([...lista.querySelectorAll('input[type=checkbox]:checked')].map(c => c.value));
    lista.innerHTML = '<p class="muted" style="margin:0.5rem">Cargando campañas desde Meta…</p>';
    modoLabel.textContent = 'Cargando…';
    try {
        const params = new URLSearchParams({ cuenta_id: cuentaId });
        if (rango) { params.set('desde', rango.desde); params.set('hasta', rango.hasta); }
        const resp = await fetch(urlCampanias + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
        });
        const data = await resp.json();
        if (!resp.ok || data.error) {
            lista.innerHTML = '<p class="alert alert--error" style="margin:0.5rem">' +
                (data.error || 'No se pudieron cargar las campañas.') + '</p>';
            modoLabel.textContent = 'Error al cargar';
            return;
        }
        cacheEnVivo[clave] = { campanias: data.campanias || [], moneda: data.moneda || '' };
        monedaCuenta = cacheEnVivo[clave].moneda;
        // Restaurar las marcas previas antes de pintar (el placeholder de carga las borró).
        lista.innerHTML = [...marcadasAntes].map(v => `<input type="checkbox" value="${esc(v)}" checked hidden>`).join('');
        mostrarCampanias(cacheEnVivo[clave].campanias, rango);
    } catch (e) {
        lista.innerHTML = '<p class="alert alert--error" style="margin:0.5rem">Error de red al consultar Meta.</p>';
        modoLabel.textContent = 'Error al cargar';
    }
}

function mostrarCampanias(camps, rango) {
    if (camps.length === 0) {
        lista.innerHTML = '<p class="muted" style="margin:0.5rem">' + (rango
            ? `Ninguna campaña generó gasto entre ${esc(rango.desde)} y ${esc(rango.hasta)}. Desmarcá "Solo campañas con gasto" para ver todas.`
            : 'Esta cuenta no tiene campañas en Meta.') + '</p>';
        modoLabel.textContent = 'Sin campañas';
        return;
    }
    pintarLista(camps);
}

function alElegirCuenta(cuentaId) {
    importarTodoBox.style.display = cuentaId ? '' : 'none';
    document.getElementById('campanias-sin-conocer').style.display = 'none';
    // Al cambiar de cuenta reseteamos a "importar todo" (opción segura por defecto).
    importarTodo.checked = true;
    aplicarModoImportarTodo();
}

// Cuando "Importar toda la cuenta" está marcado, la lista selectiva se oculta.
// Cuando se desmarca, cargamos las campañas EN VIVO desde Meta para elegir.
function aplicarModoImportarTodo() {
    const todo = importarTodo.checked;
    if (todo) {
        fieldset.style.display = 'none';
        lista.querySelectorAll('input[type=checkbox]').forEach(c => { c.disabled = true; c.checked = false; });
    } else {
        cargarCampaniasEnVivo(cuentaSelect.value);
    }
}

function actualizarModoLabel() {
    const marcadas = lista.querySelectorAll('input[type=checkbox]:checked').length;
    const total = lista.querySelectorAll('input[type=checkbox]').length;
    const visibles = checkboxesVisibles().length;
    const filtrado = filtro.value.trim() !== '' ? ` · ${visibles} coinciden con el filtro` : '';
    modoLabel.textContent = (marcadas === 0
        ? `Ninguna marcada de ${total} — marcá al menos una`
        : `${marcadas} de ${total} campañas seleccionadas`) + filtrado;
}

cuentaSelect.addEventListener('change', () => alElegirCuenta(cuentaSelect.value));
lista.addEventListener('change', actualizarModoLabel);
importarTodo.addEventListener('change', aplicarModoImportarTodo);
filtro.addEventListener('input', aplicarFiltro);

// Cambiar el rango o la casilla de actividad recarga la lista (con una pequeña espera
// para no consultar Meta en cada tecla de la fecha).
let recargaPendiente = null;
function recargarSiSelectivo() {
    if (importarTodo.checked || !cuentaSelect.value) return;
    clearTimeout(recargaPendiente);
    recargaPendiente = setTimeout(() => cargarCampaniasEnVivo(cuentaSelect.value), 400);
}
soloActivas.addEventListener('change', recargarSiSelectivo);
rangoInicio.addEventListener('change', recargarSiSelectivo);
rangoFin.addEventListener('change', recargarSiSelectivo);
// Enter en el filtro no debe disparar la importación.
filtro.addEventListener('keydown', ev => { if (ev.key === 'Enter') ev.preventDefault(); });

// "Marcar todas" / "Ninguna" actúan sobre las campañas visibles (las que pasan el filtro).
document.getElementById('btn-todas').addEventListener('click', () => {
    checkboxesVisibles().forEach(c => c.checked = true);
    actualizarModoLabel();
});
document.getElementById('btn-ninguna').addEventListener('click', () => {
    checkboxesVisibles().forEach(c => c.checked = false);
    actualizarModoLabel();
});

document.getElementById('importar-form')?.addEventListener('submit', function (ev) {
    // En modo selectivo (importar todo desmarcado) exigimos al menos una campaña,
    // para que no se dispare una cuenta completa por error.
    if (!importarTodo.checked) {
        const marcadas = lista.querySelectorAll('input[type=checkbox]:checked').length;
        if (marcadas === 0) {
            ev.preventDefault();
            alert('Marcá al menos una campaña, o activá "Importar toda la cuenta".');
            return;
        }
    }
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
    recargarSiSelectivo();
});
</script>
