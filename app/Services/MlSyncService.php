<?php

class MlSyncService {
    private $db;
    private $api;
    private $cache;
    private $syncStatus;
    private $central;

    public function __construct($db) {
        $this->db = $db;
        $this->api = new MercadoLibreApiService($db);
        $this->cache = new MlProductsCacheModel($db);
        $this->syncStatus = new MlSyncStatusModel($db);
        $this->central = new ProductsCentralModel($db);
    }

    public function startFullSync($connectionId, $userId) {
        $store = $this->getStore($connectionId);
        if (!$store) return ['ok' => false, 'error' => 'Tienda no encontrada.'];

        $token = $this->getToken($store, $userId);
        if (!$token) return ['ok' => false, 'error' => 'Token no disponible.'];

        if (!$this->syncStatus->acquireLock($connectionId, $userId)) {
            return ['ok' => false, 'error' => 'Ya hay un sync en curso para esta tienda.'];
        }

        error_log("[SYNC] Starting full sync for connection $connectionId (user $userId)");

        $allIds = $this->api->scanAllItems($store->ml_user_id, $token, 100);
        if (empty($allIds)) {
            $this->syncStatus->releaseLock($connectionId);
            return ['ok' => false, 'error' => 'No se encontraron productos en ML.'];
        }

        $this->syncStatus->setSyncing($connectionId, count($allIds), $userId);

        $_SESSION['sync_items_' . $connectionId] = $allIds;
        $_SESSION['sync_processed_' . $connectionId] = 0;

        return [
            'ok' => true,
            'total' => count($allIds),
            'message' => 'Sync iniciado. Procesa los chunks con syncChunk.',
        ];
    }

    public function processChunk($connectionId, $token, $chunkSize = 20) {
        $itemsKey = 'sync_items_' . $connectionId;
        $processedKey = 'sync_processed_' . $connectionId;

        $allIds = $_SESSION[$itemsKey] ?? [];
        $processed = $_SESSION[$processedKey] ?? 0;

        if ($processed >= count($allIds)) {
            return ['done' => true, 'processed' => $processed, 'total' => count($allIds)];
        }

        $chunk = array_slice($allIds, $processed, $chunkSize);
        if (empty($chunk)) {
            return ['done' => true, 'processed' => $processed, 'total' => count($allIds)];
        }

        $detail = $this->api->getItemDetails($chunk, $token);
        $results = [];
        if ($detail['code'] === 200 && is_array($detail['body'])) {
            foreach ($detail['body'] as $entry) {
                $obj = $entry->body ?? $entry;
                if (!isset($obj->id)) continue;
                $this->cache->upsertFromMlItem($connectionId, $obj);
                $sku = MlApiHelper::extractSku($obj);
                $results[] = ['id' => $obj->id, 'sku' => $sku ?: '-', 'title' => $obj->title ?? ''];
            }
        }

        $processed += count($chunk);
        $_SESSION[$processedKey] = $processed;
        $this->syncStatus->updateProgress($connectionId, $processed);

        return [
            'done' => $processed >= count($allIds),
            'processed' => $processed,
            'total' => count($allIds),
            'results' => $results,
        ];
    }

    public function finishSync($connectionId, $userId = null, $hadErrors = false) {
        $count = $this->cache->getCount($connectionId);
        $this->syncStatus->setIdle($connectionId, $count);

        $this->cache->resolveCategoryNames($connectionId);
        $this->central->mirrorFromMlCache($connectionId);

        $store = $this->getStore($connectionId);
        $storeName = $store ? ($store->store_name ?? 'ID ' . $connectionId) : 'ID ' . $connectionId;

        // ── Activity log ──
        if ($userId) {
            $desc = $hadErrors
                ? "Sincronización de {$storeName}: {$count} productos (con errores)"
                : "Sincronización de {$storeName}: {$count} productos";
            try {
                $activity = new ActivityService($this->db);
                $activity->log($userId, 'sync_completed', $desc);
            } catch (\Exception $e) {
                error_log("[SYNC] Activity log error: " . $e->getMessage());
            }
        }

        // ── Low stock check ──
        $this->checkLowStock($connectionId, $userId);

        unset($_SESSION['sync_items_' . $connectionId]);
        unset($_SESSION['sync_processed_' . $connectionId]);

        error_log("[SYNC] Finished sync for connection $connectionId — $count products cached");
        return ['ok' => true, 'total_products' => $count];
    }

