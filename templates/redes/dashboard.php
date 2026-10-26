<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array<string,mixed> $cliente */
/** @var bool $es_admin */
/** @var bool $asignado */
/** @var string $base */
/** @var array{meta: bool, google_ads: bool, analytics: bool, redes: bool} $fuentes */
/** @var \MisterCo\Reports\Domain\PeriodoReporte $periodo */
/** @var list<string> $meses_disponibles */
/** @var list<array<string,mixed>> $paginas */
/** @var list<array<string,mixed>> $cuentas_instagram */
/** @var array<string,mixed>|null $facebook */
/** @var array<string,mixed>|null $instagram */

$fmtNum = static fn ($v): string => $v === null ? '—' : number_format((float) $v, 0, ',', '.');

// [etiqueta, columna] de los KPI de cada red. Las métricas que Meta no
// informó para el período quedan en "—".
$kpis = [
    'facebook' => [
        ['Visualizaciones', 'visualizaciones'],
        ['Espectadores', 'espectadores'],
        ['Interacciones', 'interacciones'],
        ['Nuevos seguidores', 'nuevos_seguidores'],
        ['Dejaron de seguir', 'dejaron_de_seguir'],
        ['Visitas al perfil', 'visitas_perfil'],
    ],
    'instagram' => [
        ['Visualizaciones', 'visualizaciones'],
        ['Alcance', 'alcance'],
        ['Cuentas que interactuaron', 'cuentas_interactuaron'],
        ['Interacciones', 'interacciones'],
        ['Nuevos seguidores', 'nuevos_seguidores'],
        ['Dejaron de seguir', 'dejaron_de_seguir'],
        ['Toques en enlaces', 'toques_enlaces'],
    ],
];
$redes = array_filter([
    'facebook' => $facebook !== null ? ['titulo' => 'Facebook', 'datos' => $facebook, 'entidad' => 'Página'] : null,
    'instagram' => $instagram !== null ? ['titulo' => 'Instagram', 'datos' => $instagram, 'entidad' => 'Cuenta'] : null,
]);
$nombres = array_merge(
    array_map(static fn ($p) => (string) $p['nombre'], $paginas),
    array_map(static fn ($c) => '@' . $c['username'], $cuentas_instagram),
);
?>
<?= $view->renderPartial('partials/encabezado_cliente', [
    'usuario' => $usuario, 'cliente' => $cliente, 'es_admin' => $es_admin,
    'seccion' => 'redes', 'descripcion' => 'Vista previa de Facebook e Instagram',
]) ?>

