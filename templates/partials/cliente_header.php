<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
?>
<header class="shell__header">
    <div class="shell__brand">Mister Co. · Reportes</div>
    <div class="shell__user">
        <span><?= $view->e($usuario->nombreCompleto) ?></span>
        <form method="POST" action="/logout" class="logout-form">
            <?= $view->csrfField() ?>
            <button type="submit" class="btn btn--link">Cerrar sesión</button>
        </form>
    </div>
</header>
