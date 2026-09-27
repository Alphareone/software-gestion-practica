<?php


class MlController extends Controller {

    protected $baseRoute = '/connections';

    protected function route($path = '') {
        return URLROOT . $this->baseRoute . $path;
    }

    protected function ensureCsrf() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    // ── Obtener token activo (para otros controllers) ──

    public static function getActiveToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $storeId = $_SESSION['ml_active_store_id'] ?? null;
        if (!$storeId) return null;

        $userId = (int) $_SESSION['user_id'];
        $service = new MercadoLibreAuthService();
        return $service->getTokenForStore($storeId, $userId);
    }

    // ── Obtener conexiones de la DB ──

    protected function getConnections() {
        $db = $this->db();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $isAdmin = !empty($_SESSION['is_admin']);

        $connModel = new ConnectionModel($db);
        $rows = $connModel->findAll();

        $currentAppId = MlConfigService::getAppId($db);

        $authService = new MercadoLibreAuthService($db);
        foreach ($rows as $row) {
            $expired = !$row->token_expires_at || strtotime($row->token_expires_at . ' UTC') < time() + 300;
            if ($row->is_active && $expired && $isAdmin) {
                $authService->tryRefreshToken((int) $row->id, $userId);
            }
        }

        if ($isAdmin) {
            $rows = $connModel->findAll();
        }

        foreach ($rows as $row) {
            $row->token_ok = $row->token_expires_at && strtotime($row->token_expires_at . ' UTC') > time();
            $row->config_mismatch = $row->config_app_id && $currentAppId && $row->config_app_id !== $currentAppId;
        }
        return $rows;
    }

    protected function hasPendingBatch($userId) {
        $db = $this->db();
        $stmt = $db->query('SELECT id FROM price_batch_pending WHERE user_id = ? AND status IN (\'pending\', \'processing\') LIMIT 1');
        $stmt->execute([$userId]);
        return (bool) $stmt->fetch();
    }

    // ── Páginas ──

    public function index() {
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }

    public function metrics() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $activeStoreId = $_SESSION['ml_active_store_id'] ?? null;
        if (!$activeStoreId) {
            echo json_encode(['error' => 'No hay tienda seleccionada.']);
            exit;
        }

        $db = $this->db();
        $cache = new MlProductsCacheModel($db);
        $metrics = $cache->getMetrics((int) $activeStoreId);

        $syncStatus = new MlSyncStatusModel($db);
        $sync = $syncStatus->get((int) $activeStoreId);

        $metrics['sync_status'] = $sync->status ?? 'idle';
        $metrics['last_sync_at'] = $sync->last_sync_at ?? null;
        $metrics['total_products_cached'] = (int) ($sync->total_products ?? 0);

        echo json_encode($metrics);
        exit;
    }

    public function adminMetrics($id = null) {
        $this->requireAdmin();
        header('Content-Type: application/json');

        if (!$id || !is_numeric($id)) {
            echo json_encode(['error' => 'ID no especificado.']);
            exit;
        }

        $db = $this->db();
        $stmt = $db->query('SELECT id, is_active, store_name FROM ml_connections WHERE id = ?');
        $stmt->execute([(int)$id]);
        $store = $stmt->fetch();

        if (!$store) {
            echo json_encode(['error' => 'Tienda no encontrada.']);
            exit;
        }

        if (!$store->is_active) {
            echo json_encode(['total' => 0, 'active' => 0, 'low_stock' => 0]);
            exit;
        }

        $cache = new MlProductsCacheModel($db);
        $metrics = $cache->getMetrics((int) $id);
        $metrics['store_id'] = (int)$id;
        $metrics['store_name'] = $store->store_name;
        echo json_encode($metrics);
        exit;
    }

    // ── Fase 1: verificar products_central contra ml_products_cache.
    // El mirror ahora limpia datos (ver ProductsCentralModel::mirrorFromMlCache),
    // así que central_count puede ser MENOR que cache_count (filas basura
    // descartadas) sin que sea un error. Que sea MAYOR sí lo es siempre. ──
    public function centralVerify() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        $central = new ProductsCentralModel($this->db());
        $rows = $central->verifyAgainstCache();

        $connections = [];
        $allMatch = true;
        foreach ($rows as $r) {
            $cacheCount = (int) $r->cache_count;
            $centralCount = (int) $r->central_count;
            $match = $centralCount <= $cacheCount;
            if (!$match) $allMatch = false;
            $connections[] = [
                'connection_id'  => (int) $r->connection_id,
                'store_name'     => $r->store_name,
                'is_active'      => (bool) $r->is_active,
                'cache_count'    => $cacheCount,
                'central_count'  => $centralCount,
                'discarded'      => max(0, $cacheCount - $centralCount),
                'match'          => $match,
                'last_mirror_at' => $r->last_mirror_at,
            ];
        }

        echo json_encode(['ok' => $allMatch, 'connections' => $connections]);
        exit;
    }

    public function adminstores() {
        $this->requireAdmin();
        $this->ensureCsrf();

        $connections = $this->getConnections();

        $page_title = 'MercadoLibre - Gestión de Tiendas';
        $page_description = 'Administra las tiendas conectadas.';
        $current_nav = 'ml-admin';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connections,
        ];

        $view_content = __DIR__ . '/../Views/ml/content-admin-stores.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function products() {
        $this->requireAuth();
        $this->ensureCsrf();

        $connections = $this->getConnections();

        // ── Store filter from GET ──
        $selectedStoreId = $_GET['store'] ?? $_SESSION['ml_active_store_id'] ?? null;
        if ($selectedStoreId !== null && $selectedStoreId !== 'all') {
            $found = false;
            foreach ($connections as $c) {
                if ((int) $c->id === (int) $selectedStoreId && $c->is_active) {
                    $_SESSION['ml_active_store_id'] = (int) $selectedStoreId;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $selectedStoreId = $_SESSION['ml_active_store_id'] ?? null;
            }
        } elseif ($selectedStoreId === 'all') {
            $selectedStoreId = null;
        } else {
            $selectedStoreId = $_SESSION['ml_active_store_id'] ?? null;
        }

        $activeStore = null;
        $searchQuery = $_GET['q'] ?? '';

        if ($selectedStoreId) {
            foreach ($connections as $c) {
                if ((int) $c->id === (int) $selectedStoreId && $c->is_active) {
                    $activeStore = $c;
                    break;
                }
            }
        }

        $page_title = 'Productos en MercadoLibre';
        $page_description = 'Explora y gestiona los productos publicados en tu tienda de MercadoLibre.';
        $current_nav = 'products';

        $estandarCount = 0;
        if ($selectedStoreId) {
            $db = $this->db();
            $st = $db->query('SELECT COUNT(*) as cnt FROM ml_products_cache WHERE ml_connection_id = ? AND sku IN (?, ?)');
            $st->execute([(int) $selectedStoreId, 'Estándar', 'Estandar']);
            $estandarCount = (int) ($st->fetch()->cnt ?? 0);
        }

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connections,
            'active_store_id' => $selectedStoreId,
            'active_store' => $activeStore,
            'search_query' => $searchQuery,
            'status_filter' => $_GET['status'] ?? '',
            'async_products' => true,
            'estandar_count' => $estandarCount,
        ];

        $view_content = __DIR__ . '/../Views/ml/content-products.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function productsData() {
        $this->requireAuth();
        header('Content-Type: application/json');

        // Allow store switch via GET param for AJAX
        $storeOverride = $_GET['store'] ?? null;
        if ($storeOverride !== null && $storeOverride !== '') {
            $connections = $this->getConnections();
            foreach ($connections as $c) {
                if ((int) $c->id === (int) $storeOverride && $c->is_active) {
                    $_SESSION['ml_active_store_id'] = (int) $storeOverride;
                    break;
                }
            }
        }

        $activeStoreId = $_SESSION['ml_active_store_id'] ?? null;
        if (!$activeStoreId) {
            echo json_encode(['error' => 'No hay tienda seleccionada.']);
            exit;
        }

        $db = $this->db();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int) ($_GET['limit'] ?? 50)));
        $searchQuery = trim($_GET['q'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');

        $cache = new MlProductsCacheModel($db);
        $data = $cache->getProducts((int) $activeStoreId, $page, $limit, $searchQuery, $statusFilter);

        echo json_encode($data);
        exit;
    }

    public function exportXlsx() {
        $this->requireAuth();

        $userId = (int) $_SESSION['user_id'];
        $rateLimiter = new RateLimiter();
        $exportCheck = $rateLimiter->attempt($userId, 'export', $userId);

        if (!$exportCheck['allowed']) {
            $retryAfter = ceil($exportCheck['retry_after'] / 60);
            $_SESSION['error'] = "Demasiadas exportaciones recientes. Intenta nuevamente en {$retryAfter} minuto(s).";
            error_log("Rate limit exceeded for export: user_id={$userId}, retry_after={$exportCheck['retry_after']}s");
            header('Location: ' . $this->route('/connections/products'));
            exit;
        }

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $connections = $this->getConnections();
        $activeStoreId = $_SESSION['ml_active_store_id'] ?? null;
        $searchQuery = htmlspecialchars(trim($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8');
        $statusFilter = $_GET['status'] ?? '';

        $allFields = [
            'codigo'      => 'Código ML',
            'titulo'      => 'Título',
            'precio'      => 'Precio',
            'stock'       => 'Stock',
            'sku'         => 'SKU',
            'condicion'   => 'Condición',
            'tipo'        => 'Tipo',
            'enlace'      => 'Enlace',
            'categoria'   => 'Categoría',
            'atributos'   => 'Atributos',
            'variaciones' => 'Variaciones',
            'garantia'    => 'Garantía',
        ];

        $selectedFieldsRaw = isset($_GET['fields']) && is_array($_GET['fields']) ? $_GET['fields'] : [];
        $selectedFields = array_filter($selectedFieldsRaw, function($field) use ($allFields) {
            return isset($allFields[$field]);
        });

        if (empty($selectedFields)) {
            $selectedFields = array_keys($allFields);
        }

        $activeStore = null;

        foreach ($connections as $c) {
            if ((int) $c->id === (int) $activeStoreId && $c->is_active) {
                $activeStore = $c;
                break;
            }
        }

        if (!$activeStore) {
            $_SESSION['error'] = 'No hay tienda activa seleccionada.';
            header('Location: ' . $this->route('/connections/products'));
            exit;
        }

        $db = $this->db();
        $cache = new MlProductsCacheModel($db);
        $data = $cache->getAllForExport((int) $activeStoreId, $searchQuery, $statusFilter, ML_EXPORT_MAX_ITEMS);
        $items = $data['items'];

        if (empty($items)) {
            $_SESSION['error'] = 'No hay productos en caché para exportar. Espera al próximo sync automático.';
            header('Location: ' . $this->route('/connections/products'));
            exit;
        }

        $exportService = new ExportService($db);
        
        try {
            $spreadsheet = $exportService->exportProductsXlsx($items, $selectedFields, $allFields);

            $act = new ActivityService($db);
            $act->log((int) $_SESSION['user_id'], 'export', 'Exportó ' . count($items) . ' productos ML a XLSX');

            $exportService->outputXlsx($spreadsheet, 'ml_productos.xlsx');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . $this->route('/connections/products'));
        }
        exit;
    }

    public function productDetail($mlItemId = null) {
        $this->requireAuth();
        $this->ensureCsrf();

        if (!$mlItemId) {
            header('Location: ' . $this->route('/connections/products'));
            exit;
        }

        $activeStoreId = $_SESSION['ml_active_store_id'] ?? null;
        if (!$activeStoreId) {
            $_SESSION['error'] = 'No hay tienda seleccionada.';
            header('Location: ' . $this->route('/connections/products'));
            exit;
        }

        $db = $this->db();
        $cache = new MlProductsCacheModel($db);
        $product = $cache->getProduct((int) $activeStoreId, $mlItemId);

        if (!$product) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . $this->route('/connections/products'));
            exit;
        }

        // If this product is a variation, fetch its parent
        $parentProduct = null;
        if (!empty($product->parent_ml_item_id)) {
            $parentProduct = $cache->getProduct((int) $activeStoreId, $product->parent_ml_item_id);
        }

        // Fetch variations if this is a parent product
        $variations = $cache->getVariations((int) $activeStoreId, $mlItemId);

        $connections = $this->getConnections();
        $storeName = '';
        foreach ($connections as $c) {
            if ((int) $c->id === (int) $activeStoreId) {
                $storeName = $c->store_name ?? $c->ml_nickname ?? '';
                break;
            }
        }

        $page_title = $product->title;
        $page_description = 'Detalle del producto · ' . ($storeName ?: 'Sin tienda');
        $current_nav = 'products';

        $data = [
            'product' => $product,
            'parent_product' => $parentProduct,
            'variations' => $variations,
            'store_name' => $storeName,
            'csrf_token' => $_SESSION['csrf_token'],
        ];

        $view_content = __DIR__ . '/../Views/ml/content-product-detail.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function priceHub() {
        $_SESSION['info'] = 'El módulo de precios está deshabilitado.';
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }

    public function bulkPrices() {
        $_SESSION['info'] = 'El módulo de precios está deshabilitado.';
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }

    public function prices() {
        $_SESSION['info'] = 'El módulo de precios está deshabilitado.';
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }

    public function preparePrices() {
        http_response_code(503);
        echo json_encode(['error' => 'Las modificaciones de precios están deshabilitadas.']);
        exit;
    }

    public function startPriceBatch() {
        http_response_code(503);
        echo json_encode(['error' => 'Las modificaciones de precios están deshabilitadas.']);
        exit;
    }

    public function applyPriceBatch() {
        http_response_code(503);
        echo json_encode(['error' => 'Las modificaciones de precios están deshabilitadas.']);
        exit;
    }

    public function cancelPriceBatch() {
        http_response_code(503);
        echo json_encode(['error' => 'El módulo de precios está deshabilitado.']);
        exit;
    }

    private function cleanupTempFile() {
        if (!empty($_SESSION['price_upload_path'])) {
            $path = $_SESSION['price_upload_path'];
            unset($_SESSION['price_upload_path']);
            if (file_exists($path)) @unlink($path);
        }
    }

    public function priceHistory() {
        $_SESSION['info'] = 'El módulo de precios está deshabilitado.';
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }

    public function exportBatchXlsx($batchId = null) {
        $_SESSION['info'] = 'El módulo de precios está deshabilitado.';
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }

    public function exportPriceResults() {
        $_SESSION['info'] = 'El módulo de precios está deshabilitado.';
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }

    public function exportPriceTemplate() {
        $_SESSION['info'] = 'El módulo de precios está deshabilitado.';
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }

    private function extractField($item, $key) {
        switch ($key) {
            case 'codigo': return $item->id ?? '';
            case 'titulo': return $item->title ?? '';
            case 'precio': return $item->price ?? 0;
            case 'stock': return $item->available_quantity ?? 0;
            case 'sku': return $this->extractSku($item);
            case 'condicion': return $item->condition ?? '';
            case 'tipo': return $item->listing_type_id ?? '';
            case 'enlace': return $item->permalink ?? '';
            case 'categoria': return $item->category_id ?? '';
            case 'atributos': return $this->extractAttributes($item);
            case 'variaciones': return $this->extractVariations($item);
            case 'garantia': return $item->warranty ?? '';
            default: return '';
        }
    }

    private function extractSku($item) {
        return MlApiHelper::extractSku($item);
    }

    private function extractAttributes($item) {
        if (empty($item->attributes)) return '';
        $pairs = [];
        foreach ($item->attributes as $attr) {
            $name = trim($attr->name ?? '');
            $val = trim($attr->value_name ?? '');
            if ($name && $val !== '' && !in_array($attr->id, ['SELLER_SKU', 'SELLER_SKU_', 'SKU'])) {
                $pairs[] = $name . ': ' . $val;
            }
        }
        return implode(' | ', $pairs);
    }

    private function extractVariations($item) {
        if (empty($item->variations)) return '';
        $summaries = [];
        foreach ($item->variations as $v) {
            $parts = [];
            if (!empty($v->attribute_combinations)) {
                foreach ($v->attribute_combinations as $ac) {
                    $parts[] = $ac->value_name ?? '';
                }
            }
            $parts[] = 'Stock: ' . ($v->available_quantity ?? 0);
            $parts[] = '$' . ($v->price ?? 0);
            $summaries[] = implode(' / ', $parts);
        }
        return implode(' | ', $summaries);
    }

    private function countryToSiteId($country) {
        $map = [
            'AR' => 'MLA', 'BO' => 'MBO', 'BR' => 'MLB',
            'CL' => 'MLC', 'CO' => 'MCO', 'CR' => 'MCR',
            'DO' => 'MLD', 'EC' => 'MEC', 'GT' => 'MLG',
            'HN' => 'MLH', 'MX' => 'MLM', 'NI' => 'MLI',
            'PA' => 'MPA', 'PE' => 'MPE', 'PY' => 'MPY',
            'SV' => 'MLS', 'UY' => 'MLU', 'VE' => 'MLV',
        ];
        return $map[strtoupper($country)] ?? 'MLC';
    }

    private function makeOauthState($userId, $extra = '') {
        $random = bin2hex(random_bytes(16));
        $ts = time();
        $extraB64 = $extra ? rtrim(strtr(base64_encode($extra), '+/', '-_'), '=') : '';
        $payload = $userId . '.' . $ts . '.' . $random . ($extraB64 ? '.' . $extraB64 : '');
        $sig = hash_hmac('sha256', $payload, ENCRYPTION_KEY);
        return $payload . '.' . $sig;
    }

    private function verifyOauthState($state) {
        $parts = explode('.', $state);
        if (count($parts) < 4 || count($parts) > 5) return false;
        [$userId, $ts, $random, $sig] = $parts;
        $extraB64 = $parts[4] ?? '';
        $payload = $userId . '.' . $ts . '.' . $random . ($extraB64 ? '.' . $extraB64 : '');
        $expected = hash_hmac('sha256', $payload, ENCRYPTION_KEY);
        if (!hash_equals($expected, $sig)) return false;
        if (time() - (int) $ts > 600) return false;
        return ['user_id' => (int) $userId, 'extra' => $extraB64 ? base64_decode(strtr($extraB64, '-_', '+/')) : ''];
    }

    public function auditHub() {
        return $this->audit();
    }

    public function auditHistory() {
        $this->requireAuth();
        $this->ensureCsrf();

        $db = $this->db();
        $connections = $this->getConnections();

        $selectedStoreId = $_GET['store'] ?? 'all';
        $searchQuery = trim($_GET['q'] ?? '');
        $statusFilter = $_GET['status'] ?? 'all';

        $where = '1=1';
        $params = [];

        if ($selectedStoreId !== 'all') {
            $where .= ' AND a.ml_connection_id = ?';
            $params[] = (int) $selectedStoreId;
        }

        if ($searchQuery !== '') {
            $where .= ' AND a.batch_id LIKE ?';
            $params[] = '%' . $searchQuery . '%';
        }

        $stmt = $db->query(
            "SELECT a.batch_id, a.audit_type, MAX(a.audit_date) as audit_date, a.store_name, u.username as performed_by,
                    COUNT(*) as total_skus,
                    SUM(CASE WHEN a.diff_amount != 0 AND a.diff_amount IS NOT NULL THEN 1 ELSE 0 END) as mismatches,
                    SUM(CASE WHEN a.ml_item_id = '-' OR a.ml_item_id = '' THEN 1 ELSE 0 END) as not_found
             FROM auditorias a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE $where
             GROUP BY a.batch_id, a.audit_type, a.store_name, u.username
             ORDER BY MAX(a.audit_date) DESC"
        );
        $stmt->execute($params);
        $batches = $stmt->fetchAll();

        // ── Compute status for each batch ──
        $statusCounts = ['perfect' => 0, 'warn' => 0, 'danger' => 0];
        foreach ($batches as $b) {
            $total = (int) $b->total_skus;
            $m = (int) $b->mismatches;
            $nf = (int) $b->not_found;
            $hasIssues = $m > 0 || $nf > 0;
            $allFailed = $total > 0 && ($m >= $total || $nf >= $total);
            $status = $allFailed ? 'danger' : ($hasIssues ? 'warn' : 'perfect');
            $statusCounts[$status]++;
        }

        // ── Filter by status (computed) ──
        if ($statusFilter !== 'all') {
            $batches = array_filter($batches, function ($b) use ($statusFilter) {
                $total = (int) $b->total_skus;
                $m = (int) $b->mismatches;
                $nf = (int) $b->not_found;
                $hasIssues = $m > 0 || $nf > 0;
                $allFailed = $total > 0 && ($m >= $total || $nf >= $total);
                $status = $allFailed ? 'danger' : ($hasIssues ? 'warn' : 'perfect');
                return $status === $statusFilter;
            });
            $batches = array_values($batches);
        }

        $page_title = 'Historial de auditorías';
        $page_description = 'Todas las auditorías ejecutadas.';
        $current_nav = 'audit';

        $data = [
            'batches' => $batches,
            'csrf_token' => $_SESSION['csrf_token'],
            'type' => 'history',
            'connections' => $connections,
            'selected_store_id' => $selectedStoreId,
            'search_query' => $searchQuery,
            'status_filter' => $statusFilter,
            'status_counts' => $statusCounts,
            'is_admin' => !empty($_SESSION['is_admin']),
        ];

        $view_content = __DIR__ . '/../Views/ml/content-audit-history.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function auditHistoryData() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $db = $this->db();
        $connections = $this->getConnections();

        $selectedStoreId = $_GET['store'] ?? 'all';
        $searchQuery = trim($_GET['q'] ?? '');
        $statusFilter = $_GET['status'] ?? 'all';

        $where = '1=1';
        $params = [];

        if ($selectedStoreId !== 'all') {
            $where .= ' AND a.ml_connection_id = ?';
            $params[] = (int) $selectedStoreId;
        }

        if ($searchQuery !== '') {
            $where .= ' AND a.batch_id LIKE ?';
            $params[] = '%' . $searchQuery . '%';
        }

        $stmt = $db->query(
            "SELECT a.batch_id, a.audit_type, MAX(a.audit_date) as audit_date, a.store_name, u.username as performed_by,
                    COUNT(*) as total_skus,
                    SUM(CASE WHEN a.diff_amount != 0 AND a.diff_amount IS NOT NULL THEN 1 ELSE 0 END) as mismatches,
                    SUM(CASE WHEN a.ml_item_id = '-' OR a.ml_item_id = '' THEN 1 ELSE 0 END) as not_found
             FROM auditorias a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE $where
             GROUP BY a.batch_id, a.audit_type, a.store_name, u.username
             ORDER BY MAX(a.audit_date) DESC"
        );
        $stmt->execute($params);
        $batches = $stmt->fetchAll();

        // ── Compute status for each batch ──
        $statusCounts = ['perfect' => 0, 'warn' => 0, 'danger' => 0];
        foreach ($batches as $b) {
            $total = (int) $b->total_skus;
            $m = (int) $b->mismatches;
            $nf = (int) $b->not_found;
            $hasIssues = $m > 0 || $nf > 0;
            $allFailed = $total > 0 && ($m >= $total || $nf >= $total);
            $status = $allFailed ? 'danger' : ($hasIssues ? 'warn' : 'perfect');
            $statusCounts[$status]++;
        }

        // ── Filter by status (computed) ──
        if ($statusFilter !== 'all') {
            $batches = array_filter($batches, function ($b) use ($statusFilter) {
                $total = (int) $b->total_skus;
                $m = (int) $b->mismatches;
                $nf = (int) $b->not_found;
                $hasIssues = $m > 0 || $nf > 0;
                $allFailed = $total > 0 && ($m >= $total || $nf >= $total);
                $status = $allFailed ? 'danger' : ($hasIssues ? 'warn' : 'perfect');
                return $status === $statusFilter;
            });
            $batches = array_values($batches);
        }

        $typeLabels = ['prices' => 'Precios', 'stock' => 'Stock'];
        $tz = new DateTimeZone(date_default_timezone_get());
        $now = new DateTime();

        $groups = [];
        foreach ($batches as $b) {
            $date = new DateTime($b->audit_date, new DateTimeZone('UTC'));
            $date->setTimezone($tz);
            $diff = $now->diff($date);

            $isToday = $date->format('Y-m-d') === $now->format('Y-m-d');
            $yesterday = (new DateTime('-1 day'))->format('Y-m-d');
            $isYesterday = $date->format('Y-m-d') === $yesterday;

            if ($isToday) {
                $groupKey = 'Hoy';
                $hours = $diff->h; $minutes = $diff->i;
                if ($hours === 0 && $minutes === 0) $timeAgo = 'Hoy a las ' . $date->format('H:i');
                elseif ($hours === 0) $timeAgo = 'Hace ' . $minutes . ' min';
                else $timeAgo = 'Hace ' . $hours . ' horas';
            } elseif ($isYesterday) {
                $groupKey = 'Ayer';
                $timeAgo = 'Ayer ' . $date->format('H:i');
            } elseif ($diff->days < 7) {
                $groupKey = 'Esta semana';
                $timeAgo = 'Hace ' . $diff->days . ' días';
            } else {
                $groupKey = 'Anteriores';
                $timeAgo = $date->format('d/M');
            }

            $totalSkus = (int) $b->total_skus;
            $mismatchesCnt = (int) $b->mismatches;
            $notFoundCnt = (int) $b->not_found;
            $allFailed = $totalSkus > 0 && ($mismatchesCnt >= $totalSkus || $notFoundCnt >= $totalSkus);
            $hasIssues = ($mismatchesCnt > 0 || $notFoundCnt > 0);
            $dotColor = $allFailed ? '#ef4444' : ($hasIssues ? '#f59e0b' : '#10b981');

            $typeLabel = $typeLabels[$b->audit_type] ?? $b->audit_type;

            $groups[$groupKey][] = [
                'batch_id' => $b->batch_id,
                'store_name' => $b->store_name ?? 'Sin tienda',
                'audit_type' => $b->audit_type,
                'type_label' => $typeLabel,
                'total_skus' => $totalSkus,
                'mismatches' => $mismatchesCnt,
                'not_found' => $notFoundCnt,
                'all_failed' => $allFailed,
                'has_issues' => $hasIssues,
                'dot_color' => $dotColor,
                'performed_by' => $b->performed_by ?? '',
                'time_ago' => $timeAgo,
            ];
        }

        // Get connection names for the count display
        $connNames = [];
        foreach ($connections as $c) {
            $connNames[] = ['id' => (int) $c->id, 'name' => $c->store_name ?? 'Sin nombre'];
        }

        echo json_encode([
            'groups' => $groups,
            'status_counts' => $statusCounts,
            'connections' => $connNames,
        ]);
        exit;
    }

    public function audit() {
        $this->requireAuth();
        $this->ensureCsrf();

        $connections = $this->getConnections();
        $activeStoreId = $_SESSION['ml_active_store_id'] ?? null;
        $activeStore = null;

        foreach ($connections as $c) {
            if ((int) $c->id === (int) $activeStoreId && $c->is_active) {
                $activeStore = $c;
                break;
            }
        }

        $error = $_SESSION['audit_error'] ?? null;
        unset($_SESSION['audit_error']);

        // Últimas 5 auditorías agrupadas por batch
        $db = $this->db();
        $recentStmt = $db->query(
            'SELECT a.batch_id, a.audit_type, MAX(a.audit_date) as audit_date, a.store_name,
                    COUNT(*) as total_skus,
                    SUM(CASE WHEN a.diff_amount != 0 AND a.diff_amount IS NOT NULL THEN 1 ELSE 0 END) as mismatches,
                    SUM(CASE WHEN a.ml_item_id = \'-\' OR a.ml_item_id = \'\' THEN 1 ELSE 0 END) as not_found
             FROM auditorias a
             GROUP BY a.batch_id, a.audit_type, a.store_name
             ORDER BY MAX(a.audit_date) DESC
             LIMIT 5'
        );
        $recentStmt->execute();
        $recentAudits = $recentStmt->fetchAll();

        $page_title = 'Auditorías';
        $page_description = 'Contrasta datos de tu Excel con MercadoLibre.';
        $current_nav = 'audit';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'active_store' => $activeStore,
            'connections' => $connections,
            'active_store_id' => $activeStoreId,
            'error' => $error,
            'recent_audits' => $recentAudits,
        ];

        $view_content = __DIR__ . '/../Views/ml/content-audit-hub.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function auditPrices()   { $this->renderAuditPage('prices'); }
    public function auditStock()    { $this->renderAuditPage('stock'); }

    private function renderAuditPage($type) {
        $this->requireAuth();
        $this->ensureCsrf();

        $connections = $this->getConnections();
        $activeStoreId = $_SESSION['ml_active_store_id'] ?? null;
        $activeStore = null;
        foreach ($connections as $c) {
            if ((int) $c->id === (int) $activeStoreId && $c->is_active) {
                $activeStore = $c;
                break;
            }
        }

        $cacheCount = 0;
        $timeAgo = null;
        if ($activeStore) {
            $db = $this->db();
            $cache = new MlProductsCacheModel($db);
            $cacheCount = (int) $cache->getCount((int) $activeStore->id);

            $syncStatus = new MlSyncStatusModel($db);
            $sync = $syncStatus->get((int) $activeStore->id);
            if ($sync && $sync->last_sync_at) {
                $diff = time() - strtotime($sync->last_sync_at . ' UTC');
                if ($diff < 60) $timeAgo = 'hace momentos';
                elseif ($diff < 3600) $timeAgo = 'hace ' . floor($diff / 60) . ' min';
                elseif ($diff < 86400) $timeAgo = 'hace ' . floor($diff / 3600) . ' h';
                else $timeAgo = 'hace ' . floor($diff / 86400) . ' d';
            }
        }

        $configs = [
            'prices' => [
                'cardTitle'  => 'Subir archivo de precios',
                'endpoint'   => '/connections/compareAuditPrices',
                'filePrefix' => 'auditoria_precios_',
            ],
            'stock' => [
                'cardTitle'  => 'Subir archivo de stock',
                'endpoint'   => '/connections/compareAuditStock',
                'filePrefix' => 'auditoria_stock_',
            ],
        ];

        $error = $_SESSION['audit_error'] ?? null;
        unset($_SESSION['audit_error']);

        $page_title = $type === 'prices' ? 'Auditoría de precios' : 'Auditoría de stock';
        $page_description = $type === 'prices'
            ? 'Contrasta precios de tu Excel con MercadoLibre.'
            : 'Contrasta niveles de stock de tu Excel con MercadoLibre.';
        $current_nav = 'audit';

        $data = [
            'csrf_token'     => $_SESSION['csrf_token'],
            'type'           => $type,
            'audit_cfg'      => $configs[$type],
            'connections'    => $connections,
            'active_store'   => $activeStore,
            'active_store_id' => $activeStoreId,
            'cache_count'    => $cacheCount,
            'time_ago'       => $timeAgo,
            'error'          => $error,
        ];

        $view_content = __DIR__ . '/../Views/ml/content-audit-form.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function compareAuditPrices()  { $this->runCompareAudit('prices'); }
    public function compareAuditStock()   { $this->runCompareAudit('stock'); }

    public function auditBatchDetail($batchId = null) {
        $this->requireAuth();
        $this->ensureCsrf();

        if (!$batchId || !preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $batchId)) {
            header('Location: ' . URLROOT . '/connections/auditHistory');
            exit;
        }

        $db = $this->db();

        // Info del batch (resumen)
        $summaryStmt = $db->query(
            'SELECT a.batch_id, a.audit_type, MAX(a.audit_date) as audit_date,
                    a.store_name, u.username as performed_by,
                    COUNT(*) as total_skus,
                    SUM(CASE WHEN a.diff_amount != 0 AND a.diff_amount IS NOT NULL THEN 1 ELSE 0 END) as mismatches,
                    SUM(CASE WHEN a.ml_item_id = \'-\' OR a.ml_item_id = \'\' THEN 1 ELSE 0 END) as not_found
             FROM auditorias a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.batch_id = ?
             GROUP BY a.batch_id, a.audit_type, a.store_name, u.username'
        );
        $summaryStmt->execute([$batchId]);
        $summary = $summaryStmt->fetch();

        if (!$summary) {
            header('Location: ' . URLROOT . '/connections/auditHistory');
            exit;
        }

        // Filas individuales del batch
        $rowsStmt = $db->query(
            'SELECT a.*, c.ml_nickname
             FROM auditorias a
             LEFT JOIN ml_connections c ON a.ml_connection_id = c.id
             WHERE a.batch_id = ?
             ORDER BY a.sku ASC'
        );
        $rowsStmt->execute([$batchId]);
        $rows = $rowsStmt->fetchAll();

        $page_title = '';
        $page_description = '';
        $current_nav = 'audit';
        $data = [
            'summary' => $summary,
            'rows' => $rows,
            'csrf_token' => $_SESSION['csrf_token'],
            'type' => 'history',
            'is_admin' => !empty($_SESSION['is_admin']),
        ];
        $view_content = __DIR__ . '/../Views/ml/content-audit-batch-detail.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function auditBatchDownload($batchId = null) {
        $this->requireAuth();
        $db = $this->db();

        $summaryStmt = $db->query(
            'SELECT a.batch_id, a.audit_type, COUNT(*) as total_skus,
                    SUM(CASE WHEN a.diff_amount != 0 AND a.diff_amount IS NOT NULL THEN 1 ELSE 0 END) as mismatches,
                    SUM(CASE WHEN a.ml_item_id = \'-\' OR a.ml_item_id = \'\' THEN 1 ELSE 0 END) as not_found,
                    MAX(a.audit_date) as audit_date, a.store_name
             FROM auditorias a
             WHERE a.batch_id = ?
             GROUP BY a.batch_id, a.audit_type, a.store_name'
        );
        $summaryStmt->execute([$batchId]);
        $summary = $summaryStmt->fetch();

        if (!$summary) {
            http_response_code(404);
            echo json_encode(['error' => 'Batch no encontrado.']);
            exit;
        }

        $rowsStmt = $db->query(
            'SELECT a.*, c.ml_nickname
             FROM auditorias a
             LEFT JOIN ml_connections c ON a.ml_connection_id = c.id
             WHERE a.batch_id = ?
             ORDER BY a.sku ASC'
        );
        $rowsStmt->execute([$batchId]);
        $rows = $rowsStmt->fetchAll();

        $results = [];
        $auditService = new AuditService($db);

        if ($summary->audit_type === 'prices') {
            foreach ($rows as $r) {
                $results[] = [
                    'sku' => $r->sku,
                    'uploaded_price' => $r->prev_ml_price,
                    'ml_price' => $r->ml_price,
                    'item_id' => $r->ml_item_id,
                    'item_status' => '',
                    'diff_amount' => $r->diff_amount,
                    'diff_percent' => $r->diff_percent,
                    'result' => ($r->ml_item_id === '-' || $r->ml_item_id === '') ? 'No encontrado' : (($r->diff_amount != 0 && $r->diff_amount !== null) ? 'Diferencia' : 'Coincide'),
                ];
            }
        } elseif ($summary->audit_type === 'stock') {
            foreach ($rows as $r) {
                $results[] = [
                    'sku' => $r->sku,
                    'uploaded_stock' => $r->prev_ml_price,
                    'ml_stock' => $r->ml_price,
                    'item_id' => $r->ml_item_id,
                    'item_status' => '',
                    'diff_amount' => $r->diff_amount,
                    'result' => ($r->ml_item_id === '-' || $r->ml_item_id === '') ? 'No encontrado' : (($r->diff_amount != 0 && $r->diff_amount !== null) ? 'Diferencia' : 'Coincide'),
                ];
            }
        }

        require_once __DIR__ . '/../Helpers/XlsxWriter.php';
        $xlsx = new XlsxWriter();
        $xlsxHeaders = [
            'prices' => ['SKU', 'Item ID', 'Precio Subido', 'Precio ML', 'Diferencia $', 'Diferencia %', 'Resultado'],
            'stock' => ['SKU', 'Item ID', 'Stock Subido', 'Stock ML', 'Diferencia', 'Resultado'],
        ];
        $xlsx->setHeaders($xlsxHeaders[$summary->audit_type] ?? ['SKU', 'Item ID', 'Resultado']);
        $auditService->fillXlsx($summary->audit_type, $xlsx, $results);

        try {
            $name = 'auditoria_' . $summary->audit_type . '_' . date('Ymd_His');
            $tmpDir = ini_get('session.save_path') ?: sys_get_temp_dir();
            $filename = tempnam($tmpDir, $name) . '.xlsx';
            $xlsx->output($filename);

            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $name . '.xlsx"');
            header('Content-Length: ' . filesize($filename));

            $exportService = new ExportService($this->db());
            readfile($filename);
            unlink($filename);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }

    }

    private function runCompareAudit($type) {
        $this->requireAuth();
        set_time_limit(600);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }

        if (!isset($_FILES['audit_file']) || $_FILES['audit_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Error al subir el archivo.']);
            exit;
        }

        $tmpFile = $_FILES['audit_file']['tmp_name'];
        $originalName = $_FILES['audit_file']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $auditService = new AuditService();
        try {
            $rows = $auditService->readFile($tmpFile, $ext);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }

        $config = [
            'prices' => [
                'headerRegex' => '/sku|código|codigo|item|id|precio|price/i',
                'filePrefix' => 'auditoria_precios_',
                'errorRoute' => '/audit',
                'xlsxHeaders' => ['SKU', 'Item ID', 'Precio Subido', 'Precio ML', 'Diferencia $', 'Diferencia %', 'Resultado'],
            ],
            'stock' => [
                'headerRegex' => '/sku|código|codigo|item|id|stock|cantidad/i',
                'filePrefix' => 'auditoria_stock_',
                'errorRoute' => '/auditStock',
                'xlsxHeaders' => ['SKU', 'Item ID', 'Stock Subido', 'Stock ML', 'Diferencia', 'Resultado'],
            ],
        ];

        $cfg = $config[$type] ?? $config['prices'];

        $startRow = $auditService->detectHeaderRow($rows, $cfg['headerRegex']);

        // Detect column indices by header name (SKU, Precio/Stock, optional Código ML)
        $headerRow = $rows[max(0, $startRow - 1)] ?? [];
        $skuCol = 0;
        $valCol = 1;
        $mlIdCol = null;
        foreach ($headerRow as $i => $h) {
            $hl = mb_strtolower(trim((string) $h));
            if ($hl === 'sku' || strpos($hl, 'sku') === 0) {
                $skuCol = $i;
            } elseif ($type === 'prices' && ($hl === 'precio' || $hl === 'price')) {
                $valCol = $i;
            } elseif ($type === 'stock' && ($hl === 'stock' || $hl === 'cantidad' || $hl === 'quantity')) {
                $valCol = $i;
            } elseif (preg_match('/código.*(ml|mercadolibre)|(item|ml).*id/i', $hl)) {
                $mlIdCol = $i;
            }
        }

        $entries = $auditService->parseEntries($type, $rows, $startRow, $skuCol, $valCol, $mlIdCol);

        // For Estándar entries with an ML ID column, use the ML ID as key
        if ($mlIdCol !== null) {
            foreach ($entries as &$e) {
                if (in_array($e['sku'], ['Estándar', 'Estandar']) && !empty($e['ml_id'])) {
                    $e['sku'] = $e['ml_id'];
                }
            }
            unset($e);
        }

        if (empty($entries)) {
            http_response_code(400);
            echo json_encode(['error' => 'No se encontraron datos válidos en el archivo.']);
            exit;
        }

        $connections = $this->getConnections();
        $activeStoreId = $_SESSION['ml_active_store_id'] ?? null;
        $activeStore = null;

        foreach ($connections as $c) {
            if ((int) $c->id === (int) $activeStoreId && $c->is_active) {
                $activeStore = $c;
                break;
            }
        }

        if (!$activeStore) {
            http_response_code(400);
            echo json_encode(['error' => 'No hay tienda activa seleccionada.']);
            exit;
        }

        $db = $this->db();
        $stmt = $db->query(
            'SELECT sku, ml_item_id, price, available_quantity, status
             FROM ml_products_cache
             WHERE ml_connection_id = ? AND sku IS NOT NULL AND sku != \'\''
        );
        $stmt->execute([(int) $activeStore->id]);
        $skuMap = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row->sku;
            if (in_array($key, ['Estándar', 'Estandar', ''])) {
                $key = $row->ml_item_id;
            }
            if ($type === 'prices') {
                $skuMap[$key] = [
                    'item_id' => $row->ml_item_id,
                    'ml_price' => (float) $row->price,
                    'status' => $row->status,
                ];
            } else {
                $skuMap[$key] = [
                    'item_id' => $row->ml_item_id,
                    'ml_stock' => (int) $row->available_quantity,
                    'status' => $row->status,
                ];
            }
        }

        $results = $auditService->compare($type, $entries, $skuMap);

        // Local fallback: for not-found SKUs, search cache by title/sku
        // Skip Estándar/Estandar — can't be matched uniquely without ML ID column
        $foundFallback = 0;
        foreach ($results as &$r) {
            if ($r['item_id'] !== '-') continue;
            $sku = $r['sku'] ?? '';
            if (!$sku) continue;
            if (in_array($sku, ['Estándar', 'Estandar'])) continue;
            $stmt2 = $db->query(
                'SELECT sku, ml_item_id, price, available_quantity, status
                 FROM ml_products_cache
                 WHERE ml_connection_id = ? AND (title LIKE ? OR sku LIKE ?)
                 LIMIT 1'
            );
            $stmt2->execute([(int) $activeStore->id, '%' . $sku . '%', '%' . $sku . '%']);
            $match = $stmt2->fetch();
            if ($match) {
                if ($type === 'prices') {
                    $skuMap[$sku] = [
                        'item_id' => $match->ml_item_id,
                        'ml_price' => (float) $match->price,
                        'status' => $match->status,
                    ];
                } else {
                    $skuMap[$sku] = [
                        'item_id' => $match->ml_item_id,
                        'ml_stock' => (int) $match->available_quantity,
                        'status' => $match->status,
                    ];
                }
                $foundFallback++;
            }
        }
        unset($r);

        if ($foundFallback > 0) {
            $results = $auditService->compare($type, $entries, $skuMap);
        }

        $batchId = 'audit_' . bin2hex(random_bytes(8));
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $mlConnId = (int) $activeStore->id;
        $storeName = $activeStore->store_name ?? '';
        $auditDate = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        foreach ($results as $r) {
            $sku = $r['sku'];
            $mlItemId = $r['item_id'] ?? '';

            if ($type === 'prices') {
                $mlPrice = $r['ml_price'] ?? 0;
                $prevPrice = $r['uploaded_price'] ?? null;
                $diffAmount = $r['diff_amount'];
                $diffPercent = $r['diff_percent'];
            } elseif ($type === 'stock') {
                $mlPrice = $r['ml_stock'] ?? 0;
                $prevPrice = $r['uploaded_stock'] ?? null;
                $diffAmount = $r['diff_amount'] ?? 0;
                $diffPercent = null;
            }

            $stmt = $db->query('INSERT INTO auditorias (user_id, ml_connection_id, store_name, batch_id, audit_type, sku, ml_item_id, ml_price, prev_ml_price, diff_amount, diff_percent, audit_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $mlConnId, $storeName, $batchId, $type, $sku, $mlItemId, $mlPrice, $prevPrice, $diffAmount, $diffPercent, $auditDate]);
        }

        require_once __DIR__ . '/../Helpers/XlsxWriter.php';
        $xlsx = new XlsxWriter();
        $xlsx->setHeaders($cfg['xlsxHeaders']);
        $auditService->fillXlsx($type, $xlsx, $results);

        try {
            $tmpDir = ini_get('session.save_path') ?: sys_get_temp_dir();
            $name = $cfg['filePrefix'] . date('Ymd_His');
            $filename = tempnam($tmpDir, $name) . '.xlsx';
            $xlsx->output($filename);

            $act = new ActivityService($this->db());
            $labels = ['prices'=>'precios','stock'=>'stock'];
            $act->log((int) $_SESSION['user_id'], 'audit', "Ejecutó auditoría de {$labels[$type]}");

            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $name . '.xlsx"');
            header('Content-Length: ' . filesize($filename));

            $exportService = new ExportService($this->db());
            readfile($filename);
            unlink($filename);
            exit;
        } catch (Exception $e) {
            $_SESSION['audit_error'] = $e->getMessage();
            header('Location: ' . $this->route($cfg['errorRoute']));
        }
        exit;
    }

    public function selectstore($id) {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $this->route('/dashboard'));
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . $this->route('/dashboard'));
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID de tienda inválido.';
            header('Location: ' . $this->route('/dashboard'));
            exit;
        }

        $storeId = (int) $id;
        $userId = (int) $_SESSION['user_id'];

        $db = $this->db();
        $stmt = $db->query('SELECT id, store_name FROM ml_connections WHERE id = ? AND is_active = 1');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch();

        if (!$store) {
            $_SESSION['error'] = 'Tienda no encontrada o inactiva.';
            header('Location: ' . $this->route('/dashboard'));
            exit;
        }

        $_SESSION['ml_active_store_id'] = $storeId;
        $db->query('UPDATE users SET current_store_id = ? WHERE id = ?')->execute([$storeId, $userId]);
        $storeName = $store->store_name ?? 'Sin nombre';
        $_SESSION['success'] = "Tienda {$storeName} seleccionada correctamente.";
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }

    public function reauthorize($id) {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $this->route('/dashboard'));
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . $this->route('/dashboard'));
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID de tienda inválido.';
            header('Location: ' . $this->route('/dashboard'));
            exit;
        }

        $storeId = (int) $id;
        $userId = (int) $_SESSION['user_id'];
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $state = $this->makeOauthState($userId, $codeVerifier);

        $authUrl = ML_AUTH_URL . '/authorization'
            . '?response_type=code'
            . '&client_id=' . urlencode(MlConfigService::getAppId($this->db()))
            . '&redirect_uri=' . urlencode(ML_REDIRECT_URI)
            . '&state=' . urlencode($state)
            . '&scope=' . urlencode('read write offline_access')
            . '&code_challenge=' . urlencode($codeChallenge)
            . '&code_challenge_method=S256';

        $authUrlPopup = $authUrl . '&popup=1';

        $page_title = 'Reconectar tienda';
        $page_description = '';
        $current_nav = 'connections';
        $data = [
            'auth_url' => $authUrl,
            'auth_url_popup' => $authUrlPopup,
            'csrf_token' => $_SESSION['csrf_token'],
            'reauthorize' => true,
            'store_id' => $storeId,
            'ml_configured' => MlConfigService::areCredentialsConfigured($this->db()),
        ];
        $view_content = __DIR__ . '/../Views/ml/content-connect.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function notifications() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['topic']) || empty($input['resource'])) {
            http_response_code(400);
            exit;
        }

        $topic = $input['topic'];
        $resource = $input['resource'];

        error_log("ML Notification | topic: $topic | resource: $resource");

        http_response_code(200);
        exit;
    }

    public function disconnect($id) {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $storeId = (int) $id;
        $db = $this->db();
        $db->query('UPDATE ml_connections SET is_active = 0 WHERE id = ?')->execute([$storeId]);
        $db->query('UPDATE users SET current_store_id = NULL WHERE current_store_id = ?')->execute([$storeId]);

        if (isset($_SESSION['ml_active_store_id']) && (int)$_SESSION['ml_active_store_id'] === $storeId) {
            unset($_SESSION['ml_active_store_id']);
        }

        $a = new ActivityService($db);
        $a->log((int) $_SESSION['user_id'], 'store_disconnected', "Desactivó la tienda ID {$storeId}");
        $_SESSION['success'] = 'Tienda desactivada correctamente.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    public function reactivate($id) {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $storeId = (int) $id;
        $db = $this->db();

        // Refresh token if expired before reactivating
        $userId = (int) $_SESSION['user_id'];
        $authService = new MercadoLibreAuthService($db);
        $authService->tryRefreshToken($storeId, $userId);

        $db->query('UPDATE ml_connections SET is_active = 1 WHERE id = ?')->execute([$storeId]);

        $a = new ActivityService($db);
        $a->log($userId, 'store_reactivated', "Reactivó la tienda ID {$storeId}");
        $_SESSION['success'] = 'Tienda reactivada correctamente.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    public function deleteStore($id) {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $storeId = (int) $id;
        $db = $this->db();

        if (isset($_SESSION['ml_active_store_id']) && (int)$_SESSION['ml_active_store_id'] === $storeId) {
            unset($_SESSION['ml_active_store_id']);
        }

        $db->query('DELETE FROM ml_connections WHERE id = ?')->execute([$storeId]);

        $a = new ActivityService($db);
        $a->log((int) $_SESSION['user_id'], 'store_deleted', "Eliminó la tienda ID {$storeId}");
        $_SESSION['success'] = 'Tienda eliminada permanentemente.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    public function rename($id) {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $storeName = trim((string) ($_POST['store_name'] ?? ''));
        if (empty($storeName)) {
            $_SESSION['error'] = 'El nombre de la tienda es obligatorio.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $db = $this->db();
        $stmt = $db->query('SELECT store_name FROM ml_connections WHERE id = ?');
        $stmt->execute([(int) $id]);
        $oldName = $stmt->fetch()->store_name ?? '';
        $db->query('UPDATE ml_connections SET store_name = ? WHERE id = ?')->execute([$storeName, (int) $id]);

        $a = new ActivityService($db);
        $a->log((int) $_SESSION['user_id'], 'store_renamed', "Renombró tienda ID {$id}: '{$oldName}' → '{$storeName}'");
        $_SESSION['success'] = 'Tienda renombrada correctamente.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    public function refreshToken($id) {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $authService = new MercadoLibreAuthService($this->db());
        $result = $authService->tryRefreshToken((int) $id, $userId);

        $_SESSION[$result ? 'success' : 'error'] = $result
            ? 'Token renovado correctamente.'
            : 'No se pudo renovar el token.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    // ── Sync: Status ──
    public function syncStatus() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $sync = new MlSyncService($this->db());
        $statuses = $sync->getAllStatus();

        $result = [];
        foreach ($statuses as $s) {
            $result[] = [
                'connection_id' => (int) $s->ml_connection_id,
                'store_name' => $s->store_name ?? '',
                'status' => $s->status,
                'total_products' => (int) $s->total_products,
                'synced_products' => (int) $s->synced_products,
                'last_sync_at'  => $s->last_sync_at ? (new DateTime($s->last_sync_at, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z') : null,
                'next_sync_at'  => $s->status === 'syncing'
                    ? null
                    : (new DateTime('now', new DateTimeZone('UTC')))->setTimestamp((intdiv(time(), 900) + 1) * 900)->format('Y-m-d\TH:i:s\Z'),
                'last_error'    => $s->last_error,
            ];
        }

        echo json_encode($result);
        exit;
    }

    // ── Sync: Start full sync (admin only) ──
    public function syncStart($id = null) {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (empty($id) || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID no especificado.']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $sync = new MlSyncService($this->db());
        $result = $sync->startFullSync((int) $id, $userId);

        echo json_encode($result);
        exit;
    }

    // ── Sync: Process chunk (polling) ──
    public function syncChunk($id = null) {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (empty($id) || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID no especificado.']);
            exit;
        }

        $connectionId = (int) $id;
        $sync = new MlSyncService($this->db());
        $status = $sync->getStatus($connectionId);

        if ($status->status !== 'syncing') {
            echo json_encode(['ok' => false, 'error' => 'No hay sync en curso.']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $authService = new MercadoLibreAuthService($this->db());
        $store = $this->db()->query('SELECT id, ml_user_id FROM ml_connections WHERE id = ?')->execute([$connectionId])->fetch();
        $token = $authService->getTokenForStore($connectionId, $userId);
        if (!$token) {
            echo json_encode(['ok' => false, 'error' => 'Token no disponible.']);
            exit;
        }

        $chunkResult = $sync->processChunk($connectionId, $token);

        if ($chunkResult['done']) {
            $sync->finishSync($connectionId, $userId);
        }

        echo json_encode(['ok' => true] + $chunkResult);
        exit;
    }

    // ── Sync: Check auto-sync needed (self-healing) ──
    public function syncCheck() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $userId = (int) $_SESSION['user_id'];
        $db = $this->db();
        $stmt = $db->query('SELECT id FROM ml_connections WHERE is_active = 1');
        $stmt->execute();
        $stores = $stmt->fetchAll();

        $syncService = new MlSyncService($db);
        $needsSync = [];

        foreach ($stores as $store) {
            if ($syncService->needsSync((int) $store->id)) {
                $needsSync[] = (int) $store->id;
            }
        }

        echo json_encode(['needs_sync' => $needsSync]);
        exit;
    }

    // ── Sync: Cancel ──
    public function syncStop($id = null) {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (empty($id) || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID no especificado.']);
            exit;
        }

        $connectionId = (int) $id;
        $sync = new MlSyncService($this->db());
        $sync->finishSync($connectionId);

        echo json_encode(['ok' => true, 'message' => 'Sync cancelado.']);
        exit;
    }

    public function connect() {
        $this->requireAdmin();
        $this->ensureCsrf();

        if (!MlConfigService::areCredentialsConfigured($this->db())) {
            $_SESSION['error'] = 'Configura las credenciales de MercadoLibre en Configuración → MercadoLibre.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }
        if (empty(ML_AUTH_URL)) {
            $_SESSION['error'] = 'Configura ML_AUTH_URL en app/config/config.php (ej: https://auth.mercadolibre.cl)';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $authService = new MercadoLibreAuthService();
        $state = $authService->makeOauthState($userId);

        $authUrl = ML_AUTH_URL . '/authorization'
            . '?response_type=code'
            . '&client_id=' . urlencode(MlConfigService::getAppId($this->db()))
            . '&redirect_uri=' . urlencode(ML_REDIRECT_URI)
            . '&state=' . urlencode($state)
            . '&scope=' . urlencode('read write offline_access');

        $authUrlPopup = $authUrl . '&popup=1';

        $page_title = '';
        $page_description = '';
        $current_nav = 'ml-admin';
        $data = [
            'auth_url' => $authUrl,
            'auth_url_popup' => $authUrlPopup,
            'csrf_token' => $_SESSION['csrf_token'],
            'ml_configured' => MlConfigService::areCredentialsConfigured($this->db()),
        ];
        $view_content = __DIR__ . '/../Views/ml/content-connect.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function purgeAudits() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido.']);
            exit;
        }

        $db = $this->db();
        $stmt = $db->query('SELECT COUNT(*) as cnt FROM auditorias');
        $stmt->execute();
        $count = (int) $stmt->fetch()->cnt;

        $db->query('TRUNCATE TABLE auditorias')->execute();

        $a = new ActivityService($db);
        $a->log((int) $_SESSION['user_id'], 'logs_purged', "Purgó {$count} registros de auditorías");

        echo json_encode(['ok' => true, 'deleted' => $count]);
        exit;
    }

    public function deleteAuditBatch($batchId = null) {
        $this->requireAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
            exit;
        }

        if (!$batchId || !preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $batchId)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de lote inválido.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido.']);
            exit;
        }

        $db = $this->db();
        $stmt = $db->query('SELECT COUNT(*) as cnt FROM auditorias WHERE batch_id = ?');
        $stmt->execute([$batchId]);
        $count = (int) $stmt->fetch()->cnt;

        if ($count === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Lote no encontrado.']);
            exit;
        }

        $db->query('DELETE FROM auditorias WHERE batch_id = ?')->execute([$batchId]);

        $a = new ActivityService($db);
        $a->log((int) $_SESSION['user_id'], 'logs_purged', "Eliminó lote de auditoría {$batchId} ({$count} registros)");

        echo json_encode(['ok' => true, 'deleted' => $count]);
        exit;
    }

    public function callback() {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $rateLimiter = new RateLimiter();
        $callbackCheck = $rateLimiter->attempt('oauth_callback_' . $ipAddress, 'login', null);
        if (!$callbackCheck['allowed']) {
            $this->renderCallbackPage('error', 'Demasiadas solicitudes. Intenta nuevamente en unos minutos.', '');
            return;
        }

        if (empty($code)) {
            $this->renderCallbackPage('error', 'No se recibió el código de autorización.', '');
            return;
        }

        $authService = new MercadoLibreAuthService($this->db());
        $userId = $authService->verifyOauthState($state);
        if (!$userId) {
            $this->renderCallbackPage('error', 'Enlace inválido o expirado. Genera uno nuevo desde "Conectar nueva tienda".', '');
            return;
        }

        $result = $authService->exchangeCodeForToken($code);

        if ($result['code'] !== 200 || empty($result['body']->access_token)) {
            $error = $result['body']->error_description ?? 'Error al obtener token de MercadoLibre.';
            $this->renderCallbackPage('error', $error, '');
            return;
        }

        $tokenData = $result['body'];

        $api = new MercadoLibreApiService();
        $userInfo = $api->getUserInfo($tokenData->access_token);
        $mlUser = $userInfo['body'] ?? null;

        if ($userInfo['code'] !== 200 || !$mlUser) {
            $this->renderCallbackPage('error', 'No se pudo obtener información del usuario de MercadoLibre.', '');
            return;
        }

        $mlUserId = (int) $mlUser->id;
        $mlEmail = $mlUser->email ?? '';
        $mlNickname = $mlUser->nickname ?? '';
        $country = strtoupper($mlUser->country_id ?? '');

        $db = $this->db();
        $currentAppId = MlConfigService::getAppId($db);
        $stmt = $db->query('SELECT id FROM ml_connections WHERE ml_user_id = ? AND user_id = ?');
        $stmt->execute([$mlUserId, $userId]);
        $existing = $stmt->fetch();

        $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))->setTimestamp(time() + (int) ($tokenData->expires_in ?? 21600))->format('Y-m-d H:i:s');

        $encToken = MercadoLibreAuthService::encrypt($tokenData->access_token);
        $encRefresh = MercadoLibreAuthService::encrypt($tokenData->refresh_token ?? '');

        if ($existing) {
            $db->query('UPDATE ml_connections SET store_name = ?, ml_email = ?, ml_nickname = ?, country = ?, config_app_id = ?, access_token = ?, refresh_token = ?, token_expires_at = ?, is_active = 1, updated_at = NOW() WHERE id = ?')
               ->execute([$mlNickname, $mlEmail, $mlNickname, $country, $currentAppId, $encToken, $encRefresh, $expiresAt, $existing->id]);
            $storeId = (int) $existing->id;
        } else {
            $db->query('INSERT INTO ml_connections (user_id, store_name, ml_user_id, ml_email, ml_nickname, country, config_app_id, access_token, refresh_token, token_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$userId, $mlNickname, $mlUserId, $mlEmail, $mlNickname, $country, $currentAppId, $encToken, $encRefresh, $expiresAt]);
            $storeId = (int) $db->lastInsertId();
        }

        $_SESSION['ml_active_store_id'] = $storeId;
        $db->query('UPDATE users SET current_store_id = ? WHERE id = ?')->execute([$storeId, $userId]);

        $this->renderCallbackPage('success', 'Tienda conectada exitosamente.', $mlNickname);
    }

    private function renderCallbackPage($type, $message, $nickname) {
        $isSuccess = $type === 'success';
        $brand = BRAND_NAME;
        $accentBg = $isSuccess ? '#d1fae5' : '#fee2e2';
        $accentColor = $isSuccess ? '#059669' : '#dc2626';
        $icon = $isSuccess
            ? '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
            : '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
        require __DIR__ . '/../Views/ml/callback.php';
    }
}
