<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use MisterCo\Reports\Core\Database;

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';

Dotenv::createImmutable($basePath)->safeLoad();

$dbConfig = require $basePath . '/config/database.php';

// Asegurar que la base de datos exista (crearla si no).
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['charset']),
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec(sprintf(
        "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        $dbConfig['database']
    ));
    echo "[*] Base de datos `{$dbConfig['database']}` lista.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "[!] No se pudo conectar/crear la base: " . $e->getMessage() . "\n");
    exit(1);
}

$db = new Database($dbConfig);

// Tabla de control de migraciones.
$db->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS migraciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL UNIQUE,
    aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

$applied = array_column($db->select('SELECT nombre FROM migraciones'), 'nombre');
$migrationsDir = $basePath . '/database/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files);

$pending = array_filter($files, fn (string $f): bool => !in_array(basename($f), $applied, true));

if ($pending === []) {
    echo "[✓] No hay migraciones pendientes.\n";
    exit(0);
}

foreach ($pending as $file) {
    $name = basename($file);
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "[!] No se pudo leer {$name}\n");
        exit(1);
    }

    echo "[→] Aplicando {$name}... ";
    try {
        // MySQL hace implicit commit en DDL, así que no usamos transacción.
        $db->pdo()->exec($sql);
        $db->execute('INSERT INTO migraciones (nombre) VALUES (:nombre)', ['nombre' => $name]);
        echo "OK\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "FALLÓ\n[!] {$e->getMessage()}\n");
        exit(1);
    }
}

echo "[✓] Todas las migraciones aplicadas.\n";
