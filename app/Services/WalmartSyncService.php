<?php

/**
 * Sincronización del catálogo Walmart → walmart_products_cache.
 *
 * La paginación de la Items API es por cursor u offset (ver
 * WalmartApiService::getItemsPage). El cursor del sync en curso se persiste
 * en walmart_sync_status.next_cursor (no en $_SESSION), así el proceso
 * sobrevive a pérdida de sesión y funciona igual en CLI.
 *
 * next_cursor tiene tres estados posibles mientras el sync está en curso:
 *  - numérico (ej. "50")  → offset de la página de ítems pendiente.
 *  - "INV:<offset>"       → fase de enriquecimiento de inventario por SKU
 *                           (fallback cuando /v3/inventories no está
 *                           disponible para la cuenta), offset en la lista
 *                           de SKUs ya cacheados.
 *  - null                 → sync completo, listo para finishSync().
 *
 * La fase de inventario por SKU se trocea igual que la de ítems porque
 * hacerla completa en una sola request puede exceder el max_execution_time
 * de PHP en catálogos grandes sin acceso a la API paginada de inventario.
 *
 * Dos modos, mismo patrón que MlSyncService:
 *  - UI: startFullSync() + processChunk() por polling desde el navegador.
 *  - CLI: runFullSync() en una sola pasada (cron).
 */
class WalmartSyncService {

    private const PAGE_SIZE = 50;
    private const INVENTORY_CHUNK_SIZE = 25;

    private $db;
    private $api;
    private $cache;
    private $syncStatus;
    private $connection;

    public function __construct($db) {
        $this->db = $db;
        $this->api = new WalmartApiService($db);
        $this->cache = new WalmartProductsCacheModel($db);
        $this->syncStatus = new WalmartSyncStatusModel($db);
        $this->connection = new WalmartConnectionModel($db);
    }

    public function startFullSync($connectionId, $userId) {
        $conn = $this->connection->findById($connectionId);
        if (!$conn || !$conn->is_active) {
            return ['ok' => false, 'error' => 'La conexión no existe o está inactiva.'];
        }

        if (!$this->syncStatus->acquireLock($connectionId, $userId)) {
            return ['ok' => false, 'error' => 'Ya hay una sincronización en curso para esta tienda.'];
        }

        // Primera página: valida credenciales y obtiene el total
        $page = $this->api->getItemsPage($connectionId, self::PAGE_SIZE, null);
        if ($page['code'] !== 200) {
            $msg = 'Error al iniciar sync (HTTP ' . $page['code'] . ')';
            $this->syncStatus->setError($connectionId, $msg);
            return ['ok' => false, 'error' => 'Walmart respondió HTTP ' . $page['code'] . ' al listar items.'];
        }

        $this->syncStatus->setSyncing($connectionId, $page['total'], $userId, null);
        $processed = $this->processItems($connectionId, $page['items']);
        $this->syncStatus->updateProgress($connectionId, $processed, $page['nextCursor']);

        if ($page['nextCursor'] === null || $page['nextCursor'] === '') {
            $done = $this->transitionAfterItems($connectionId, $userId);
            return ['ok' => true, 'total' => $page['total'], 'done' => $done];
        }

        return ['ok' => true, 'total' => $page['total'], 'done' => false];
    }

