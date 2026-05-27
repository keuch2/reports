<?php

declare(strict_types=1);

/**
 * Inserta datos de demo para validar el dashboard sin necesidad de token Meta real:
 * 1 cuenta publicitaria, 2 campañas, 4 adsets, 8 anuncios, 30 días de snapshots.
 * Asigna la cuenta al cliente demo.
 */

use Dotenv\Dotenv;
use MisterCo\Reports\Core\Database;

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';
Dotenv::createImmutable($basePath)->safeLoad();

$dbConfig = require $basePath . '/config/database.php';
$db = new Database($dbConfig);

echo "[*] Limpiando datos demo previos...\n";
$db->execute("DELETE FROM metricas_snapshots WHERE meta_entidad_id LIKE 'demo_%'");
$db->execute("DELETE FROM anuncios WHERE meta_ad_id LIKE 'demo_%'");
$db->execute("DELETE FROM conjuntos_anuncios WHERE meta_adset_id LIKE 'demo_%'");
$db->execute("DELETE FROM campanias WHERE meta_campaign_id LIKE 'demo_%'");
$db->execute("DELETE FROM cuentas_publicitarias WHERE meta_account_id = 'demo_act_001'");

echo "[*] Creando cuenta publicitaria...\n";
$db->execute(
    "INSERT INTO cuentas_publicitarias (meta_account_id, nombre, business_manager_id, estado, moneda, zona_horaria, accesible_con_token, ultima_sincronizacion_en)
     VALUES ('demo_act_001', 'Cuenta Demo Cliente Piloto', '10152846879291613', '1', 'PYG', 'America/Asuncion', 1, NOW())"
);
$cuentaId = $db->lastInsertId();

echo "[*] Creando 2 campañas...\n";
$campanias = [];
foreach ([
    ['demo_c_001', 'Black Friday 2026', 'OUTCOME_SALES', 'ACTIVE'],
    ['demo_c_002', 'Lanzamiento Producto X', 'OUTCOME_AWARENESS', 'PAUSED'],
] as $i => [$mid, $nombre, $obj, $est]) {
    $db->execute(
        'INSERT INTO campanias (meta_campaign_id, cuenta_publicitaria_id, nombre, objetivo, estado, fecha_inicio, presupuesto_diario)
              VALUES (:m, :c, :n, :o, :e, :f, :p)',
        ['m' => $mid, 'c' => $cuentaId, 'n' => $nombre, 'o' => $obj, 'e' => $est,
         'f' => date('Y-m-d', strtotime('-45 days')), 'p' => 150000.00 + $i * 50000]
    );
    $campanias[$mid] = $db->lastInsertId();
}

echo "[*] Creando 4 adsets...\n";
$adsets = [];
$idxAdset = 1;
foreach ($campanias as $mid => $cid) {
    foreach ([0, 1] as $j) {
        $adsetMid = "demo_as_{$idxAdset}";
        $db->execute(
            'INSERT INTO conjuntos_anuncios (meta_adset_id, campania_id, nombre, estado, presupuesto_diario, optimization_goal)
                  VALUES (:m, :c, :n, :e, :p, :o)',
            ['m' => $adsetMid, 'c' => $cid, 'n' => "Adset {$idxAdset}", 'e' => 'ACTIVE', 'p' => 75000, 'o' => 'OFFSITE_CONVERSIONS']
        );
        $adsets[$adsetMid] = $db->lastInsertId();
        $idxAdset++;
    }
}

echo "[*] Creando 8 anuncios...\n";
$anuncios = [];
$idxAd = 1;
foreach ($adsets as $mid => $aid) {
    foreach ([0, 1] as $k) {
        $adMid = "demo_ad_{$idxAd}";
        $db->execute(
            'INSERT INTO anuncios (meta_ad_id, conjunto_anuncios_id, nombre, tipo, estado)
                  VALUES (:m, :a, :n, :t, :e)',
            ['m' => $adMid, 'a' => $aid, 'n' => "Creative {$idxAd}", 't' => 'image', 'e' => 'ACTIVE']
        );
        $anuncios[$adMid] = $db->lastInsertId();
        $idxAd++;
    }
}

echo "[*] Sembrando 30 días de snapshots para 8 anuncios (240 filas)...\n";
mt_srand(42); // determinístico
for ($d = 30; $d >= 0; $d--) {
    $fecha = date('Y-m-d', strtotime("-{$d} days"));
    foreach ($anuncios as $metaAdId => $adId) {
        $impresiones = mt_rand(800, 12000);
        $clicks = (int) ($impresiones * (mt_rand(80, 350) / 10000));
        $clicksEnlace = (int) ($clicks * (mt_rand(60, 90) / 100));
        $gasto = round($impresiones * (mt_rand(40, 180) / 100), 2);
        $alcance = (int) ($impresiones * (mt_rand(55, 85) / 100));
        $ctr = $impresiones > 0 ? round($clicks / $impresiones * 100, 4) : null;
        $cpc = $clicks > 0 ? round($gasto / $clicks, 4) : null;
        $cpm = $impresiones > 0 ? round($gasto / $impresiones * 1000, 4) : null;
        $resultados = mt_rand(0, max(1, (int) ($clicks / 8)));

        $db->execute(
            'INSERT INTO metricas_snapshots
                (nivel, entidad_id, meta_entidad_id, fecha, gasto, impresiones, alcance, frecuencia,
                 clicks_totales, clicks_enlace, ctr, cpc, cpm, costo_por_resultado, resultados, conversiones)
             VALUES (\'ad\', :eid, :mid, :f, :g, :imp, :alc, :fr, :ct, :ce, :ctr, :cpc, :cpm, :cpr, :res, :con)
             ON DUPLICATE KEY UPDATE gasto=VALUES(gasto)',
            [
                'eid' => $adId, 'mid' => $metaAdId, 'f' => $fecha, 'g' => $gasto,
                'imp' => $impresiones, 'alc' => $alcance,
                'fr' => $alcance > 0 ? round($impresiones / $alcance, 4) : null,
                'ct' => $clicks, 'ce' => $clicksEnlace,
                'ctr' => $ctr, 'cpc' => $cpc, 'cpm' => $cpm,
                'cpr' => $resultados > 0 ? round($gasto / $resultados, 4) : null,
                'res' => $resultados, 'con' => $resultados,
            ]
        );
    }
}

echo "[*] Asignando la cuenta al cliente demo...\n";
$cli = $db->selectOne("SELECT id FROM clientes WHERE nombre_comercial = 'Cliente Piloto S.A.'");
$adminUid = (int) ($db->selectOne("SELECT id FROM usuarios WHERE rol = 'admin' LIMIT 1")['id'] ?? 0);
if ($cli !== null) {
    $db->execute(
        'INSERT IGNORE INTO permisos_cliente_cuenta (cliente_id, cuenta_publicitaria_id, otorgado_por_usuario_id)
              VALUES (:c, :a, :u)',
        ['c' => (int) $cli['id'], 'a' => $cuentaId, 'u' => $adminUid]
    );
    echo "[✓] Cuenta asignada al cliente {$cli['id']}.\n";
}

echo "[✓] Seeds demo Meta completados. Logueate como cliente@piloto.test / cliente1234\n";
