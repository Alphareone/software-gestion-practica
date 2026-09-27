<?php

/**
 * Wrapper de negocio sobre la API de Cencosud Chile.
 * Resuelve la conexión, obtiene/renueva el token de acceso, reintenta peticiones
 * en caso de errores 401 (token expirado) y aplica backoff exponencial para errores 429/5xx.
 */
class CencosudApiService {

    private $db;
    private $auth;
    private $connection;
    private $rateLimiter;

    public function __construct($db) {
        $this->db = $db;
        $this->auth = new CencosudAuthService($db);
        $this->connection = new CencosudConnectionModel($db);
        $this->rateLimiter = new CencosudApiRateLimiter($db);
    }

    /**
     * Realiza una petición autenticada a la API de Cencosud.
     * En caso de error 401, renueva el token e intenta una vez más.
     */
    public function call($connectionId, $endpoint, $method = 'GET', $data = null) {
        $conn = $this->connection->findById($connectionId);
        if (!$conn) {
            return ['code' => 0, 'body' => null, 'raw' => 'Conexión no encontrada'];
        }

        $token = $this->auth->getToken($connectionId);
        if (!$token) {
            return ['code' => 401, 'body' => null, 'raw' => 'No se pudo obtener el token de acceso'];
        }

        $result = $this->doRequest($conn, $endpoint, $method, $data, $token);

        // Si el token expiró a mitad de proceso (Error 401), forzar renovación e intentar de nuevo
        if ($result['code'] === 401) {
            $token = $this->auth->getToken($connectionId, true); // forceNew = true
            if ($token) {
                $result = $this->doRequest($conn, $endpoint, $method, $data, $token);
            }
        }

        return $result;
    }

