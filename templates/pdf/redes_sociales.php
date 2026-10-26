<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $cliente */
/** @var string $desde */
/** @var string $hasta */
/** @var list<array<string,mixed>> $paginas */
/** @var list<array<string,mixed>> $cuentas_instagram */
/** @var array<string,mixed>|null $facebook */
/** @var array<string,mixed>|null $instagram */
/** @var string|null $comentarios */
/** @var string $generado_en */

$fmtNum = static fn ($v): string => $v === null ? '—' : number_format((float) $v, 0, ',', '.');
// La fuente del PDF no tiene emojis (saldrían como cuadrados): se quitan del texto.
$sinEmojis = static fn (string $t): string => trim((string) preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $t));

// Mismos KPI que templates/redes/dashboard.php.
$kpis = [
    'facebook' => [
        ['Visualizaciones', 'visualizaciones'], ['Espectadores', 'espectadores'], ['Interacciones', 'interacciones'],
        ['Nuevos seguidores', 'nuevos_seguidores'], ['Dejaron de seguir', 'dejaron_de_seguir'], ['Visitas al perfil', 'visitas_perfil'],
    ],
    'instagram' => [
        ['Visualizaciones', 'visualizaciones'], ['Alcance', 'alcance'], ['Cuentas que interactuaron', 'cuentas_interactuaron'],
        ['Interacciones', 'interacciones'], ['Me gusta', 'me_gusta'], ['Comentarios', 'comentarios'],
        ['Compartidos', 'compartidos'], ['Guardados', 'guardados'], ['Toques en enlaces', 'toques_enlaces'],
        ['Nuevos seguidores', 'nuevos_seguidores'], ['Dejaron de seguir', 'dejaron_de_seguir'],
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
<?= $view->renderPartial('pdf/portada', [
    'cliente' => $cliente,
    'subtitulo' => 'Reporte de Facebook e Instagram',
    'detalle' => implode(' · ', $nombres),
    'desde' => $desde,
    'hasta' => $hasta,
    'generado_en' => $generado_en,
]) ?>

<?php foreach ($redes as $clave => $red):
    $d = $red['datos'];
    $t = $d['totales'];
    $celdas = array_merge([['Seguidores actuales', $d['seguidores']]],
        array_map(static fn ($k) => [$k[0], $t[$k[1]] ?? null], $kpis[$clave]),
        [['Publicaciones', $d['cantidad_publicaciones']]]);
?>
<h2><?= $view->e($red['titulo']) ?></h2>
<table class="kpi-row">
    <?php foreach (array_chunk($celdas, 3) as $fila): ?>
        <tr>
            <?php foreach ($fila as [$etiqueta, $valor]): ?>
                <td><div class="kpi-label"><?= $view->e($etiqueta) ?></div><div class="kpi-value"><?= $fmtNum($valor) ?></div></td>
            <?php endforeach; ?>
            <?php for ($i = count($fila); $i < 3; $i++): ?><td></td><?php endfor; ?>
        </tr>
    <?php endforeach; ?>
</table>

<?php if (count($d['por_entidad']) > 1): ?>
<h3>Por <?= $view->e(strtolower($red['entidad'])) ?></h3>
<table>
    <thead><tr><th><?= $view->e($red['entidad']) ?></th><th class="num">Visualizaciones</th><th class="num">Interacciones</th><th class="num">Nuevos seguidores</th></tr></thead>
    <tbody>
    <?php foreach ($d['por_entidad'] as $e): ?>
        <tr>
            <td><?= $view->e((string) $e['entidad']) ?></td>
            <td class="num"><?= $fmtNum($e['visualizaciones'] ?? null) ?></td>
            <td class="num"><?= $fmtNum($e['interacciones'] ?? null) ?></td>
            <td class="num"><?= $fmtNum($e['nuevos_seguidores'] ?? null) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h3>Publicaciones destacadas</h3>
<?php if ($d['publicaciones'] === []): ?>
    <p class="muted">No hay publicaciones importadas en este período.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Fecha</th><th>Publicación</th><th class="num"><?= $clave === 'facebook' ? 'Reacciones' : 'Me gusta' ?></th><th class="num">Coment.</th><?php if ($clave === 'facebook'): ?><th class="num">Compart.</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($d['publicaciones'] as $p): ?>
            <tr>
                <td><?= $view->e(date('d/m/Y', strtotime((string) $p['publicado_en']))) ?></td>
                <td>
                    <?= $view->e(mb_strimwidth($sinEmojis((string) ($p['texto'] ?? '(sin texto)')), 0, 110, '…')) ?>
                    <?php if (!empty($p['permalink'])): ?><br><a href="<?= $view->e((string) $p['permalink']) ?>" style="font-size:8pt">Ver publicación</a><?php endif; ?>
                </td>
                <td class="num"><?= $fmtNum($p['me_gusta']) ?></td>
                <td class="num"><?= $fmtNum($p['comentarios']) ?></td>
                <?php if ($clave === 'facebook'): ?><td class="num"><?= $fmtNum($p['compartidos']) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($d['evolucion'] !== []): ?>
<h3>Evolución diaria</h3>
<table>
    <thead><tr><th>Fecha</th><th class="num">Visualizaciones</th><th class="num"><?= $clave === 'facebook' ? 'Espectadores' : 'Alcance' ?></th><th class="num">Interacciones</th><th class="num">Nuevos seguidores</th></tr></thead>
    <tbody>
    <?php foreach ($d['evolucion'] as $e): ?>
        <tr>
            <td><?= $view->e((string) $e['fecha']) ?></td>
            <td class="num"><?= $fmtNum($e['visualizaciones'] ?? null) ?></td>
            <td class="num"><?= $fmtNum($e[$clave === 'facebook' ? 'espectadores' : 'alcance'] ?? null) ?></td>
            <td class="num"><?= $fmtNum($e['interacciones'] ?? null) ?></td>
            <td class="num"><?= $fmtNum($e['nuevos_seguidores'] ?? null) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php endforeach; ?>

<p class="muted" style="font-size:8pt;margin-top:10pt">
    Espectadores, alcance y cuentas que interactuaron son personas únicas por día: al sumar varios días, una misma
    persona puede contarse más de una vez. "—" indica que Meta no informó esa métrica para el período.
</p>

<?php if ($comentarios !== null && trim($comentarios) !== ''): ?>
<h2>Comentarios estratégicos</h2>
<div style="white-space:pre-wrap;font-size:10pt;line-height:1.5"><?= $view->e($comentarios) ?></div>
<?php endif; ?>
