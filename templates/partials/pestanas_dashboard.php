<?php
/**
 * Pestañas del dashboard del cliente: Meta Ads | Google Ads | Google Analytics |
 * Facebook e Instagram.
 * Mantienen el período elegido al cambiar de pestaña.
 *
 * El cliente ve solo las fuentes que tiene asignadas (si es una sola, no hay
 * pestañas). El admin ve las tres; las no asignadas aparecen atenuadas y al
 * entrar muestran el aviso para asignarlas.
 *
 * @var \MisterCo\Reports\Core\View $view
 * @var string $activa 'meta' | 'google_ads' | 'analytics' | 'redes'
 * @var bool $es_admin
 * @var int $cliente_id
 * @var array{meta: bool, google_ads: bool, analytics: bool, redes: bool} $fuentes
 * @var \MisterCo\Reports\Domain\PeriodoReporte|null $periodo
 */

$base = $es_admin ? '/admin/clientes/' . $cliente_id : '/cliente';
$pestanas = [
    'meta' => ['Meta Ads', $es_admin ? $base . '/dashboard' : $base],
    'google_ads' => ['Google Ads', $base . '/google-ads'],
    'analytics' => ['Google Analytics', $base . '/analytics'],
    'redes' => ['Facebook e Instagram', $base . '/redes'],
];
if (!$es_admin) {
    $pestanas = array_filter($pestanas, static fn (string $clave): bool => $fuentes[$clave], ARRAY_FILTER_USE_KEY);
}
if (count($pestanas) < 2) {
    return;
}
$query = isset($periodo) && $periodo !== null && $periodo->query() !== '' ? '?' . $periodo->query() : '';
?>
<nav class="tabs" aria-label="Reportes">
    <?php foreach ($pestanas as $clave => [$etiqueta, $url]): ?>
        <a href="<?= $view->e($view->url($url) . $query) ?>"
           class="tabs__item<?= $clave === $activa ? ' tabs__item--activa' : '' ?><?= $fuentes[$clave] ? '' : ' tabs__item--vacia' ?>"
           <?= $clave === $activa ? 'aria-current="page"' : '' ?>
           <?= $fuentes[$clave] ? '' : 'title="Sin campañas o propiedades asignadas"' ?>>
            <?= $view->e($etiqueta) ?>
        </a>
    <?php endforeach; ?>
</nav>
