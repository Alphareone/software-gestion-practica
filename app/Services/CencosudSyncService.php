<?php

/**
 * Servicio de Sincronización del catálogo Cencosud → cencosud_products_cache.
 *
 * Soporta paginación por cursor u offset. El cursor del progreso se almacena en la BD
 * (cencosud_sync_status) para que el proceso sea reanudable (por si se cae la sesión
 * o si se ejecuta en segundo plano por CLI).
 *
 * Posee dos fases:
 *  1. Descarga de productos y guardado en caché local.
 *  2. Enriquecimiento del stock (inventarios), ya sea consultando de forma masiva
 *     (si la API lo permite) o uno por uno (fallback seguro por chunks de SKUs).
 */
class CencosudSyncService {

    private const PAGE_SIZE = 50;
    private const INVENTORY_CHUNK_SIZE = 25;

    private $db;
    private $api;
    private $cache;
    private $syncStatus;
    private $connection;

    public function __construct($db) {
        $this->db = $db;
        $this->api = new CencosudApiService($db);
        $this->cache = new CencosudProductsCacheModel($db);
        $this->syncStatus = new CencosudSyncStatusModel($db);
        $this->connection = new CencosudConnectionModel($db);
    }

    /**
     * Inicia una sincronización completa para la tienda.
     * Retorna ['ok' => bool, 'total' => int, 'done' => bool].
     */
    public function startFullSync($connectionId, $userId) {
        $conn = $this->connection->findById($connectionId);
        if (!$conn || !$conn->is_active) {
            return ['ok' => false, 'error' => 'La conexión no existe o está inactiva.'];
        }

        // Intenta adquirir el bloqueo exclusivo (evita ejecuciones simultáneas)
        if (!$this->syncStatus->acquireLock($connectionId, $userId)) {
            return ['ok' => false, 'error' => 'Ya hay una sincronización en curso para esta tienda.'];
        }

        // Obtener la primera página de productos de la API
        $page = $this->api->getItemsPage($connectionId, self::PAGE_SIZE, null);
        if ($page['code'] !== 200) {
            $this->syncStatus->setError($connectionId, 'Error al iniciar sync (HTTP ' . $page['code'] . ')');
            return ['ok' => false, 'error' => 'Cencosud respondió HTTP ' . $page['code'] . ' al listar ítems.'];
        }

        $this->syncStatus->setSyncing($connectionId, $page['total'], $userId, null);
        $processed = $this->processItems($connectionId, $page['items']);
        $this->syncStatus->updateProgress($connectionId, $processed, $page['nextCursor']);

        // Si no hay más páginas, decidir si enriquecemos inventario o terminamos
        if ($page['nextCursor'] === null || $page['nextCursor'] === '') {
            $done = $this->transitionAfterItems($connectionId, $userId);
            return ['ok' => true, 'total' => $page['total'], 'done' => $done];
        }

        return ['ok' => true, 'total' => $page['total'], 'done' => false];
    }

