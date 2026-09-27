<?php

class MercadoLibreAdapter implements MarketplaceInterface {
    protected $db;
    protected $productsModel;

    public function __construct($db = null) {
        $this->db = $db ?: new Database();
        $this->productsModel = new MlProductsCacheModel($this->db);
    }

    public function getProducts($connectionId, $page, $limit, $search, $status) {
        return $this->productsModel->getProducts($connectionId, $page, $limit, $search, $status);
    }

    public function saveProduct($connectionId, $data) {
        $itemId = $data['id'] ?? '';
        if (empty($itemId)) {
            throw new Exception("Operación no soportada para creación de productos directos en MercadoLibre.");
        }
        
        $updateData = [];
        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (isset($data['price'])) $updateData['price'] = (float)$data['price'];
        if (isset($data['original_price'])) $updateData['original_price'] = (float)$data['original_price'];
        if (isset($data['currency_id'])) $updateData['currency_id'] = $data['currency_id'];
        if (isset($data['available_quantity'])) $updateData['available_quantity'] = (int)$data['available_quantity'];
        if (isset($data['sold_quantity'])) $updateData['sold_quantity'] = (int)$data['sold_quantity'];
        if (isset($data['condition'])) $updateData['condition'] = $data['condition'];
        if (isset($data['listing_type_id'])) $updateData['listing_type_id'] = $data['listing_type_id'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['category_id'])) $updateData['category_id'] = $data['category_id'];
        if (isset($data['category_name'])) $updateData['category_name'] = $data['category_name'];
        if (isset($data['thumbnail'])) $updateData['thumbnail'] = $data['thumbnail'];
        if (isset($data['permalink'])) $updateData['permalink'] = $data['permalink'];
        if (isset($data['sku'])) $updateData['sku'] = $data['sku'];
        if (isset($data['parent_ml_item_id'])) $updateData['parent_ml_item_id'] = $data['parent_ml_item_id'];
        if (isset($data['start_time'])) $updateData['start_time'] = $data['start_time'];
        if (isset($data['extra_data'])) {
            $updateData['extra_data'] = (is_array($data['extra_data']) || is_object($data['extra_data']))
                ? json_encode($data['extra_data'], JSON_UNESCAPED_UNICODE)
                : $data['extra_data'];
        }

        $this->productsModel->upsert($connectionId, $itemId, $updateData);

        // 🔄 SINCRONIZACIÓN CON BODEGA CENTRAL Y CENCOSUD (DELTA + CRÍTICO <= 5)
        if (!empty($updateData['sku']) && isset($updateData['available_quantity'])) {
            $this->syncStockToCencosudWithProtection($updateData['sku'], (int)$updateData['available_quantity']);
        }

        // 🔀 PROCESAR VARIANTES / VARIACIONES
        $variations = $data['variations'] ?? $data['variantes'] ?? [];
        if (!empty($variations) && (is_array($variations) || is_object($variations))) {
            foreach ($variations as $var) {
                $var = (array)$var;
                $varId = $var['id'] ?? $var['ml_item_id'] ?? null;
                if (!$varId) continue;

                $vData = [
                    'parent_ml_item_id' => $itemId,
                ];
                if (isset($var['sku'])) $vData['sku'] = $var['sku'];
                if (isset($var['title'])) $vData['title'] = $var['title'];
                if (isset($var['price'])) $vData['price'] = (float)$var['price'];
                if (isset($var['original_price'])) $vData['original_price'] = (float)$var['original_price'];
                if (isset($var['currency_id'])) $vData['currency_id'] = $var['currency_id'];
                if (isset($var['available_quantity'])) $vData['available_quantity'] = (int)$var['available_quantity'];
                if (isset($var['sold_quantity'])) $vData['sold_quantity'] = (int)$var['sold_quantity'];
                if (isset($var['condition'])) $vData['condition'] = $var['condition'];
                if (isset($var['listing_type_id'])) $vData['listing_type_id'] = $var['listing_type_id'];
                if (isset($var['status'])) $vData['status'] = $var['status'];
                if (isset($var['category_id'])) $vData['category_id'] = $var['category_id'];
                if (isset($var['category_name'])) $vData['category_name'] = $var['category_name'];
                if (isset($var['thumbnail'])) $vData['thumbnail'] = $var['thumbnail'];
                if (isset($var['permalink'])) $vData['permalink'] = $var['permalink'];

                $this->productsModel->upsert($connectionId, (string)$varId, $vData);

                if (!empty($vData['sku']) && isset($vData['available_quantity'])) {
                    $this->syncStockToCencosudWithProtection($vData['sku'], (int)$vData['available_quantity']);
                }
            }
        }

