<?php
/**
 * Encabezado de las pantallas por cliente (Google, comparativo): header del cliente, o
 * header admin + banner de vista previa cuando las ve el admin.
 *
 * @var \MisterCo\Reports\Core\View $view
 * @var \MisterCo\Reports\Domain\Usuario $usuario
 * @var array<string,mixed> $cliente
 * @var bool $es_admin
 * @var string $seccion Sección activa del menú del cliente.
 * @var string $descripcion Texto del banner de preview.
 * @var array{url:string, texto:string}|null $volver Destino del botón del banner (por defecto, la ficha del cliente).
 */

$volver = $volver ?? ['url' => $view->url('/admin/clientes/' . ((int) $cliente['id'])), 'texto' => 'Volver al cliente'];
?>
<?php if ($es_admin): ?>
    <?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>
    <div class="preview-banner">
        <span>👁️ <?= $view->e($descripcion) ?> de <strong><?= $view->e((string) $cliente['nombre_comercial']) ?></strong>
            — exactamente lo que ve el cliente.</span>
        <a href="<?= $view->e($volver['url']) ?>" class="btn btn--link"><?= $view->e($volver['texto']) ?></a>
    </div>
<?php else: ?>
    <?= $view->renderPartial('partials/cliente_header', ['usuario' => $usuario, 'seccion' => $seccion]) ?>
<?php endif; ?>