    public function processChunk($connectionId, $userId = null) {
        $status = $this->syncStatus->get($connectionId);
        if ($status->status !== 'syncing') {
            return ['ok' => false, 'done' => true, 'error' => 'No hay sincronización en curso.'];
        }

        $cursor = $status->next_cursor;

        // Fase de inventario por SKU (fallback troceado)
        if ($cursor !== null && strpos($cursor, 'INV:') === 0) {
            $done = $this->processInventoryChunk($connectionId, $userId, (int) substr($cursor, 4));
            return ['ok' => true, 'done' => $done, 'processed' => (int) $status->synced_products, 'total' => (int) $status->total_products];
        }

        // Sin cursor pendiente: los ítems ya están, decidir inventario o terminar
        if ($cursor === null || $cursor === '') {
            $done = $this->transitionAfterItems($connectionId, $userId);
            return ['ok' => true, 'done' => $done, 'processed' => (int) $status->synced_products, 'total' => (int) $status->total_products];
        }

        // Fase de paginación de ítems
        $page = $this->api->getItemsPage($connectionId, self::PAGE_SIZE, $cursor);
        if ($page['code'] !== 200) {
            $msg = 'Error en chunk de sync (HTTP ' . $page['code'] . ')';
            $this->syncStatus->setError($connectionId, $msg);
            return ['ok' => false, 'done' => true, 'error' => 'Walmart respondió HTTP ' . $page['code'] . '.'];
        }

        $processed = (int) $status->synced_products + $this->processItems($connectionId, $page['items']);
        $this->syncStatus->updateProgress($connectionId, $processed, $page['nextCursor']);

        if ($page['nextCursor'] === null || $page['nextCursor'] === '') {
            $done = $this->transitionAfterItems($connectionId, $userId);
            return ['ok' => true, 'done' => $done, 'processed' => $processed, 'total' => (int) $status->total_products];
        }

        return ['ok' => true, 'done' => false, 'processed' => $processed, 'total' => (int) $status->total_products];
    }

    /**
     * Se llama justo después de terminar de paginar los ítems. Decide si el
     * inventario se puede enriquecer de una vez (API paginada disponible,
     * rápida) o si hay que entrar en la fase troceada por SKU.
     * Retorna true si el sync quedó completamente terminado en esta misma
     * llamada, false si quedó pendiente de más chunks (fase de inventario).
     */
    private function transitionAfterItems($connectionId, $userId) {
        $probe = $this->api->getInventoriesPage($connectionId, 1, null);

        if ($probe['code'] === 200) {
            // API paginada disponible: pocas requests, se hace completa aquí mismo.
            $this->enrichInventoryPaginated($connectionId);
            $this->finishSync($connectionId, $userId);
            return true;
        }

        if (in_array($probe['code'], [404, 405, 501], true)) {
            // Sin API paginada: trocear el enriquecimiento por SKU en varios chunks.
            $this->syncStatus->updateProgress($connectionId, (int) $this->cache->getCount($connectionId), 'INV:0');
            return false;
        }

        // Otro error (ej. red): no bloquear el sync por esto, solo se deja sin enriquecer.
        error_log("[WALMART-SYNC] No se pudo determinar API de inventario (HTTP {$probe['code']}) en conexión {$connectionId}; se omite enriquecimiento.");
        $this->finishSync($connectionId, $userId);
        return true;
    }

    /**
     * Procesa un lote de SKUs del fallback de inventario por SKU.
     * Retorna true si con este chunk se completó el inventario (y el sync).
     */
    private function processInventoryChunk($connectionId, $userId, $offset) {
        $skus = $this->cache->getSkusPage($connectionId, $offset, self::INVENTORY_CHUNK_SIZE);

        foreach ($skus as $sku) {
            $resp = $this->api->getInventory($connectionId, $sku);
            if ($resp['code'] === 200 && isset($resp['body']->quantity->amount)) {
                $this->cache->updateLocalStock($connectionId, $sku, (int) $resp['body']->quantity->amount);
            }
            usleep(150000);
        }

        $nextOffset = $offset + count($skus);
        $isLastChunk = count($skus) < self::INVENTORY_CHUNK_SIZE;

        if ($isLastChunk) {
            $this->syncStatus->updateProgress($connectionId, (int) $this->cache->getCount($connectionId), null);
            $this->finishSync($connectionId, $userId);
            return true;
        }

        $this->syncStatus->updateProgress($connectionId, (int) $this->cache->getCount($connectionId), 'INV:' . $nextOffset);
        return false;
    }