    private function checkLowStock($connectionId, $userId = null) {
        if (!$userId) return;
        try {
            $rows = $this->db->query(
                'SELECT p.id, p.sku, p.title, p.available_quantity, p.ml_connection_id
                 FROM ml_products_cache p
                 WHERE p.ml_connection_id = ? AND p.status = ? AND p.available_quantity >= 0 AND p.available_quantity <= 5
                 ORDER BY p.available_quantity ASC
                 LIMIT 50'
            );
            $rows->execute([$connectionId, 'active']);
            $notif = new NotificationService($this->db);
            foreach ($rows as $r) {
                $notif->checkAndNotifyLowStock(
                    $userId, null, $r->sku ?? 'S/N',
                    $r->title ?? '', (int) $r->available_quantity, (int) $r->ml_connection_id
                );
            }
        } catch (\Exception $e) {
            error_log("[SYNC] Low stock check error: " . $e->getMessage());
        }
    }

    public function runFullSync($connectionId, $skipCategoryResolve = false) {
        $startTime = microtime(true);

        $store = $this->getStore($connectionId);
        if (!$store) return ['ok' => false, 'error' => 'Tienda no encontrada.'];

        $authService = new MercadoLibreAuthService($this->db);
        $token = $authService->getTokenForStore((int) $connectionId, 0);
        if (!$token) return ['ok' => false, 'error' => 'Token no disponible.'];

        if (!$this->syncStatus->acquireLock($connectionId, 0)) {
            return ['ok' => false, 'error' => 'Sync en curso para esta tienda.'];
        }

        error_log("[SYNC-RUN] Tienda {$connectionId}: escaneando IDs...");

        $allIds = $this->api->scanAllItems($store->ml_user_id, $token, 100);
        if (empty($allIds)) {
            $this->syncStatus->setError($connectionId, 'No se encontraron productos en ML.');
            return ['ok' => false, 'error' => 'No se encontraron productos en ML.'];
        }

        $totalIds = count($allIds);
        $this->syncStatus->setSyncing($connectionId, $totalIds);

        error_log("[SYNC-RUN] Tienda {$connectionId}: {$totalIds} IDs encontrados, procesando...");

        $processed = 0;
        foreach (array_chunk($allIds, 20) as $chunk) {
            $detail = $this->api->getItemDetails($chunk, $token);
            if ($detail['code'] === 200 && is_array($detail['body'])) {
                foreach ($detail['body'] as $entry) {
                    $obj = $entry->body ?? $entry;
                    if (isset($obj->id)) {
                        $this->cache->upsertFromMlItem($connectionId, $obj);
                    }
                }
            }
            $processed += count($chunk);
            $this->syncStatus->updateProgress($connectionId, $processed);
        }

        $count = $this->cache->getCount($connectionId);
        $this->syncStatus->setIdle($connectionId, $count);

        if (!$skipCategoryResolve) {
            $this->cache->resolveCategoryNames($connectionId);
        }
        $this->central->mirrorFromMlCache($connectionId);

        $duration = round(microtime(true) - $startTime, 1);
        error_log("[SYNC-RUN] Tienda {$connectionId}: OK — {$count} productos en {$duration}s");

        return ['ok' => true, 'total' => $count, 'duration' => $duration];
    }

    public function getStatus($connectionId) {
        return $this->syncStatus->get($connectionId);
    }

    public function getAllStatus() {
        return $this->syncStatus->getAll();
    }

    public function needsSync($connectionId, $maxAgeMinutes = 30) {
        return $this->syncStatus->needsSync($connectionId, $maxAgeMinutes);
    }

    private function getStore($connectionId) {
        $db = $this->db;
        $stmt = $db->query('SELECT id, ml_user_id, store_name, is_active FROM ml_connections WHERE id = ?');
        $stmt->execute([$connectionId]);
        return $stmt->fetch();
    }

    private function getToken($store, $userId) {
        $authService = new MercadoLibreAuthService($this->db);
        return $authService->getTokenForStore((int) $store->id, $userId);
    }
}
