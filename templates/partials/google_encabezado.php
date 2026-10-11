<?php
/**
 * Encabezado de las pantallas de Google Ads / Analytics: header del cliente, o
 * header admin + banner de vista previa cuando las ve el admin.
 *
 * @var \MisterCo\Reports\Core\View $view
 * @var \MisterCo\Reports\Domain\Usuario $usuario
 * @var array<string,mixed> $cliente
 * @var bool $es_admin
 * @var string $seccion 'google_ads' | 'analytics'
 * @var string $descripcion Texto del banner de preview.
 */
?>
<?php if ($es_admin): ?>
    <?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>
    <div class="preview-banner">
        <span>👁️ <?= $view->e($descripcion) ?> de <strong><?= $view->e((string) $cliente['nombre_comercial']) ?></strong>
            — exactamente lo que ve el cliente.</span>
        <a href="<?= $view->url('/admin/clientes/' . ((int) $cliente['id'])) ?>" class="btn btn--link">Volver al cliente</a>
    </div>
<?php else: ?>
    <?= $view->renderPartial('partials/cliente_header', ['usuario' => $usuario, 'seccion' => $seccion]) ?>
<?php endif; ?>
