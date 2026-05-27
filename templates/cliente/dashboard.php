<?php // legacy: el dashboard real vive en cliente/dashboard_meta.php; este archivo se mantiene por compatibilidad con HomeController::cliente() ?>
<?= $view->renderPartial('cliente/sin_datos', ['usuario' => $usuario]) ?>