        return $itemId;
    }

    public function deleteProduct($connectionId, $productId) {
        return $this->db->query('DELETE FROM ml_products_cache WHERE ml_connection_id = ? AND ml_item_id = ?')
                        ->execute([$connectionId, $productId]);
    }

    public function updateStock($connectionId, $productId, $qty) {
        $qty = (int)$qty;
        $this->productsModel->upsert($connectionId, $productId, ['available_quantity' => $qty]);
        
        // Obtener SKU del producto en Mercado Libre
        $pStmt = $this->db->query("SELECT sku FROM ml_products_cache WHERE ml_connection_id = ? AND ml_item_id = ?");
        $pStmt->execute([$connectionId, $productId]);
        $prod = $pStmt->fetch();
        
        if ($prod && !empty($prod->sku)) {
            // 🔄 SINCRONIZACIÓN CRUZADA CONTRA BODEGA (products_master) Y CENCOSUD
            $this->syncStockToCencosudWithProtection($prod->sku, $qty);
        }
        return true;
    }

    /**
     * Sincroniza el stock de Mercado Libre a Cencosud cruzándolo obligatoriamente contra el Stock Real de Bodega (products_master).
     * Aplica la regla de protección <= 5 -> 0 y la comparación de Delta (Evita peticiones HTTP redundantes).
     */
    protected function syncStockToCencosudWithProtection($sku, $mlQty) {
        // 1. Consultar la Fuente de Verdad en Bodega Central (products_master)
        $bodegaRow = $this->db->row("SELECT stock FROM products_master WHERE sku = ? LIMIT 1", [$sku]);
        $bodegaStock = $bodegaRow ? (int)$bodegaRow->stock : $mlQty;

        // 2. Stock Disponible Real = Mínimo entre Bodega física y Mercado Libre
        $realAvailableStock = min($bodegaStock, $mlQty);

        // 3. Regla de Stock Crítico: Si el stock real disponible es <= 5 unidades, Cencosud se fuerza a 0
        $cencoTargetStock = ($realAvailableStock <= 5) ? 0 : $realAvailableStock;

        // 4. Obtener el estado actual registrado en Cencosud
        $cencoItem = $this->db->row("SELECT id, available_quantity, cencosud_connection_id FROM cencosud_products_cache WHERE sku = ? LIMIT 1", [$sku]);

        if (!$cencoItem) {
            // SKU no existe en Cencosud, omitir
            return;
        }

        $currentCencoStock = (int)$cencoItem->available_quantity;

        // 5. COMPARACIÓN DE DELTA: Si el stock objetivo YA coincide con Cencosud, OMITIR petición HTTP externa
        if ($currentCencoStock === $cencoTargetStock) {
            return;
        }

        // 6. Actualizar la caché local de Cencosud
        $this->db->query("UPDATE cencosud_products_cache SET available_quantity = ? WHERE sku = ?")
                 ->execute([$cencoTargetStock, $sku]);

        // 7. Transmitir el cambio a la API de Cencosud / Paris.cl
        try {
            $cencoApiService = new CencosudApiService($this->db);
            $cencoApiService->updateStockBySku($cencoItem->cencosud_connection_id, $sku, $cencoTargetStock);

            // 8. Notificar en la campanita si operó la protección <= 5
            if ($realAvailableStock <= 5 && $realAvailableStock > 0) {
                $notifService = new NotificationService($this->db);
                $userId = $_SESSION['user_id'] ?? 1;
                $notifService->notifyStockSyncProtection((int)$userId, $sku, $realAvailableStock, $cencoTargetStock);
            }
        } catch (\Throwable $e) {
            error_log("Error al sincronizar stock de ML/Bodega a Cencosud por SKU ($sku): " . $e->getMessage());
        }
    }

    public function syncProducts($connectionId) {
        $syncService = new MlSyncService($this->db);
        return $syncService->sync($connectionId);
    }

    // MercadoLibre todavía no tiene módulo de órdenes en el sistema; se
    // devuelve un resultado vacío para cumplir MarketplaceInterface.
    public function getOrders($connectionId, $page, $limit, $search) {
        return ['items' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
    }

    public function syncOrders($connectionId) {
        return true;
    }
}
