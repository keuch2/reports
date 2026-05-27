<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
?>
<div class="shell">
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

    <section class="shell__body">
        <h1>Mi dashboard</h1>
        <p class="muted">Bienvenido, <?= $view->e($usuario->nombreCompleto) ?>.</p>

        <div class="empty-state">
            <p>Tu administrador aún no ha importado datos para tu cuenta.</p>
            <p class="muted">Una vez importados, verás aquí tus campañas, métricas y reportes.</p>
        </div>
    </section>
</div>
