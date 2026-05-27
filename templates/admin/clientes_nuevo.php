<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var \MisterCo\Reports\Domain\Usuario $usuario */
?>
<?= $view->renderPartial('partials/admin_header', ['usuario' => $usuario, 'seccion' => 'clientes']) ?>

<section class="shell__body">
    <h1>Nuevo cliente</h1>
    <article class="card">
        <form method="POST" action="/admin/clientes" class="form-stack">
            <?= $view->csrfField() ?>

            <fieldset class="fieldset">
                <legend>Datos del cliente</legend>
                <label class="field">
                    <span class="field__label">Nombre comercial *</span>
                    <input class="field__input" type="text" name="nombre_comercial" required>
                </label>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Correo de contacto</span>
                        <input class="field__input" type="email" name="correo_contacto">
                    </label>
                    <label class="field">
                        <span class="field__label">Teléfono</span>
                        <input class="field__input" type="text" name="telefono">
                    </label>
                </div>
                <label class="field">
                    <span class="field__label">Contacto principal</span>
                    <input class="field__input" type="text" name="contacto_principal">
                </label>
            </fieldset>

            <fieldset class="fieldset">
                <legend>Usuario primario del cliente</legend>
                <label class="field">
                    <span class="field__label">Nombre completo *</span>
                    <input class="field__input" type="text" name="usuario_nombre" required>
                </label>
                <label class="field">
                    <span class="field__label">Correo (login) *</span>
                    <input class="field__input" type="email" name="usuario_correo" required>
                </label>
                <label class="field">
                    <span class="field__label">Contraseña inicial * (mín. 8 caracteres)</span>
                    <input class="field__input" type="text" name="usuario_password" required minlength="8">
                </label>
                <p class="muted">Compartila por canal seguro. El cliente podrá cambiarla luego.</p>
            </fieldset>

            <div class="form-actions">
                <a href="/admin/clientes" class="btn btn--link">Cancelar</a>
                <button type="submit" class="btn btn--primary">Crear cliente</button>
            </div>
        </form>
    </article>
</section>
