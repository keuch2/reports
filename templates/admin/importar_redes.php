<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var list<array<string,mixed>> $paginas */
/** @var list<array<string,mixed>> $recientes */
/** @var string $rango_inicio_default */
/** @var string $rango_fin_default */

$fmtFecha = static fn (string $f): string => date('d/m/Y', strtotime($f));
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'importar']) ?>

<section class="shell__body">
    <?= $view->renderPartial('partials/importar_pestanas', ['activa' => 'redes']) ?>

    <?php if ($paginas === []): ?>
        <div class="alert alert--warning">
            No hay páginas de Facebook sincronizadas. Sincronizalas desde <a href="<?= $view->url('/admin/meta') ?>">Cuenta Meta</a>.
        </div>
    <?php else: ?>
        <article class="card">
            <h2>Nueva importación</h2>
            <form class="form-stack" id="importar-redes-form">
                <label class="field">
                    <span class="field__label">Página o cuenta</span>
                    <select class="field__input" name="origen" required>
                        <optgroup label="Facebook">
                            <?php foreach ($paginas as $p): ?>
                                <option value="facebook:<?= (int) $p['id'] ?>"><?= $view->e((string) $p['nombre']) ?><?= $p['seguidores'] !== null ? ' · ' . number_format((int) $p['seguidores'], 0, ',', '.') . ' seguidores' : '' ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Instagram">
                            <?php foreach ($paginas as $p): ?>
                                <?php if ($p['ig_id'] !== null): ?>
                                    <option value="instagram:<?= (int) $p['ig_id'] ?>">@<?= $view->e((string) $p['ig_username']) ?><?= $p['ig_seguidores'] !== null ? ' · ' . number_format((int) $p['ig_seguidores'], 0, ',', '.') . ' seguidores' : '' ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
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
                    Trae métricas diarias y publicaciones del rango. Meta guarda solo los últimos 2 años, las páginas
                    necesitan al menos 100 seguidores para tener métricas y los datos tardan hasta 48 h en consolidarse.
                    Instagram se importa de a una semana: un rango de varios meses tarda algunos minutos.
                </p>
                <p><button type="submit" class="btn btn--primary">Importar analíticas</button></p>
            </form>
        </article>
    <?php endif; ?>

    <article class="card" style="margin-top:1.5rem">
        <h2>Importaciones recientes</h2>
        <?php if ($recientes === []): ?>
            <p class="muted">Todavía no hay importaciones de Facebook ni de Instagram.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>#</th><th>Origen</th><th>Rango</th><th>Iniciada</th><th>Estado</th><th class="num">Días</th><th class="num">Publicaciones</th></tr></thead>
                <tbody>
                <?php foreach ($recientes as $r): ?>
                    <tr>
                        <td>#<?= (int) $r['id'] ?></td>
                        <td><?= $r['plataforma'] === 'facebook' ? 'Facebook' : 'Instagram' ?> · <?= $view->e((string) ($r['referencia_nombre'] ?? '—')) ?></td>
                        <td><?= $fmtFecha((string) $r['rango_inicio']) ?> → <?= $fmtFecha((string) $r['rango_fin']) ?></td>
                        <td><small><?= $view->e((string) $r['iniciado_en']) ?></small></td>
                        <td>
                            <span class="badge badge--<?= $view->e((string) $r['estado']) ?>"><?= $view->e(str_replace('_', ' ', (string) $r['estado'])) ?></span>
                            <?php if (!empty($r['error_mensaje'])): ?>
                                <details><summary><?= $r['estado'] === 'completada' ? 'Ver avisos' : 'Ver error' ?></summary>
                                    <pre style="white-space:pre-wrap;font-size:0.78rem;margin:0.25rem 0"><?= $view->e((string) $r['error_mensaje']) ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (int) $r['dias_importados'] ?></td>
                        <td class="num"><?= (int) $r['publicaciones'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>

<?= $view->renderPartial('partials/progreso_importacion') ?>

<script>
(function () {
    // Importación por tramos: iniciar → un tramo por petición → finalizar.
    // Cada petición es corta, así un rango de varios meses no choca con el
    // timeout del servidor y la barra muestra el avance real.
    const form = document.getElementById('importar-redes-form');
    if (!form) return;
    const csrf = <?= json_encode($session->csrfToken()) ?>;
    const base = <?= json_encode($view->url('/admin/importar/redes')) ?>;
    const P = window.ProgresoImportacion;
    const esc = s => String(s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const fecha = f => f.split('-').reverse().join('/');

    async function post(url, datos) {
        const cuerpo = new URLSearchParams({ _csrf: csrf, ...datos });
        let resp;
        try {
            resp = await fetch(url, { method: 'POST', body: cuerpo, headers: { 'Accept': 'application/json' } });
        } catch (e) {
            throw new Error('Se perdió la conexión con el servidor. Lo ya importado quedó guardado; volvé a importar el resto.');
        }
        const json = await resp.json().catch(() => null);
        if (!resp.ok || json === null || json.error) {
            throw new Error(json && json.error ? json.error : `El servidor respondió ${resp.status}. Lo ya importado quedó guardado.`);
        }
        return json;
    }

    form.addEventListener('submit', async ev => {
        ev.preventDefault();
        const datos = Object.fromEntries(new FormData(form));
        const select = form.elements.origen;
        const origen = select.options[select.selectedIndex].text.split(' · ')[0];
        P.mostrar({ titulo: 'Importando ' + origen, detalle: 'Preparando la importación…', alCerrar: () => location.reload() });
        try {
            const inicio = await post(base + '/iniciar', datos);
            const total = inicio.tramos.length + 1; // + publicaciones
            for (let i = 0; i < inicio.tramos.length; i++) {
                const [d, h] = inicio.tramos[i];
                P.avance(i, total, `Métricas del ${fecha(d)} al ${fecha(h)} (${i + 1} de ${inicio.tramos.length})`);
                await post(`${base}/${inicio.importacion_id}/tramo`, { desde: d, hasta: h });
            }
            P.avance(inicio.tramos.length, total, 'Publicaciones y seguidores…');
            const r = await post(`${base}/${inicio.importacion_id}/finalizar`, {});
            const avisos = r.avisos.length ? '<p><strong>Avisos de Meta:</strong></p><ul>' + r.avisos.map(a => '<li>' + esc(a) + '</li>').join('') + '</ul>' : '';
            P.terminar('Importación completada', `<p>Importación #${r.importacion_id}: <strong>${r.dias}</strong> días con métricas y <strong>${r.publicaciones}</strong> publicaciones.</p>` + avisos);
        } catch (e) {
            P.error(e.message);
        }
    });
})();
</script>
