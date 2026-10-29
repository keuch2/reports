<?php
/**
 * Pestañas de la sección Importar: una página por fuente de datos.
 *
 * @var \MisterCo\Reports\Core\View $view
 * @var string $activa 'meta' | 'redes' | 'google'
 */

$pestanas = [
    'meta' => ['Meta Ads', '/admin/importar'],
    'redes' => ['Facebook e Instagram', '/admin/importar/redes'],
    'google' => ['Google Ads y Analytics', '/admin/importar/google'],
];
?>
<h1>Importar datos</h1>
<nav class="tabs" aria-label="Fuente de datos">
    <?php foreach ($pestanas as $clave => [$etiqueta, $url]): ?>
        <a href="<?= $view->url($url) ?>" class="tabs__item<?= $clave === $activa ? ' tabs__item--activa' : '' ?>"
           <?= $clave === $activa ? 'aria-current="page"' : '' ?>><?= $view->e($etiqueta) ?></a>
    <?php endforeach; ?>
</nav>
