<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
/** @var string $seccion */
?>
<header class="shell__header">
    <div class="shell__brand">Mister Co. · Admin</div>
    <nav class="shell__nav">
        <a href="/admin" class="<?= $seccion === 'home' ? 'active' : '' ?>">Inicio</a>
        <a href="/admin/meta" class="<?= $seccion === 'meta' ? 'active' : '' ?>">Cuenta Meta</a>
        <a href="/admin/importar" class="<?= $seccion === 'importar' ? 'active' : '' ?>">Importar</a>
        <a href="/admin/clientes" class="<?= $seccion === 'clientes' ? 'active' : '' ?>">Clientes</a>
        <a href="/admin/plantillas" class="<?= $seccion === 'plantillas' ? 'active' : '' ?>">Plantillas</a>
    </nav>
    <div class="shell__user">
        <span><?= $view->e($usuario->nombreCompleto) ?></span>
        <form method="POST" action="/logout" class="logout-form">
            <?= $view->csrfField() ?>
            <button type="submit" class="btn btn--link">Cerrar sesión</button>
        </form>
    </div>
</header>
