<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use MisterCo\Reports\Core\Database;

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';

Dotenv::createImmutable($basePath)->safeLoad();

$dbConfig = require $basePath . '/config/database.php';
$db = new Database($dbConfig);

$hashPassword = static fn (string $password): string => password_hash(
    $password,
    PASSWORD_ARGON2ID,
    ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]
);

// 1. Seed catálogo de métricas (si no está poblado).
$existentes = (int) ($db->selectOne('SELECT COUNT(*) AS n FROM catalogo_metricas')['n'] ?? 0);
if ($existentes === 0) {
    $sql = (string) file_get_contents($basePath . '/database/seeds/0001_catalogo_metricas.sql');
    $db->pdo()->exec($sql);
    echo "[✓] Catálogo de métricas poblado.\n";
} else {
    echo "[=] Catálogo de métricas ya tiene {$existentes} filas, skip.\n";
}

// 2. Crear admin inicial si no existe.
$admin = $db->selectOne('SELECT id FROM usuarios WHERE rol = :r LIMIT 1', ['r' => 'admin']);
if ($admin === null) {
    $db->execute(
        'INSERT INTO usuarios (correo, password_hash, nombre_completo, rol) VALUES (:c, :h, :n, :r)',
        ['c' => 'admin@misterco.test', 'h' => $hashPassword('admin1234'), 'n' => 'Admin Mister Co.', 'r' => 'admin']
    );
    echo "[✓] Admin creado: admin@misterco.test / admin1234\n";
} else {
    echo "[=] Admin ya existe, skip.\n";
}

// 3. Crear cliente demo + su usuario.
$cliente = $db->selectOne('SELECT id FROM clientes LIMIT 1');
if ($cliente === null) {
    $db->execute(
        'INSERT INTO clientes (nombre_comercial, correo_contacto) VALUES (:n, :c)',
        ['n' => 'Cliente Piloto S.A.', 'c' => 'piloto@example.com']
    );
    $clienteId = $db->lastInsertId();
    $db->execute(
        'INSERT INTO usuarios (correo, password_hash, nombre_completo, rol, cliente_id) VALUES (:c, :h, :n, :r, :cid)',
        ['c' => 'cliente@piloto.test', 'h' => $hashPassword('cliente1234'), 'n' => 'Usuario Piloto', 'r' => 'cliente', 'cid' => $clienteId]
    );
    echo "[✓] Cliente demo creado: cliente@piloto.test / cliente1234\n";
} else {
    echo "[=] Cliente demo ya existe, skip.\n";
}

echo "[✓] Seeds completados.\n";