    /** Wrap-up final: notificaciones, activity log y status idle. Asume que el inventario ya se resolvió (o se decidió omitir). */
    public function finishSync($connectionId, $userId = null, $hadErrors = false) {
        $total = $this->cache->getCount($connectionId);
        $this->syncStatus->setIdle($connectionId, $total);

        $storeName = $this->connection->findStoreName($connectionId);
        if ($userId) {
            try {
                $notif = new NotificationService($this->db);
                $notif->notifySyncComplete($userId, null, $total, $total);

                $lowStock = $this->cache->getLowStock($connectionId, 5);
                if (!empty($lowStock)) {
                    $notif->createNotification(
                        $userId,
                        'low_stock',
                        'Stock bajo en Walmart — ' . $storeName,
                        count($lowStock) . ' productos con 5 o menos unidades'
                    );
                }
            } catch (Exception $e) {
                error_log('[WALMART-SYNC] Error creando notificaciones: ' . $e->getMessage());
            }

            try {
                (new ActivityService($this->db))->log(
                    $userId,
                    'walmart_sync',
                    "Sincronizó la tienda Walmart '{$storeName}' ({$total} productos)"
                );
            } catch (Exception $e) {}
        }

        try {
            (new WalmartExportService($this->db))->generateBoth($connectionId);
        } catch (Exception $e) {
            error_log('[WALMART-SYNC] Error generando exports automáticos: ' . $e->getMessage());
        }

        try {
            (new SkuFamilyService($this->db))->rebuildIndex();
        } catch (Exception $e) {
            error_log('[WALMART-SYNC] Error reconstruyendo índice de familias de SKU: ' . $e->getMessage());
        }

        return ['ok' => true, 'total' => $total];
    }

    /** Sync completo en una pasada (cron / CLI). */
    public function runFullSync($connectionId) {
        $start = $this->startFullSync($connectionId, 0);
        if (!$start['ok']) {
            return $start;
        }

        $maxChunks = 5000; // tope de seguridad contra cursores que no avanzan
        $done = !empty($start['done']);
        while (!$done) {
            usleep(300000); // 300 ms entre páginas para respetar la API
            $chunk = $this->processChunk($connectionId, 0);
            if (!$chunk['ok']) {
                return $chunk;
            }
            $done = !empty($chunk['done']);
            if (--$maxChunks <= 0) {
                $this->syncStatus->setError($connectionId, 'Sync abortado: demasiadas páginas.');
                return ['ok' => false, 'error' => 'Sync abortado: demasiadas páginas.'];
            }
        }

        $total = $this->cache->getCount($connectionId);
        return ['ok' => true, 'total' => $total, 'done' => true];
    }

    public function stopSync($connectionId) {
        $status = $this->syncStatus->get($connectionId);
        $this->syncStatus->setIdle($connectionId, (int) $status->total_products);
        return ['ok' => true];
    }

    public function getStatus($connectionId) {
        return $this->syncStatus->get($connectionId);
    }

    public function getAllStatuses() {
        return $this->syncStatus->getAll();
    }

    public function needsSync($connectionId, $maxAgeMinutes = 60) {
        return $this->syncStatus->needsSync($connectionId, $maxAgeMinutes);
    }

    private function processItems($connectionId, array $items) {
        $count = 0;
        foreach ($items as $item) {
            if ($this->cache->upsertFromWalmartItem($connectionId, $item) !== null) {
                $count++;
            }
        }
        return $count;
    }

    /** Recorre GET /v3/inventories (paginado) y vuelca available_quantity al cache. Rápido: pocas requests. */
    private function enrichInventoryPaginated($connectionId) {
        $updated = 0;
        $cursor = null;
        $maxPages = 500;

        do {
            $page = $this->api->getInventoriesPage($connectionId, 50, $cursor);
            if ($page['code'] !== 200) {
                error_log("[WALMART-SYNC] Inventories HTTP {$page['code']} en conexión {$connectionId}; stock no actualizado.");
                return $updated;
            }

            foreach ($page['inventories'] as $inv) {
                $sku = trim((string) ($inv->sku ?? ''));
                if ($sku === '') continue;
                $qty = (int) ($inv->quantity->amount ?? 0);
                $this->cache->updateLocalStock($connectionId, $sku, $qty);
                $updated++;
            }

            $cursor = $page['nextCursor'];
        } while ($cursor !== null && $cursor !== '' && --$maxPages > 0);

        return $updated;
    }
}
