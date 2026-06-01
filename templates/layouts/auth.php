<?php /** @var \MisterCo\Reports\Core\View $view */ ?>
<?php /** @var \MisterCo\Reports\Core\Session $session */ ?>
<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $view->e($session->csrfToken()) ?>">
    <title>Acceso · Mister Co. Reports</title>
    <link rel="stylesheet" href="<?= $view->url('/css/app.css') ?>">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <?= $content ?>
    </main>
</body>
</html>
