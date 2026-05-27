<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
?>
<div class="shell">
    <header class="shell__header">
        <div class="shell__brand">Mister Co. · Admin</div>
        <div class="shell__user">
            <span><?= $view->e($usuario->nombreCompleto) ?></span>
            <form method="POST" action="/logout" class="logout-form">
                <?= $view->csrfField() ?>
                <button type="submit" class="btn btn--link">Cerrar sesión</button>
            </form>
        </div>
    </header>

    <section class="shell__body">
        <h1>Panel administrativo</h1>
        <p class="muted">Bienvenido, <?= $view->e($usuario->nombreCompleto) ?>.</p>

        <div class="placeholder-grid">
            <article class="card">
                <h2>Conectar cuenta Meta</h2>
                <p>Vinculá el token de System User del Business Manager.</p>
                <p class="muted"><em>(Disponible en semanas 3-4)</em></p>
            </article>
            <article class="card">
                <h2>Importar datos</h2>
                <p>Disparar una importación on-demand desde Meta.</p>
                <p class="muted"><em>(Disponible en semanas 3-4)</em></p>
            </article>
            <article class="card">
                <h2>Clientes</h2>
                <p>Gestión de clientes y usuarios primarios.</p>
                <p class="muted"><em>(Disponible en semanas 3-4)</em></p>
            </article>
        </div>
    </section>
</div>
