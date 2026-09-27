<?php
/**
 * Sincroniza las órdenes recientes de todas las tiendas Walmart activas.
 *
 * Uso:
 *   php app/cli/walmart_sync_orders.php           # todas las tiendas activas
 *   php app/cli/walmart_sync_orders.php --id=5     # solo tienda ID 5
 *   php app/cli/walmart_sync_orders.php --days=7   # ventana de días hacia atrás (default: 30)
 *
 * Cron sugerido: cada 2-4 horas. La ventana de días se re-sincroniza
 * completa cada vez (upsert por purchase_order_id), así que corridas
 * repetidas no duplican nada — solo actualizan el estado si cambió.
 */

require_once __DIR__ . '/bootstrap.php';

$onlyId = null;
$daysBack = 30;
foreach ($argv as $arg) {
    if (preg_match('/^--id=(\d+)$/', $arg, $m)) $onlyId = (int) $m[1];
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) $daysBack = (int) $m[1];
}

$db = new Database();
$connectionModel = new WalmartConnectionModel($db);
$syncService = new WalmartOrderSyncService($db);

$connections = $connectionModel->findActive();

foreach ($connections as $conn) {
    if ($onlyId && (int) $conn->id !== $onlyId) continue;

    echo "Sincronizando órdenes de '{$conn->store_name}' (id={$conn->id})...\n";
    $result = $syncService->syncStore((int) $conn->id, $daysBack);

    if ($result['ok']) {
        echo "  OK — {$result['synced']} órdenes sincronizadas, {$result['failed']} con error de parseo.\n";
    } else {
        echo "  ERROR — {$result['error']}\n";
    }
}

echo "Listo.\n";
