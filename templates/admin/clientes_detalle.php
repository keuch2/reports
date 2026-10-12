<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var array<string,mixed>|null $usuario_primario */
/** @var list<array<string,mixed>> $campanias_asignadas */
/** @var list<int> $ids_asignadas */
/** @var list<array{cuenta:array<string,mixed>, campanias:list<array<string,mixed>>}> $cuentas_con_campanias */
/** @var list<array{cuenta:array<string,mixed>, campanias:list<array<string,mixed>>}> $google_ads_cuentas */
/** @var list<int> $google_ads_ids_asignadas */
/** @var list<array<string,mixed>> $ga4_propiedades */
/** @var list<int> $ga4_ids_asignadas */
/** @var string|null $success */
/** @var string|null $error */

$asignadasSet = array_flip($ids_asignadas);
$googleAdsSet = array_flip($google_ads_ids_asignadas);
$ga4Set = array_flip($ga4_ids_asignadas);
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
        <div style="display:flex;gap:0.5rem;align-items:center">
            <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/dashboard') ?>" class="btn btn--primary">
                👁️ Ver dashboard del cliente
            </a>
            <?php if ($google_ads_ids_asignadas !== []): ?>
                <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/google-ads') ?>" class="btn btn--secondary">Google Ads</a>
            <?php endif; ?>
            <?php if ($ga4_ids_asignadas !== []): ?>
                <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/analytics') ?>" class="btn btn--secondary">Analytics</a>
            <?php endif; ?>
            <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/permisos') ?>" class="btn btn--link">
                Permisos avanzados →
            </a>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert--error"><?= $view->e((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert--success"><?= $view->e((string) $success) ?></div><?php endif; ?>

    <article class="card">
        <h2>Datos del cliente</h2>
        <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/editar') ?>" class="form-stack">
            <?= $view->csrfField() ?>
            <div class="field-row">
                <label class="field">
                    <span class="field__label">Nombre comercial *</span>
                    <input class="field__input" type="text" name="nombre_comercial"
                           value="<?= $view->e((string) $cliente['nombre_comercial']) ?>" required>
                </label>
                <label class="field">
                    <span class="field__label">Correo de contacto</span>
                    <input class="field__input" type="email" name="correo_contacto"
                           value="<?= $view->e((string) ($cliente['correo_contacto'] ?? '')) ?>">
                </label>
            </div>
            <div class="field-row">
                <label class="field">
                    <span class="field__label">Contacto principal</span>
                    <input class="field__input" type="text" name="contacto_principal"
                           value="<?= $view->e((string) ($cliente['contacto_principal'] ?? '')) ?>">
                </label>
                <label class="field">
                    <span class="field__label">Teléfono</span>
                    <input class="field__input" type="text" name="telefono"
                           value="<?= $view->e((string) ($cliente['telefono'] ?? '')) ?>">
                </label>
            </div>
            <p><button type="submit" class="btn btn--primary">Guardar datos</button></p>
        </form>
    </article>

    <article class="card" style="margin-top:1.5rem">
        <h2>Usuario de acceso del cliente</h2>
        <?php if ($usuario_primario === null): ?>
            <p class="muted">Este cliente no tiene un usuario asignado. Eliminá el cliente y créalo de nuevo desde "Nuevo cliente".</p>
        <?php else: ?>
            <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/usuario') ?>" class="form-stack">
                <?= $view->csrfField() ?>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Nombre del usuario *</span>
                        <input class="field__input" type="text" name="usuario_nombre"
                               value="<?= $view->e((string) $usuario_primario['nombre_completo']) ?>" required>
                    </label>
                    <label class="field">
                        <span class="field__label">Correo (usuario para login) *</span>
                        <input class="field__input" type="email" name="usuario_correo"
                               value="<?= $view->e((string) $usuario_primario['correo']) ?>" required>
                    </label>
                </div>
                <p><button type="submit" class="btn btn--primary">Guardar usuario</button></p>
            </form>

            <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--color-border)">

            <h3 style="margin-top:0">Cambiar contraseña</h3>
            <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/password') ?>" class="form-stack" autocomplete="off">
                <?= $view->csrfField() ?>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Nueva contraseña *</span>
                        <input class="field__input" type="password" name="password_nueva" required
                               autocomplete="new-password" minlength="12">
                    </label>
                    <label class="field">
                        <span class="field__label">Confirmar contraseña *</span>
                        <input class="field__input" type="password" name="password_confirm" required
                               autocomplete="new-password" minlength="12">
                    </label>
                </div>
                <p class="muted" style="font-size:0.85rem;margin:0">
                    Mínimo 12 caracteres con mayúscula, minúscula, número y símbolo.
                </p>
                <p><button type="submit" class="btn btn--primary">Actualizar contraseña</button></p>
            </form>
        <?php endif; ?>
    </article>

    <article class="card" style="margin-top:1.5rem">
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
            <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/asignar') ?>" data-filtro-campanias data-fuente="meta">
                <?= $view->csrfField() ?>
                <div class="filtro-campanias">
                    <div class="filtro-campanias__rango">
                        <label class="filtro-campanias__check">
                            <input type="checkbox" data-solo-gasto>
                            <span>Solo campañas con gasto entre</span>
                        </label>
                        <input type="date" class="field__input" data-rango-desde value="<?= $view->e($rango_desde_default) ?>" aria-label="Desde">
                        <span>y</span>
                        <input type="date" class="field__input" data-rango-hasta value="<?= $view->e($rango_hasta_default) ?>" aria-label="Hasta">
                    </div>
                    <input type="search" class="field__input" data-filtro-input autocomplete="off"
                           placeholder="Filtrar campañas por nombre… (ej. Costa Marfil 2026)" aria-label="Filtrar campañas por nombre">
                    <small class="muted" data-filtro-info></small>
                </div>

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
                        <table class="table" data-cuenta="<?= (int) $cuenta['id'] ?>" data-moneda="<?= $view->e((string) ($cuenta['moneda'] ?? '')) ?>">
                            <thead><tr><th style="width:50px"></th><th>Campaña</th><th>Objetivo</th><th>Estado</th><th class="num">En el rango</th></tr></thead>
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
                                    <td class="num muted" data-actividad></td>
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

    <article class="card" style="margin-top:1.5rem">
        <h2>Campañas de Google Ads (<?= count($google_ads_ids_asignadas) ?> asignadas)</h2>
        <p class="muted">Marcá las campañas de Google Ads que el cliente podrá ver en su reporte de Google Ads.</p>

        <?php if ($google_ads_cuentas === []): ?>
            <p class="muted">No hay cuentas de Google Ads sincronizadas. <a href="<?= $view->url('/admin/google') ?>">Conectá Google</a> e importá una cuenta primero.</p>
        <?php else: ?>
            <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/asignar-google-ads') ?>" data-filtro-campanias data-fuente="google_ads">
                <?= $view->csrfField() ?>
                <div class="filtro-campanias">
                    <div class="filtro-campanias__rango">
                        <label class="filtro-campanias__check">
                            <input type="checkbox" data-solo-gasto>
                            <span>Solo campañas con gasto entre</span>
                        </label>
                        <input type="date" class="field__input" data-rango-desde value="<?= $view->e($rango_desde_default) ?>" aria-label="Desde">
                        <span>y</span>
                        <input type="date" class="field__input" data-rango-hasta value="<?= $view->e($rango_hasta_default) ?>" aria-label="Hasta">
                    </div>
                    <input type="search" class="field__input" data-filtro-input autocomplete="off"
                           placeholder="Filtrar campañas por nombre… (ej. Costa Marfil 2026)" aria-label="Filtrar campañas por nombre">
                    <small class="muted" data-filtro-info></small>
                </div>
                <?php foreach ($google_ads_cuentas as $bloque): ?>
                    <?php $cuenta = $bloque['cuenta']; if ($bloque['campanias'] === []) continue; ?>
                    <fieldset class="fieldset" style="margin-top:1rem">
                        <legend>
                            <?= $view->e((string) $cuenta['nombre']) ?>
                            <span class="muted" style="font-weight:normal;font-size:0.85rem">
                                · <?= $view->e((string) $cuenta['customer_id']) ?> · <?= $view->e((string) ($cuenta['moneda'] ?? '')) ?>
                            </span>
                        </legend>
                        <div class="campania-select-actions">
                            <button type="button" class="btn btn--link" data-toggle-all="gads-<?= (int) $cuenta['id'] ?>">Marcar todas</button>
                        </div>
                        <table class="table" data-cuenta="gads-<?= (int) $cuenta['id'] ?>" data-moneda="<?= $view->e((string) ($cuenta['moneda'] ?? '')) ?>">
                            <thead><tr><th style="width:50px"></th><th>Campaña</th><th>Tipo</th><th>Estado</th><th class="num">En el rango</th></tr></thead>
                            <tbody>
                            <?php foreach ($bloque['campanias'] as $c): ?>
                                <tr>
                                    <td><input type="checkbox" name="google_ads_campanias[]" value="<?= (int) $c['id'] ?>" <?= isset($googleAdsSet[(int) $c['id']]) ? 'checked' : '' ?>></td>
                                    <td><?= $view->e((string) $c['nombre']) ?></td>
                                    <td><?= $view->e(\MisterCo\Reports\Domain\EtiquetasGoogle::tipoCampania($c['tipo_canal'])) ?></td>
                                    <td><?= $view->e(\MisterCo\Reports\Domain\EtiquetasGoogle::estadoCampania($c['estado'])) ?></td>
                                    <td class="num muted" data-actividad></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </fieldset>
                <?php endforeach; ?>
                <div class="form-actions" style="margin-top:1.5rem">
                    <button type="submit" class="btn btn--primary">Guardar campañas de Google Ads</button>
                </div>
            </form>
        <?php endif; ?>
    </article>

    <article class="card" style="margin-top:1.5rem">
        <h2>Google Analytics (<?= count($ga4_ids_asignadas) ?> asignadas)</h2>
        <p class="muted">Marcá las propiedades (sitios web) de Google Analytics que el cliente podrá ver.</p>

        <?php if ($ga4_propiedades === []): ?>
            <p class="muted">No hay propiedades de Google Analytics sincronizadas. <a href="<?= $view->url('/admin/google') ?>">Conectá Google</a> primero.</p>
        <?php else: ?>
            <form method="POST" action="<?= $view->url('/admin/clientes/' . ((int) $cliente['id']) . '/asignar-analytics') ?>">
                <?= $view->csrfField() ?>
                <table class="table">
                    <thead><tr><th style="width:50px"></th><th>Propiedad</th><th>Cuenta de Analytics</th><th>Property ID</th><th>Última importación</th></tr></thead>
                    <tbody>
                    <?php foreach ($ga4_propiedades as $p): ?>
                        <tr>
                            <td><input type="checkbox" name="ga4_propiedades[]" value="<?= (int) $p['id'] ?>" <?= isset($ga4Set[(int) $p['id']]) ? 'checked' : '' ?>></td>
                            <td><?= $view->e((string) $p['nombre']) ?></td>
                            <td><?= $view->e((string) ($p['cuenta_nombre'] ?? '—')) ?></td>
                            <td><code><?= $view->e((string) $p['property_id']) ?></code></td>
                            <td><?= $view->e((string) ($p['ultima_sincronizacion_en'] ?? 'nunca')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="form-actions" style="margin-top:1.5rem">
                    <button type="submit" class="btn btn--primary">Guardar propiedades de Analytics</button>
                </div>
            </form>
        <?php endif; ?>
    </article>
</section>

<script>
// Minúsculas y sin tildes, para que "interaccion" encuentre "Interacción".
const normalizar = s => String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
const filaVisible = tr => tr.style.display !== 'none';

// "Marcar todas" alterna solo las campañas visibles de esa cuenta (respeta el filtro).
document.querySelectorAll('[data-toggle-all]').forEach(btn => {
    btn.addEventListener('click', () => {
        const filas = [...document.querySelectorAll(`table[data-cuenta="${btn.dataset.toggleAll}"] tbody tr`)].filter(filaVisible);
        const checks = filas.map(tr => tr.querySelector('input[type="checkbox"]'));
        const nuevoEstado = checks.some(c => !c.checked);
        checks.forEach(c => c.checked = nuevoEstado);
    });
});

// Filtro por nombre (cada palabra tiene que aparecer, en cualquier orden) y, opcionalmente,
// por gasto en un rango de fechas según los datos importados. Las campañas ocultas
// conservan su marca y se guardan igual al enviar.
const urlActividad = <?= json_encode($view->url('/admin/clientes/actividad-campanias')) ?>;
const cacheActividad = {};
async function actividadEnRango(desde, hasta) {
    const clave = desde + '|' + hasta;
    if (!cacheActividad[clave]) {
        const resp = await fetch(urlActividad + '?' + new URLSearchParams({ desde, hasta }), { headers: { 'Accept': 'application/json' } });
        const data = await resp.json();
        if (!resp.ok || data.error) throw new Error(data.error || 'No se pudo consultar la actividad.');
        cacheActividad[clave] = data;
    }
    return cacheActividad[clave];
}

document.querySelectorAll('form[data-filtro-campanias]').forEach(form => {
    const fuente = form.dataset.fuente;
    const etiquetaResultados = fuente === 'google_ads' ? 'conversiones' : 'resultados';
    const input = form.querySelector('[data-filtro-input]');
    const info = form.querySelector('[data-filtro-info]');
    const soloGasto = form.querySelector('[data-solo-gasto]');
    const desde = form.querySelector('[data-rango-desde]');
    const hasta = form.querySelector('[data-rango-hasta]');
    const filas = [...form.querySelectorAll('table[data-cuenta] tbody tr')];
    filas.forEach(tr => tr.dataset.nombre = normalizar(tr.cells[1].textContent));
    let actividad = null; // { idCampania: {gasto, resultados} } cuando el filtro de gasto está activo
    let aviso = '';
    let consulta = 0; // solo la última consulta pisa el resultado (cambios rápidos de fecha)

    const formatear = (tr, a) => {
        const moneda = tr.closest('table').dataset.moneda;
        const gasto = Number(a.gasto).toLocaleString('es-PY', { maximumFractionDigits: moneda === 'PYG' ? 0 : 2 });
        const res = Number(a.resultados).toLocaleString('es-PY', { maximumFractionDigits: 2 });
        return `${moneda} ${gasto} · ${res} ${etiquetaResultados}`;
    };

    const aplicar = () => {
        const palabras = normalizar(input.value).split(/\s+/).filter(Boolean);
        filas.forEach(tr => {
            const a = actividad ? actividad[tr.querySelector('input[type="checkbox"]').value] : null;
            tr.querySelector('[data-actividad]').textContent = a ? formatear(tr, a) : '';
            const pasaNombre = palabras.every(p => tr.dataset.nombre.includes(p));
            tr.style.display = pasaNombre && (actividad === null || a) ? '' : 'none';
        });
        form.querySelectorAll('fieldset').forEach(fs => {
            fs.style.display = [...fs.querySelectorAll('tbody tr')].some(filaVisible) ? '' : 'none';
        });
        const visibles = filas.filter(filaVisible).length;
        const filtrando = palabras.length > 0 || actividad !== null;
        info.textContent = aviso || (!filtrando ? ''
            : (visibles === 0 ? 'Ninguna campaña coincide con el filtro.' : `${visibles} de ${filas.length} campañas coinciden.`));
    };

    const recargarActividad = async () => {
        aviso = '';
        if (!soloGasto.checked) { actividad = null; aplicar(); return; }
        if (!desde.value || !hasta.value || desde.value > hasta.value) {
            actividad = null; aviso = 'Elegí un rango de fechas válido.'; aplicar(); return;
        }
        info.textContent = 'Buscando campañas con gasto…';
        const esta = ++consulta;
        let resultado = null;
        try {
            resultado = (await actividadEnRango(desde.value, hasta.value))[fuente] || {};
        } catch (e) {
            if (esta === consulta) aviso = e.message;
        }
        if (esta !== consulta) return;
        actividad = resultado;
        aplicar();
    };

    input.addEventListener('keydown', ev => { if (ev.key === 'Enter') ev.preventDefault(); });
    input.addEventListener('input', aplicar);
    soloGasto.addEventListener('change', recargarActividad);
    desde.addEventListener('change', recargarActividad);
    hasta.addEventListener('change', recargarActividad);
});
</script>