    /**
     * Realiza llamados a la API implementando reintentos con Backoff Exponencial
     * para manejar de forma segura errores transitorios del servidor (5xx) o límites de tasa (429).
     */
    public function callWithRetry($connectionId, $endpoint, $method = 'GET', $data = null, $maxRetries = 3) {
        $delay = 1; // Retraso inicial de 1 segundo
        
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $result = $this->call($connectionId, $endpoint, $method, $data);
            
            // Reintentar solo si es error de límite de tasa (429) o error del servidor (5xx)
            $retryable = $result['code'] === 429 || ($result['code'] >= 500 && $result['code'] < 600);
            
            if (!$retryable || $attempt === $maxRetries) {
                return $result;
            }
            
            sleep($delay);
            $delay *= 2; // Duplicar el tiempo de espera (1s -> 2s -> 4s -> 8s)
        }
        return $result;
    }

    // ── Catálogo (Items) ──

    /**
     * Obtiene una página del catálogo de productos de Cencosud.
     * Soporta paginación por cursor o por offset.
     */
    public function getItemsPage($connectionId, $limit = 200, $cursor = null) {
        // GET /v2/stock devuelve el catálogo del vendedor paginado por offset,
        // con título, stock y el SKU propio del seller. Es la fuente del espejo.
        // Respuesta: { pagging: {quantity, limit, offset}, skus: [...] }
        $offset = ($cursor !== null && $cursor !== '' && ctype_digit((string) $cursor)) ? (int) $cursor : 0;

        $endpoint = '/v2/stock?limit=' . (int) $limit . '&offset=' . $offset;

        $result = $this->callWithRetry($connectionId, $endpoint);
        if ($result['code'] !== 200 || !$result['body']) {
            return [
                'code' => $result['code'],
                'items' => [],
                'nextCursor' => null,
                'total' => 0,
                'raw' => $result['raw'] ?? ''
            ];
        }

        $body = $result['body'];
        $items = $body->skus ?? [];
        $items = is_array($items) ? $items : [$items];
        $total = (int) ($body->pagging->quantity ?? 0);

        // La API pagina por offset y no entrega cursor: se calcula el siguiente.
        $nextOffset = $offset + count($items);
        $nextCursor = ($total > 0 && $nextOffset < $total && count($items) > 0) ? (string) $nextOffset : null;

        return [
            'code' => 200,
            'items' => $items,
            'nextCursor' => $nextCursor,
            'total' => $total,
        ];
    }

    /**
     * Obtiene una página del catálogo Cencosud v2 mediante filtros avanzados y paginación por offset/limit.
     * (GET /v2/products/search?limit=25&offset=0)
     */
    public function searchProductsPage($connectionId, $limit = 25, $offset = 0, array $filters = []) {
        $query = [
            'limit' => max(1, (int) $limit),
            'offset' => max(0, (int) $offset),
        ];

        if (!empty($filters['search'])) $query['search'] = $filters['search'];
        if (!empty($filters['familyIds'])) $query['familyIds'] = $filters['familyIds'];
        if (!empty($filters['statusApproval'])) $query['statusApproval'] = $filters['statusApproval'];
        if (!empty($filters['category'])) $query['category'] = $filters['category'];

        $endpoint = '/v2/products/search?' . http_build_query($query);
        $result = $this->callWithRetry($connectionId, $endpoint);

        if ($result['code'] !== 200 || !$result['body']) {
            return [
                'code' => $result['code'],
                'items' => [],
                'total' => 0,
                'offset' => $offset,
                'limit' => $limit,
                'raw' => $result['raw'] ?? ''
            ];
        }

        $body = $result['body'];
        $items = $body->results ?? ($body->ItemResponse ?? []);
        $total = (int) ($body->total ?? ($body->totalItems ?? 0));

        return [
            'code' => 200,
            'items' => is_array($items) ? $items : [$items],
            'total' => $total,
            'offset' => (int) ($body->offset ?? $offset),
            'limit' => (int) ($body->limit ?? $limit),
            'has_more' => ($offset + count($items)) < $total,
        ];
    }

    /**
     * Obtiene TODOS los productos del catálogo recorriendo secuencialmente la API de Cencosud
     * mediante un bucle while seguro con corta-circuito ($maxPages) para prevenir bucles infinitos.
     */
    public function fetchAllProducts($connectionId, array $filters = [], $pageSize = 100, $maxPages = 500) {
        $offset = 0;
        $allProducts = [];
        $hasMore = true;
        $pageCount = 0;

        while ($hasMore && $pageCount < $maxPages) {
            $pageCount++;
            $pageResult = $this->searchProductsPage($connectionId, $pageSize, $offset, $filters);
            
            if ($pageResult['code'] !== 200 || empty($pageResult['items'])) {
                $hasMore = false;
                break;
            }

            foreach ($pageResult['items'] as $item) {
                $allProducts[] = $item;
            }

            $fetchedCount = count($pageResult['items']);
            $total = (int) ($pageResult['total'] ?? 0);
            
            // Incrementar el offset por la cantidad real de elementos recibidos en la página
            $offset += $fetchedCount;

            // Evaluar condición de continuación: Si hay un total informado, verificar offset < total
            if ($total > 0) {
                $hasMore = ($offset < $total) && ($fetchedCount > 0);
            } else {
                $hasMore = ($fetchedCount >= $pageSize);
            }
        }

        return [
            'code' => 200,
            'total' => count($allProducts),
            'products' => $allProducts,
            'pages_fetched' => $pageCount,
        ];
    }

    public function getItem($connectionId, $sku) {
        // No hay endpoint de ficha individual: se filtra el catálogo por SKU.
        return $this->callWithRetry($connectionId, '/v2/stock?limit=1&offset=0&sku=' . rawurlencode($sku));
    }

    // ── Inventarios ──

    /**
     * Obtiene inventarios paginados de Cencosud.
     */
    public function getInventoriesPage($connectionId, $limit = 50, $cursor = null) {
        // La API no tiene un servicio de inventarios aparte: el stock viaja
        // en el mismo /v2/stock que el catálogo, paginado por offset.
        $offset = ($cursor !== null && $cursor !== '' && ctype_digit((string) $cursor)) ? (int) $cursor : 0;
        $endpoint = '/v2/stock?limit=' . (int) $limit . '&offset=' . $offset;

        $result = $this->callWithRetry($connectionId, $endpoint);
        if ($result['code'] !== 200 || !$result['body']) {
            return ['code' => $result['code'], 'inventories' => [], 'nextCursor' => null];
        }

        $body = $result['body'];
        $inventories = $body->skus ?? [];
        $inventories = is_array($inventories) ? $inventories : [$inventories];
        $total = (int) ($body->pagging->quantity ?? 0);
        $nextOffset = $offset + count($inventories);
        $nextCursor = ($total > 0 && $nextOffset < $total && count($inventories) > 0) ? (string) $nextOffset : null;
        
        return [
            'code' => 200,
            'inventories' => is_array($inventories) ? $inventories : [$inventories],
            'nextCursor' => $nextCursor,
        ];
    }

    public function getInventory($connectionId, $sku) {
        return $this->callWithRetry($connectionId, '/v2/stock?limit=1&offset=0&sku=' . rawurlencode($sku));
    }

    // ── Actualizaciones de Precio y Stock ──

    /**
     * Actualiza el precio de un SKU específico en la API de Cencosud.
     */
    public function updatePrice($connectionId, $sku, $amount, $currency = 'CLP') {
        // PATCH /v2/prices/product/{sku} — precios diferenciados por tienda.
        // El SKU va en la ruta y el cuerpo lleva el arreglo de precios.
        $payload = json_encode([
            'prices' => [[
                'value' => (float) $amount,
            ]],
        ], JSON_UNESCAPED_UNICODE);

        return $this->callWithRetry(
            $connectionId,
            '/v2/prices/product/' . rawurlencode($sku),
            'PATCH',
            $payload,
            2
        );
    }

    /**
     * Actualiza el stock de un SKU específico en la API de Cencosud y lo sincroniza bidireccionalmente con Mercado Libre.
     */
    public function updateInventory($connectionId, $sku, $quantity) {
        // POST /v2/stock — crea o actualiza stock. Admite varios SKU por llamada.
        $payload = json_encode([
            'skus' => [[
                'sku' => $sku,
                'quantity' => (int) $quantity,
            ]],
        ], JSON_UNESCAPED_UNICODE);

        $res = $this->callWithRetry($connectionId, '/v2/stock', 'POST', $payload, 2);
        if ($res['code'] === 200 || $res['code'] === 204) {
            $this->syncStockToMl($sku, (int) $quantity);
        }
        return $res;
    }

    /**
     * Sincronización bidireccional de stock por SKU hacia Mercado Libre.
     */
    public function syncStockToMl($sku, $quantity) {
        if (empty($sku) || !$this->db) return;
        try {
            $this->db->query("UPDATE ml_products_cache SET available_quantity = ? WHERE sku = ?", [(int)$quantity, $sku]);
        } catch (\Throwable $e) {
            error_log("Error al sincronizar stock bidireccional a Mercado Libre: " . $e->getMessage());
        }
    }

    /**
     * Sincroniza órdenes y sub-órdenes desde Cencosud API (GET /v1/orders y GET /v3/sub-orders).
     */
    public function syncOrders($connectionId) {
        $res = $this->callWithRetry($connectionId, '/v1/orders');
        if ($res['code'] !== 200 || !$res['body']) return false;

        $orders = is_array($res['body']) ? $res['body'] : ($res['body']->data ?? []);
        $ordersModel = new CencosudOrdersCacheModel($this->db);

        foreach ($orders as $o) {
            $orderId = $o->orderNumber ?? ($o->id ?? '');
            if (empty($orderId)) continue;

            $subRes = $this->callWithRetry($connectionId, "/v3/sub-orders?orderNumber=" . urlencode($orderId));
            $subOrders = ($subRes['code'] === 200) ? ($subRes['body']->data ?? []) : [];

            $customerName = $o->customer->name ?? ($o->customerName ?? 'Cliente Cencosud');
            $status = $o->status ?? 'pending';
            $totalAmount = (float)($o->totalAmount ?? ($o->total ?? 0));
            $shippingStatus = $o->shippingStatus ?? 'ready_to_ship';
            $createdCenco = isset($o->createdAt) ? (new DateTime($o->createdAt))->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

            $fullData = [
                'order' => $o,
                'sub_orders' => $subOrders
            ];

            $ordersModel->upsert($connectionId, $orderId, [
                'status' => $status,
                'total_amount' => $totalAmount,
                'customer_name' => $customerName,
                'shipping_status' => $shippingStatus,
                'created_at_cenco' => $createdCenco,
                'raw_data' => json_encode($fullData, JSON_UNESCAPED_UNICODE)
            ]);
        }
        return true;
    }

    /**
     * Construye el payload JSON seguro para Cencosud (POST /v2/products o PATCH /v2/products/{id}).
     * Compara extra_data contra la fuente de verdad (products_master), sanitiza datos corruptos, aplica respaldos (fallbacks) seguros
     * y dispara una ALERTA A LA CAMPANITA si se aplicaron valores de respaldo por atributos faltantes.
     */
    public function buildProductPayload($master, $extraData = [], $variantData = [], $userId = null) {
        $extraAttrs = is_array($extraData) ? $extraData : (json_decode((string)$extraData, true) ?: []);

        // Sanitizador de valores de texto
        $cleanVal = function($val) {
            if ($val === null || $val === false) return '';
            $str = trim((string)$val);
            if (in_array(strtolower($str), ['', 'null', 'undefined', '[object object]'])) return '';
            return $str;
        };

        $usedFallbacks = [];

        // 1. COMPARACIÓN Y RESPALDOS (PRIORIDAD: products_master > extra_data > Fallback seguro)
        $brandMaster = $cleanVal($master['brand'] ?? '');
        $brand = $brandMaster ?: ($cleanVal($extraAttrs['brand'] ?? '') ?: 'Genérica');
        if (empty($brandMaster) && empty($extraAttrs['brand'])) $usedFallbacks[] = 'Marca (Genérica)';

        $title = $cleanVal($master['title'] ?? '') ?: ($cleanVal($extraAttrs['title'] ?? '') ?: 'Producto sin título');
        $description = $cleanVal($master['description'] ?? '') ?: ($cleanVal($extraAttrs['description'] ?? '') ?: $title);
        $category = $cleanVal($master['category'] ?? '') ?: ($cleanVal($extraAttrs['category'] ?? '') ?: 'General');
        
        $colorMaster = $cleanVal($master['color'] ?? '');
        $color = $colorMaster ?: ($cleanVal($extraAttrs['color'] ?? '') ?: 'Único');
        if (empty($colorMaster) && empty($extraAttrs['color'])) $usedFallbacks[] = 'Color (Único)';

        $sizeMaster = $cleanVal($master['size'] ?? '');
        $size = $sizeMaster ?: ($cleanVal($extraAttrs['talla'] ?? '') ?: ($cleanVal($extraAttrs['size'] ?? '') ?: 'Estándar'));
        if (empty($sizeMaster) && empty($extraAttrs['talla']) && empty($extraAttrs['size'])) $usedFallbacks[] = 'Talla (Estándar)';

        $genderMaster = $cleanVal($master['gender'] ?? '');
        $gender = $genderMaster ?: ($cleanVal($extraAttrs['genero'] ?? '') ?: ($cleanVal($extraAttrs['gender'] ?? '') ?: 'Unisex'));
        if (empty($genderMaster) && empty($extraAttrs['genero']) && empty($extraAttrs['gender'])) $usedFallbacks[] = 'Género (Unisex)';

        $sku = strtoupper($cleanVal($master['sku'] ?? 'SKU-PENDIENTE'));

        // 🔍 RATIFICACIÓN CONTINUA DE MEDIDAS CONTRA LA TABLA product_medidas
        $measures = $this->getRatifiedMeasures($sku, $category);
        if ($measures['fuente'] !== 'sku_exacto') {
            $usedFallbacks[] = 'Medidas de Despacho (Ratificadas de ' . $measures['fuente'] . ')';
        }

        // 🔔 DISPARAR ALERTA EN LA CAMPANITA SI HUBO INCONSISTENCIA O VALORES DE RESPALDO
        if (!empty($usedFallbacks)) {
            $targetUser = $userId ?: ($_SESSION['user_id'] ?? null);
            if ($targetUser && $this->db) {
                try {
                    $notifService = new NotificationService($this->db);
                    $notifService->notifyProductFallbackWarning((int)$targetUser, $sku, $title, $usedFallbacks);
                } catch (\Throwable $e) {
                    error_log("Error al notificar alerta de fallback Cencosud: " . $e->getMessage());
                }
            }
        }

        // 2. ATRIBUTOS NIVEL PRODUCTO PADRE (Sin datos corruptos)
        $productAttributes = [
            ['name' => 'Marca', 'value' => $brand],
            ['name' => 'Descripción', 'value' => mb_substr($description, 0, 500)],
            ['name' => 'Peso Real (Kg)', 'value' => (string) $measures['peso_kg']],
            ['name' => 'Alto (cm)', 'value' => (string) $measures['alto_cm']],
            ['name' => 'Ancho (cm)', 'value' => (string) $measures['ancho_cm']],
            ['name' => 'Profundidad (cm)', 'value' => (string) $measures['grueso_cm']],
            ['name' => 'Peso Volumétrico (Kg)', 'value' => (string) sprintf('%.2f', ($measures['alto_cm'] * $measures['ancho_cm'] * $measures['grueso_cm']) / 4000)],
        ];

        // Fusionar extra_data limpio que no colisione con atributos principales
        $ignoredKeys = ['brand', 'marca', 'title', 'titulo', 'description', 'descripcion', 'color', 'talla', 'size', 'genero', 'gender', 'sku', 'price', 'precio', 'stock', 'family_id', 'category', 'peso', 'alto', 'ancho', 'profundidad', 'largo', 'grueso'];
        foreach ($extraAttrs as $k => $v) {
            $keyClean = strtolower(trim((string)$k));
            $valClean = $cleanVal($v);
            if (!in_array($keyClean, $ignoredKeys) && !empty($valClean)) {
                $productAttributes[] = ['name' => ucfirst($k), 'value' => $valClean];
            }
        }

        // 3. ATRIBUTOS NIVEL VARIANTE (Garantizados con respaldos)
        $variantAttributes = [
            ['name' => 'Color Comercial', 'value' => $color],
            ['name' => 'Talla', 'value' => $size],
            ['name' => 'Género', 'value' => $gender],
        ];

        $thumbnail = $cleanVal($master['thumbnail'] ?? '') ?: ($cleanVal($extraAttrs['thumbnail'] ?? '') ?: '');

        $payload = [
            'product' => [
                'name' => $title,
                'sellerSku' => $sku,
                'familyId' => $cleanVal($extraAttrs['family_id'] ?? '') ?: $category,
                'category' => $category,
                'attributes' => $productAttributes,
            ],
            'variants' => [
                [
                    'sellerSku' => $sku,
                    'name' => $title . " ($color - $size)",
                    'attributes' => $variantAttributes,
                    'medias' => !empty($thumbnail) ? [['url' => str_replace('http://', 'https://', $thumbnail)]] : [],
                ]
            ]
        ];

        return $payload;
    }

    /**
     * Ratifica continuamente las medidas físicas de despacho contra la tabla product_medidas.
     * Si la planilla trae errores, ceros o nulos, busca en la tabla por SKU exacto, por Categoría
     * o aplica el respaldo estándar seguro (0.5kg, 10x10x10cm).
     */
    public function getRatifiedMeasures($sku, $category = null) {
        if ($this->db) {
            try {
                // 1. Coincidencia exacta por SKU/variante en product_medidas
                $stmt = $this->db->query("SELECT peso_kg, alto_cm, ancho_cm, grueso_cm FROM product_medidas WHERE variante = ? LIMIT 1");
                $stmt->execute([$sku]);
                $row = $stmt->fetch();
                if ($row && (float)$row->peso_kg > 0) {
                    return [
                        'peso_kg' => (float)$row->peso_kg,
                        'alto_cm' => (float)$row->alto_cm,
                        'ancho_cm' => (float)$row->ancho_cm,
                        'grueso_cm' => (float)$row->grueso_cm,
                        'fuente' => 'sku_exacto'
                    ];
                }

                // 2. Coincidencia por Categoría en product_medidas
                if (!empty($category)) {
                    $stmtCat = $this->db->query("SELECT peso_kg, alto_cm, ancho_cm, grueso_cm FROM product_medidas WHERE categoria LIKE ? ORDER BY id ASC LIMIT 1");
                    $stmtCat->execute(['%' . $category . '%']);
                    $rowCat = $stmtCat->fetch();
                    if ($rowCat && (float)$rowCat->peso_kg > 0) {
                        return [
                            'peso_kg' => (float)$rowCat->peso_kg,
                            'alto_cm' => (float)$rowCat->alto_cm,
                            'ancho_cm' => (float)$rowCat->ancho_cm,
                            'grueso_cm' => (float)$rowCat->grueso_cm,
                            'fuente' => 'categoria_fallback'
                        ];
                    }
                }
            } catch (\Throwable $e) {
                error_log("Error ratificando medidas en product_medidas: " . $e->getMessage());
            }
        }

        // 3. Respaldo continuo estándar de seguridad (0.5 kg, 10x10x10 cm)
        return [
            'peso_kg' => 0.5,
            'alto_cm' => 10.0,
            'ancho_cm' => 10.0,
            'grueso_cm' => 10.0,
            'fuente' => 'estandar_seguro'
        ];
    }

    /**
     * Actualiza el stock de un producto en Cencosud por su SKU.
     * Incluye comprobación de delta: si el valor objetivo coincide con el actual, omite la llamada HTTP externa.
     */
    public function updateStockBySku($connectionId, $sku, $qty) {
        $qty = max(0, (int)$qty);

        // 1. Verificar el stock registrado en la caché de Cencosud
        $stmtCenco = $this->db->query("SELECT id, available_quantity FROM cencosud_products_cache WHERE cencosud_connection_id = ? AND sku = ? LIMIT 1");
        $stmtCenco->execute([$connectionId, $sku]);
        $cencoItem = $stmtCenco->fetch();

        if ($cencoItem && (int)$cencoItem->available_quantity === $qty) {
            // El stock en Cencosud ya coincide; omitir petición HTTP para prevenir rate limiting
            return ['code' => 200, 'skipped' => true, 'message' => 'Stock idéntico. Petición HTTP omitida por delta 0.'];
        }

        // 2. Realizar la actualización en la API de Cencosud
        $endpoint = '/v2/stock';
        $payload = json_encode([
            'skus' => [[
                'sku' => $sku,
                'quantity' => $qty,
            ]],
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->callWithRetry($connectionId, $endpoint, 'POST', $payload);

        // 3. Actualizar la caché local si la operación fue exitosa (o 204 No Content)
        if ($result['code'] === 200 || $result['code'] === 204) {
            $this->db->query("UPDATE cencosud_products_cache SET available_quantity = ? WHERE cencosud_connection_id = ? AND sku = ?")
                     ->execute([$qty, $connectionId, $sku]);
        }

        return $result;
    }

    /**
     * Actualiza el stock por ID de producto (delegando a updateStockBySku)
     */
    public function updateStock($connectionId, $productId, $qty) {
        $stmt = $this->db->query("SELECT sku FROM cencosud_products_cache WHERE cencosud_connection_id = ? AND (id = ? OR cencosud_product_id = ?) LIMIT 1");
        $stmt->execute([$connectionId, $productId, $productId]);
        $cencoItem = $stmt->fetch();

        $sku = $cencoItem->sku ?? $productId;
        return $this->updateStockBySku($connectionId, $sku, $qty);
    }

    private function doRequest($conn, $endpoint, $method, $data, $token) {
        return CencosudApiHelper::request(
            $conn->api_base_url ?: CENCOSUD_API_BASE_URL,
            $endpoint,
            $method,
            $data,
            $this->auth->buildAuth($conn, $token),
            $this->rateLimiter
        );
    }
}

