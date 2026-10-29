<?php
/**
 * Panel de progreso de importación (modal). Dos modos:
 *
 * - Formularios con `data-importacion` (envío normal, una sola petición): al
 *   enviarse muestra el panel con animación y tiempo transcurrido hasta que el
 *   servidor responde. Si otro listener canceló el envío (validación), no aparece.
 * - Uso por JS (importación por tramos): window.ProgresoImportacion.mostrar(),
 *   .avance(hechos, total, detalle), .terminar(titulo, html), .error(mensaje).
 *
 * @var \MisterCo\Reports\Core\View $view
 */
?>
<div class="progreso" data-progreso hidden role="dialog" aria-modal="true" aria-labelledby="progreso-titulo">
    <div class="progreso__panel">
        <div class="progreso__icono" data-progreso-icono><span class="progreso__spinner"></span></div>
        <h2 id="progreso-titulo" class="progreso__titulo" data-progreso-titulo>Importando…</h2>
        <p class="progreso__detalle" data-progreso-detalle></p>
        <div class="progreso__barra" data-progreso-barra><span data-progreso-relleno></span></div>
        <p class="progreso__meta muted"><span data-progreso-porcentaje></span> <span data-progreso-tiempo></span></p>
        <div class="progreso__resultado" data-progreso-resultado></div>
        <p class="progreso__aviso muted" data-progreso-aviso>No cierres ni recargues esta pestaña mientras se importa.</p>
        <p class="progreso__acciones" data-progreso-acciones hidden>
            <button type="button" class="btn btn--primary" data-progreso-cerrar>Listo</button>
        </p>
    </div>
</div>
<script>
(function () {
    const el = document.querySelector('[data-progreso]');
    const $ = sel => el.querySelector(sel);
    let inicio = 0, reloj = null, alCerrar = null;

    const tiempo = () => {
        const s = Math.floor((Date.now() - inicio) / 1000);
        $('[data-progreso-tiempo]').textContent = '· ' + (s < 60 ? s + ' s' : Math.floor(s / 60) + ' min ' + String(s % 60).padStart(2, '0') + ' s');
    };
    const fin = (estado, titulo, html) => {
        clearInterval(reloj);
        el.dataset.estado = estado;
        $('[data-progreso-titulo]').textContent = titulo;
        $('[data-progreso-resultado]').innerHTML = html;
        $('[data-progreso-aviso]').hidden = true;
        $('[data-progreso-acciones]').hidden = false;
        $('[data-progreso-cerrar]').focus();
    };

    window.ProgresoImportacion = {
        /** opciones: { titulo, detalle, indeterminado (bool), alCerrar (fn) } */
        mostrar(op) {
            inicio = Date.now();
            alCerrar = op.alCerrar || null;
            el.dataset.estado = 'trabajando';
            el.classList.toggle('progreso--indeterminado', !!op.indeterminado);
            $('[data-progreso-titulo]').textContent = op.titulo || 'Importando…';
            $('[data-progreso-detalle]').textContent = op.detalle || '';
            $('[data-progreso-relleno]').style.width = '0%';
            $('[data-progreso-porcentaje]').textContent = op.indeterminado ? '' : '0%';
            $('[data-progreso-resultado]').innerHTML = '';
            $('[data-progreso-aviso]').hidden = false;
            $('[data-progreso-acciones]').hidden = true;
            el.hidden = false;
            tiempo();
            clearInterval(reloj);
            reloj = setInterval(tiempo, 1000);
        },
        avance(hechos, total, detalle) {
            const pct = total > 0 ? Math.round(hechos / total * 100) : 0;
            $('[data-progreso-relleno]').style.width = pct + '%';
            $('[data-progreso-porcentaje]').textContent = pct + '%';
            if (detalle) $('[data-progreso-detalle]').textContent = detalle;
        },
        terminar(titulo, html) {
            $('[data-progreso-relleno]').style.width = '100%';
            $('[data-progreso-porcentaje]').textContent = '100%';
            fin('ok', titulo, html);
        },
        error(mensaje) {
            const p = document.createElement('p');
            p.className = 'alert alert--error';
            p.textContent = mensaje;
            fin('error', 'La importación no se completó', p.outerHTML);
        },
    };

    $('[data-progreso-cerrar]').addEventListener('click', () => {
        el.hidden = true;
        if (alCerrar) alCerrar();
    });

    // Formularios de una sola petición: panel animado mientras el servidor responde.
    document.querySelectorAll('form[data-importacion]').forEach(form => {
        form.addEventListener('submit', ev => {
            setTimeout(() => {
                if (ev.defaultPrevented) return;
                window.ProgresoImportacion.mostrar({
                    titulo: form.dataset.importacion || 'Importando…',
                    detalle: 'Consultando la API y guardando los datos. Puede tardar varios minutos en rangos largos.',
                    indeterminado: true,
                });
            }, 0);
        });
    });
})();
</script>
