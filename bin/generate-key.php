<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$envFile = $basePath . '/.env';

$key = 'base64:' . base64_encode(random_bytes(32));

if (!is_file($envFile)) {
    fwrite(STDERR, "[!] No existe .env. Copialo desde .env.example primero.\n");
    exit(1);
}

$contents = (string) file_get_contents($envFile);

if (preg_match('/^APP_KEY=.*/m', $contents)) {
    $contents = (string) preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . $key, $contents);
} else {
    $contents .= "\nAPP_KEY=" . $key . "\n";
}

file_put_contents($envFile, $contents);
echo "[✓] APP_KEY generado y guardado en .env\n";
