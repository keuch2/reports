<?php /** @var \MisterCo\Reports\Core\View $view */ ?>
<?php /** @var \MisterCo\Reports\Core\Session $session */ ?>
<?php /** @var string $content */ ?>
<?php /** @var string|null $titulo */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $view->e($session->csrfToken()) ?>">
    <title><?= $view->e(($titulo ?? '') . ($titulo ? ' · ' : '') . 'Mister Co. Reports') ?></title>
    <link rel="stylesheet" href="<?= $view->url('/css/app.css') ?>">
</head>
<body>
    <main class="app-main">
        <?= $content ?>
    </main>
</body>
</html>