    /**
     * Procesa un lote de sincronización (un paso de la paginación).
     * Llamado recursivamente por AJAX en el panel o en bucles de consola.
     */
    public function processChunk($connectionId, $userId = null) {
        $status = $this->syncStatus->get($connectionId);
        if ($status->status !== 'syncing') {
            return ['ok' => false, 'done' => true, 'error' => 'No hay sincronización en curso.'];
        }

        $cursor = $status->next_cursor;

        // Fase de enriquecimiento de inventario SKU por SKU (Fallback)
        if ($cursor !== null && strpos($cursor, 'INV:') === 0) {
            $done = $this->processInventoryChunk($connectionId, $userId, (int) substr($cursor, 4));
            return ['ok' => true, 'done' => $done, 'processed' => (int) $status->synced_products, 'total' => (int) $status->total_products];
        }

        // Si terminó la paginación de ítems, decidir fase final
        if ($cursor === null || $cursor === '') {
            $done = $this->transitionAfterItems($connectionId, $userId);
            return ['ok' => true, 'done' => $done, 'processed' => (int) $status->synced_products, 'total' => (int) $status->total_products];
        }

        // Obtener la siguiente página de productos
        $page = $this->api->getItemsPage($connectionId, self::PAGE_SIZE, $cursor);
        if ($page['code'] !== 200) {
            $this->syncStatus->setError($connectionId, 'Error en lote de sync (HTTP ' . $page['code'] . ')');
            return ['ok' => false, 'done' => true, 'error' => 'Cencosud respondió HTTP ' . $page['code'] . '.'];
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
     * Transiciona el flujo al terminar de descargar productos.
     * Intenta actualizar stock de forma masiva (rápido). Si no es soportado,
     * activa la fase de sincronización uno a uno por SKUs.
     */
    private function transitionAfterItems($connectionId, $userId) {
        // Probar si la API masiva de inventarios está disponible
        $probe = $this->api->getInventoriesPage($connectionId, 1, null);

        if ($probe['code'] === 200) {
            // Soportado: Sincronizar stock completo de una sola vez
            $this->enrichInventoryPaginated($connectionId, $userId);
            $this->finishSync($connectionId, $userId);
            return true;
        }

        if (in_array($probe['code'], [404, 405, 501], true)) {
            // No soportado: activar fase troceada por SKU
            $this->syncStatus->updateProgress($connectionId, (int) $this->cache->getCount($connectionId), 'INV:0');
            return false;
        }

        // Otro error: terminar de igual forma omitiendo actualización de stock
        error_log("[CENCOSUD-SYNC] No se pudo determinar API de inventario (HTTP {$probe['code']}) en conexión {$connectionId}; se omite enriquecimiento.");
        $this->finishSync($connectionId, $userId);
        return true;
    }

    /**
     * Enriquece el stock consultando SKU por SKU en bloques.
     */
    private function processInventoryChunk($connectionId, $userId, $offset) {
        $skus = $this->cache->getSkusPage($connectionId, $offset, self::INVENTORY_CHUNK_SIZE);

        $conn = $this->connection->findById($connectionId);
        $targetUserId = $userId ?: ($conn ? (int) $conn->user_id : null);
        $notif = $targetUserId ? new NotificationService($this->db) : null;
        $storeName = $conn ? $conn->store_name : "Tienda #{$connectionId}";

        foreach ($skus as $sku) {
            $resp = $this->api->getInventory($connectionId, $sku);
            if ($resp['code'] === 200 && isset($resp['body']->quantity->amount)) {
                $qty = (int) $resp['body']->quantity->amount;
                
                // Regla de stock crítico (5 o menos)
                if ($qty <= 5 && $qty > 0) {
                    $originalQty = $qty;
                    $this->api->updateInventory($connectionId, $sku, 0);
                    $qty = 0;
                    
                    if ($notif && $targetUserId) {
                        $notif->createNotification(
                            $targetUserId,
                            'low_stock',
                            'No queda stock — ' . $storeName . ' (SKU: ' . $sku . ')',
                            "El stock en Cencosud era de {$originalQty} unidades (límite de seguridad ≤ 5). Se ha establecido automáticamente a 0 para evitar ventas sin stock."
                        );
                    }
                }
                
                $this->cache->updateLocalStock($connectionId, $sku, $qty);
            }
            usleep(150000); // 150ms de pausa de seguridad
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

    /**
     * Cierra el proceso de sincronización, envía notificaciones y registra actividad.
     */
    public function finishSync($connectionId, $userId = null, $hadErrors = false) {
        $total = $this->cache->getCount($connectionId);
        $this->syncStatus->setIdle($connectionId, $total);

        $storeName = $this->connection->findStoreName($connectionId);
        if ($userId) {
            try {
                // Notificar al usuario mediante el sistema de notificaciones de la App
                $notif = new NotificationService($this->db);
                $notif->notifySyncComplete($userId, null, $total, $total); // Notificación de catálogo completo

                // Alerta si hay productos con bajo stock
                $lowStock = $this->cache->getLowStock($connectionId, 5);
                if (!empty($lowStock)) {
                    $notif->createNotification(
                        $userId,
                        'low_stock',
                        'Stock bajo en Cencosud — ' . $storeName,
                        count($lowStock) . ' productos con 5 o menos unidades'
                    );
                }
            } catch (Exception $e) {
                error_log('[CENCOSUD-SYNC] Error creando notificaciones: ' . $e->getMessage());
            }

            try {
                // Registrar log de auditoría
                (new ActivityService($this->db))->log(
                    $userId,
                    'cencosud_sync',
                    "Sincronizó la tienda Cencosud '{$storeName}' ({$total} productos)"
                );
            } catch (Exception $e) {}
        }

        return ['ok' => true, 'total' => $total];
    }

    /** Sincronización continua en una sola llamada (especial para Cron Jobs / Consola CLI). */
    public function runFullSync($connectionId) {
        $start = $this->startFullSync($connectionId, 0);
        if (!$start['ok']) {
            return $start;
        }

        $maxChunks = 5000; // límite de seguridad para evitar bucles infinitos
        $done = !empty($start['done']);
        while (!$done) {
            usleep(300000); // 300 ms de pausa respetando límites de la API
            $chunk = $this->processChunk($connectionId, 0);
            if (!$chunk['ok']) {
                return $chunk;
            }
            $done = !empty($chunk['done']);
            if (--$maxChunks <= 0) {
                $this->syncStatus->setError($connectionId, 'Sync abortado: superó límite de páginas.');
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
            if ($this->cache->upsertFromCencosudItem($connectionId, $item) !== null) {
                $count++;
            }
        }
        return $count;
    }

    /** Descarga masiva del inventario mediante la API paginada. */
    private function enrichInventoryPaginated($connectionId, $userId = null) {
        $updated = 0;
        $cursor = null;
        $maxPages = 500;

        $conn = $this->connection->findById($connectionId);
        $targetUserId = $userId ?: ($conn ? (int) $conn->user_id : null);
        $notif = $targetUserId ? new NotificationService($this->db) : null;
        $storeName = $conn ? $conn->store_name : "Tienda #{$connectionId}";

        do {
            $page = $this->api->getInventoriesPage($connectionId, 50, $cursor);
            if ($page['code'] !== 200) {
                error_log("[CENCOSUD-SYNC] Inventories HTTP {$page['code']} en conexión {$connectionId}; stock no actualizado.");
                return $updated;
            }

            foreach ($page['inventories'] as $inv) {
                $sku = trim((string) ($inv->sku ?? ''));
                if ($sku === '') continue;
                $qty = (int) ($inv->quantity->amount ?? 0);
                
                // Regla de stock crítico (5 o menos)
                if ($qty <= 5 && $qty > 0) {
                    $originalQty = $qty;
                    $this->api->updateInventory($connectionId, $sku, 0);
                    $qty = 0;
                    
                    if ($notif && $targetUserId) {
                        $notif->createNotification(
                            $targetUserId,
                            'low_stock',
                            'No queda stock — ' . $storeName . ' (SKU: ' . $sku . ')',
                            "El stock en Cencosud era de {$originalQty} unidades (límite de seguridad ≤ 5). Se ha establecido automáticamente a 0 para evitar ventas sin stock."
                        );
                    }
                }
                
                $this->cache->updateLocalStock($connectionId, $sku, $qty);
                $updated++;
            }

            $cursor = $page['nextCursor'];
        } while ($cursor !== null && $cursor !== '' && --$maxPages > 0);

        return $updated;
    }
}
