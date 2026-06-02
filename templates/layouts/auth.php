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
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%231a2f6e'/%3E%3Ctext x='16' y='22' font-family='-apple-system,Helvetica,Arial,sans-serif' font-size='18' font-weight='700' fill='white' text-anchor='middle'%3EM%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="<?= $view->url('/css/app.css') ?>">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <?= $content ?>
    </main>
</body>
</html>
