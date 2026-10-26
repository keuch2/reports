<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var array{meta: bool, google_ads: bool, analytics: bool, redes: bool}|null $fuentes_cliente Compartido por ClienteMiddleware. */
/** @var string|null $seccion */

$fuentes = $fuentes_cliente ?? ['meta' => true, 'google_ads' => false, 'analytics' => false, 'redes' => false];
$seccion = $seccion ?? 'meta';
?>
<header class="shell__header">
    <div class="shell__brand">Mister Co. · Reportes</div>
    <nav class="shell__nav">
        <?php if ($fuentes['meta']): ?>
            <a href="<?= $view->url('/cliente') ?>" class="<?= $seccion === 'meta' ? 'active' : '' ?>">Meta Ads</a>
        <?php endif; ?>
        <?php if ($fuentes['google_ads']): ?>
            <a href="<?= $view->url('/cliente/google-ads') ?>" class="<?= $seccion === 'google_ads' ? 'active' : '' ?>">Google Ads</a>
        <?php endif; ?>
        <?php if ($fuentes['analytics']): ?>
            <a href="<?= $view->url('/cliente/analytics') ?>" class="<?= $seccion === 'analytics' ? 'active' : '' ?>">Google Analytics</a>
        <?php endif; ?>
        <?php if ($fuentes['redes']): ?>
            <a href="<?= $view->url('/cliente/redes') ?>" class="<?= $seccion === 'redes' ? 'active' : '' ?>">Facebook e Instagram</a>
        <?php endif; ?>
        <?php if ($fuentes['meta']): ?>
            <a href="<?= $view->url('/cliente/preferencias') ?>" class="<?= $seccion === 'preferencias' ? 'active' : '' ?>">Preferencias</a>
        <?php endif; ?>
    </nav>
    <div class="shell__user">
        <a href="<?= $view->url('/mi-perfil') ?>" class="shell__user-link">
            <?= $view->e($usuario->nombreCompleto) ?>
        </a>
        <form method="POST" action="<?= $view->url('/logout') ?>" class="logout-form">
            <?= $view->csrfField() ?>
            <button type="submit" class="btn btn--link">Cerrar sesión</button>
        </form>
    </div>
</header>
