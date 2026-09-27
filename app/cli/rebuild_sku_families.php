<?php
/**
 * Reconstruye el índice automático de familias de SKU (original -> individual
 * -> pack -> tripack) a partir de products_master, walmart_products_cache y
 * ml_products_cache. Las resoluciones manuales (source='manual') nunca se
 * sobrescriben.
 *
 * Uso:
 *   php app/cli/rebuild_sku_families.php
 *
 * Recomendado correrlo después de cada sync completo, o como cron cada
 * pocas horas (es liviano: solo lee SKUs distintos y aplica un patrón).
 */

require_once __DIR__ . '/bootstrap.php';

$db = new Database();
$service = new SkuFamilyService($db);

$result = $service->rebuildIndex();

echo "Familias de SKU reconstruidas.\n";
echo "  Total de SKUs revisados: {$result['total']}\n";
echo "  Resueltos automáticamente: {$result['resolved']}\n";
echo "  Sin resolver (requieren mapeo manual): {$result['unresolved']}\n";
