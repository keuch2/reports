<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
?>
<?= $view->renderPartial('partials/cliente_header', ['usuario' => $usuario]) ?>

<section class="shell__body">
    <h1>Bienvenido, <?= $view->e($usuario->nombreCompleto) ?></h1>
    <div class="empty-state">
        <p>Tu administrador aún no ha asignado cuentas publicitarias a tu perfil.</p>
        <p class="muted">Una vez asignadas e importadas, verás aquí tus campañas, métricas y reportes.</p>
    </div>
</section>
