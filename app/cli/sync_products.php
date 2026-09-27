<?php
/**
 * Script de sincronización automática de productos ML.
 * Ejecutar cada 15 minutos desde cron de Hostinger.
 *
 * Uso:
 *   php app/cli/sync_products.php           # todas las tiendas activas
 *   php app/cli/sync_products.php --id=5    # solo tienda ID 5
 *
 * Cron Hostinger (hPanel → Avanzado → Cron Jobs):
 *   Tipo: PHP
 *   Comando: php /home/USUARIO/domains/TUDOMINIO.com/public_html/app/cli/sync_products.php
 *   Frecuencia: cada 15 minutos
 */

require_once __DIR__ . '/bootstrap.php';

// ── Parsear argumentos CLI ──
$onlyId = null;
foreach ($argv as $arg) {
    if (preg_match('/^--id=(\d+)$/', $arg, $m)) {
        $onlyId = (int) $m[1];
    }
}

// ── Conexión a DB ──
$db = new Database();

// ── Obtener tiendas a sincronizar ──
if ($onlyId) {
    $stmt = $db->query('SELECT id, ml_user_id, store_name, is_active FROM ml_connections WHERE id = ?');
    $stmt->execute([$onlyId]);
    $stores = $stmt->fetchAll();
    if (empty($stores)) {
        error_log("[SYNC-CRON] Tienda ID {$onlyId} no encontrada.");
        exit(1);
    }
} else {
    $stmt = $db->query('SELECT id, ml_user_id, store_name, is_active FROM ml_connections WHERE is_active = 1');
    $stmt->execute();
    $stores = $stmt->fetchAll();
}

if (empty($stores)) {
    error_log("[SYNC-CRON] No hay tiendas activas para sincronizar.");
    exit(0);
}

error_log("[SYNC-CRON] Iniciando sync para " . count($stores) . " tienda(s)...");

// ── Servicios ──
$authService = new MercadoLibreAuthService($db);
$syncService = new MlSyncService($db);
$activityService = new ActivityService($db);

$synced = 0;
$errors = 0;

foreach ($stores as $store) {
    $connId = (int) $store->id;
    $storeName = $store->store_name ?? "Tienda #{$connId}";

    error_log("[SYNC-CRON] {$storeName}: iniciando sync...");

    $result = $syncService->runFullSync($connId, true);

    if ($result['ok']) {
        $synced++;
        error_log("[SYNC-CRON] {$storeName}: OK — {$result['total']} productos en {$result['duration']}s");
        $activityService->log(null, 'sync_cron', "Sync automático: {$storeName} — {$result['total']} productos OK ({$result['duration']}s)");
    } elseif ($result['error'] === 'Sync en curso para esta tienda.') {
        error_log("[SYNC-CRON] {$storeName}: sync ya en curso, saltando.");
    } else {
        $errors++;
        error_log("[SYNC-CRON] {$storeName}: ERROR — {$result['error']}");
        $activityService->log(null, 'sync_cron', "Sync automático: {$storeName} — ERROR: {$result['error']}");
    }

    // Pausa entre tiendas para no saturar API de ML
    if (count($stores) > 1) {
        sleep(10);
    }
}

error_log("[SYNC-CRON] Finalizado: {$synced} exitosas, {$errors} errores.");
exit($errors > 0 ? 1 : 0);
