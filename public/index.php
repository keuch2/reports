<?php

declare(strict_types=1);

use MisterCo\Reports\Core\Application;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = Application::bootstrap(dirname(__DIR__));
$app->run();
