<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var string $seccion */
?>
<header class="shell__header">
    <div class="shell__brand">Mister Co. · Admin</div>
    <nav class="shell__nav">
        <a href="<?= $view->url('/admin') ?>" class="<?= $seccion === 'home' ? 'active' : '' ?>">Inicio</a>
        <a href="<?= $view->url('/admin/meta') ?>" class="<?= $seccion === 'meta' ? 'active' : '' ?>">Cuenta Meta</a>
        <a href="<?= $view->url('/admin/importar') ?>" class="<?= $seccion === 'importar' ? 'active' : '' ?>">Importar</a>
        <a href="<?= $view->url('/admin/clientes') ?>" class="<?= $seccion === 'clientes' ? 'active' : '' ?>">Clientes</a>
        <a href="<?= $view->url('/admin/plantillas') ?>" class="<?= $seccion === 'plantillas' ? 'active' : '' ?>">Plantillas</a>
        <a href="<?= $view->url('/admin/auditoria') ?>" class="<?= $seccion === 'auditoria' ? 'active' : '' ?>">Auditoría</a>
        <a href="<?= $view->url('/admin/2fa') ?>" class="<?= $seccion === '2fa' ? 'active' : '' ?>">2FA</a>
    </nav>
    <div class="shell__user">
        <span><?= $view->e($usuario->nombreCompleto) ?></span>
        <form method="POST" action="<?= $view->url('/logout') ?>" class="logout-form">
            <?= $view->csrfField() ?>
            <button type="submit" class="btn btn--link">Cerrar sesión</button>
        </form>
    </div>
</header>
