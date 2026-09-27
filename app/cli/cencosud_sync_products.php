<?php
/**
 * Script de sincronización automática de productos Cencosud Chile.
 * Programado para ejecutarse en segundo plano periódicamente desde cron.
 *
 * Uso:
 *   php app/cli/cencosud_sync_products.php           # todas las tiendas activas
 *   php app/cli/cencosud_sync_products.php --id=5    # solo tienda ID 5
 *
 * Cron Hostinger (hPanel → Advanced → Cron Jobs):
 *   Tipo: PHP
 *   Comando: php /home/USUARIO/backend-software/app/cli/cencosud_sync_products.php
 *   Frecuencia: cada 10 minutos (Recomendado para evitar desfases de stock y respetar Rate Limits)
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
    $stmt = $db->query('SELECT id, store_name, is_active FROM cencosud_connections WHERE id = ?');
    $stmt->execute([$onlyId]);
    $stores = $stmt->fetchAll();
    if (empty($stores)) {
        error_log("[CENCOSUD-CRON] Tienda ID {$onlyId} no encontrada.");
        exit(1);
    }
} else {
    $stmt = $db->query('SELECT id, store_name, is_active FROM cencosud_connections WHERE is_active = 1');
    $stmt->execute();
    $stores = $stmt->fetchAll();
}

if (empty($stores)) {
    error_log("[CENCOSUD-CRON] No hay tiendas Cencosud activas para sincronizar.");
    exit(0);
}

error_log("[CENCOSUD-CRON] Iniciando sync para " . count($stores) . " tienda(s)...");

$syncService = new CencosudSyncService($db);
$activityService = new ActivityService($db);

$synced = 0;
$errors = 0;

foreach ($stores as $store) {
    $connId = (int) $store->id;
    $storeName = $store->store_name ?: "Tienda #{$connId}";
    $startedAt = time();

    error_log("[CENCOSUD-CRON] {$storeName}: iniciando sync...");

    $result = $syncService->runFullSync($connId);
    $duration = time() - $startedAt;

    if (!empty($result['ok'])) {
        $synced++;
        error_log("[CENCOSUD-CRON] {$storeName}: OK — {$result['total']} productos en {$duration}s");
        $activityService->log(null, 'cencosud_sync_cron', "Sync automático Cencosud: {$storeName} — {$result['total']} productos OK ({$duration}s)");
    } elseif (($result['error'] ?? '') === 'Ya hay una sincronización en curso para esta tienda.') {
        error_log("[CENCOSUD-CRON] {$storeName}: sync ya en curso, saltando.");
    } else {
        $errors++;
        $err = $result['error'] ?? 'desconocido';
        error_log("[CENCOSUD-CRON] {$storeName}: ERROR — {$err}");
        $activityService->log(null, 'cencosud_sync_cron', "Sync automático Cencosud: {$storeName} — ERROR: {$err}");
    }

    // Pausa entre tiendas para no sobrecargar los servidores de Cencosud
    if (count($stores) > 1) {
        sleep(10);
    }
}

error_log("[CENCOSUD-CRON] Finalizado: {$synced} exitosas, {$errors} errores.");
exit($errors > 0 ? 1 : 0);