<section class="shell__body">
    <?= $view->renderPartial('partials/pestanas_dashboard', [
        'es_admin' => $es_admin, 'cliente_id' => (int) $cliente['id'], 'fuentes' => $fuentes,
        'activa' => 'redes', 'periodo' => $periodo,
    ]) ?>
    <div class="header-row">
        <div>
            <h1>Facebook e Instagram</h1>
            <p class="muted"><?= $view->e(implode(' · ', $nombres)) ?></p>
        </div>
    </div>

    <?php if (!$asignado): ?>
        <div class="alert alert--warning">
            Este cliente no tiene páginas de Facebook ni cuentas de Instagram asignadas.
            <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id'])) ?>">Asignarlas</a>.
        </div>
    <?php elseif ($meses_disponibles === []): ?>
        <p class="muted">Aún no hay analíticas importadas de Facebook ni de Instagram.</p>
    <?php else: ?>
        <?= $view->renderPartial('partials/filtro_periodo', [
            'action' => $view->url($base),
            'periodo' => $periodo,
            'meses_disponibles' => $meses_disponibles,
            'exportar' => ['metodo' => 'get', 'url' => $view->url($base . '/reporte')],
        ]) ?>

        <?php foreach ($redes as $clave => $red):
            $d = $red['datos'];
            $t = $d['totales'];
        ?>
            <h2 style="margin-top:1.75rem"><?= $view->e($red['titulo']) ?></h2>
            <div class="kpi-grid">
                <div class="kpi kpi--resultado">
                    <span class="kpi__label">Seguidores actuales</span>
                    <span class="kpi__value"><?= $fmtNum($d['seguidores']) ?></span>
                </div>
                <?php foreach ($kpis[$clave] as [$etiqueta, $columna]): ?>
                    <div class="kpi">
                        <span class="kpi__label"><?= $view->e($etiqueta) ?></span>
                        <span class="kpi__value"><?= $fmtNum($t[$columna] ?? null) ?></span>
                        <?php if ($clave === 'instagram' && $columna === 'interacciones' && ($t['interacciones'] ?? null) !== null): ?>
                            <span class="kpi__sub muted">
                                <?= $fmtNum($t['me_gusta'] ?? null) ?> me gusta · <?= $fmtNum($t['comentarios'] ?? null) ?> comentarios ·
                                <?= $fmtNum($t['compartidos'] ?? null) ?> compartidos · <?= $fmtNum($t['guardados'] ?? null) ?> guardados
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="kpi">
                    <span class="kpi__label">Publicaciones</span>
                    <span class="kpi__value"><?= $fmtNum($d['cantidad_publicaciones']) ?></span>
                </div>
            </div>

            <?php if ($d['evolucion'] !== []): ?>
                <article class="card" style="margin-top:1rem">
                    <h3 style="margin-top:0">Evolución diaria</h3>
                    <canvas id="grafico-<?= $clave ?>" height="100"></canvas>
                </article>
            <?php endif; ?>

            <?php if (count($d['por_entidad']) > 1): ?>
                <article class="card" style="margin-top:1rem">
                    <h3 style="margin-top:0">Por <?= $view->e(strtolower($red['entidad'])) ?></h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= $view->e($red['entidad']) ?></th>
                                <?php foreach ($kpis[$clave] as [$etiqueta]): ?><th class="num"><?= $view->e($etiqueta) ?></th><?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($d['por_entidad'] as $e): ?>
                            <tr>
                                <td><?= $view->e((string) $e['entidad']) ?></td>
                                <?php foreach ($kpis[$clave] as [, $columna]): ?><td class="num"><?= $fmtNum($e[$columna] ?? null) ?></td><?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </article>
            <?php endif; ?>

            <article class="card" style="margin-top:1rem">
                <h3 style="margin-top:0">Publicaciones destacadas</h3>
                <?php if ($d['publicaciones'] === []): ?>
                    <p class="muted">No hay publicaciones importadas en este período.</p>
                <?php else: ?>
                    <div class="publicaciones">
                        <?php foreach ($d['publicaciones'] as $p): ?>
                            <article class="publicacion">
                                <?php if (!empty($p['imagen_url'])): ?>
                                    <img class="publicacion__imagen" src="<?= $view->e((string) $p['imagen_url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                <?php endif; ?>
                                <div class="publicacion__cuerpo">
                                    <small class="muted"><?= $view->e(date('d/m/Y', strtotime((string) $p['publicado_en']))) ?> · <?= $view->e((string) $p['cuenta']) ?></small>
                                    <p class="publicacion__texto"><?= $view->e(mb_strimwidth((string) ($p['texto'] ?? ''), 0, 160, '…')) ?></p>
                                    <small>
                                        <?= $fmtNum($p['me_gusta']) ?> <?= $clave === 'facebook' ? 'reacciones' : 'me gusta' ?> ·
                                        <?= $fmtNum($p['comentarios']) ?> comentarios
                                        <?php if ($clave === 'facebook'): ?> · <?= $fmtNum($p['compartidos']) ?> compartidos<?php endif; ?>
                                    </small>
                                    <?php if (!empty($p['permalink'])): ?>
                                        <a href="<?= $view->e((string) $p['permalink']) ?>" target="_blank" rel="noopener" class="publicacion__link">Ver publicación ↗</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <p class="muted" style="font-size:0.8rem;margin-top:1rem">
            Espectadores, alcance y cuentas que interactuaron son personas únicas por día: al sumar varios días,
            una misma persona puede contarse más de una vez. "—" indica que Meta no informó esa métrica para el período.
        </p>
    <?php endif; ?>
</section>

<?php if ($asignado && $meses_disponibles !== []): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    const series = <?= json_encode(array_map(static fn ($r) => $r['datos']['evolucion'], $redes), JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
    Object.entries(series).forEach(([red, evolucion]) => {
        const canvas = document.getElementById('grafico-' + red);
        if (!canvas || evolucion.length === 0) return;
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: evolucion.map(e => e.fecha),
                datasets: [
                    { label: 'Visualizaciones', data: evolucion.map(e => e.visualizaciones), borderColor: '#1a2f6e', backgroundColor: 'rgba(26,47,110,0.08)', tension: 0.3, yAxisID: 'y1', fill: true },
                    { label: 'Interacciones', data: evolucion.map(e => e.interacciones), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', tension: 0.3, yAxisID: 'y2', fill: true }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y1: { position: 'left', title: { display: true, text: 'Visualizaciones' }, beginAtZero: true, ticks: { precision: 0 } },
                    y2: { position: 'right', title: { display: true, text: 'Interacciones' }, grid: { drawOnChartArea: false }, beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    });
})();
</script>
<?php endif; ?>
