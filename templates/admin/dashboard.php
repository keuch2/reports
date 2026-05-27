<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'home']) ?>

<section class="shell__body">
    <h1>Panel administrativo</h1>
    <p class="muted">Bienvenido, <?= $view->e($usuario->nombreCompleto) ?>.</p>

    <div class="placeholder-grid">
        <article class="card">
            <h2><a href="/admin/meta">Conectar cuenta Meta</a></h2>
            <p>Vinculá el token de System User del Business Manager y sincronizá las cuentas publicitarias disponibles.</p>
        </article>
        <article class="card">
            <h2><a href="/admin/importar">Importar datos</a></h2>
            <p>Disparar una importación on-demand desde Meta para una cuenta y rango de fechas.</p>
        </article>
        <article class="card">
            <h2><a href="/admin/clientes">Clientes</a></h2>
            <p>Gestión de clientes, usuarios primarios y asignación de cuentas publicitarias.</p>
        </article>
    </div>
</section>
