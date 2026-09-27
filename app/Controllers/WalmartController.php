<?php

/**
 * Módulo Walmart Chile Marketplace.
 * Rutas: /walmart/{metodo} (resueltas automáticamente por app/core/App.php).
 */
class WalmartController extends Controller {

    protected $baseRoute = '/walmart';

    protected function route($path = '') {
        return URLROOT . $this->baseRoute . $path;
    }

    protected function ensureCsrf() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    protected function getConnections() {
        $connModel = new WalmartConnectionModel($this->db());
        $rows = $connModel->findAll();
        foreach ($rows as $row) {
            $row->token_ok = $row->token_expires_at && strtotime($row->token_expires_at . ' UTC') > time();
        }
        return $rows;
    }

    /** Valida método POST + token CSRF; redirige a adminstores si falla. */
    protected function requirePostCsrf() {
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
    }

    // ── Páginas ──

    public function index() {
        $this->requireAuth();
        $this->ensureCsrf();

        $connModel = new WalmartConnectionModel($this->db());

        $page_title = 'Walmart - Panel';
        $page_description = 'Resumen de las tiendas Walmart Chile conectadas.';
        $current_nav = 'walmart';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connModel->findActive(),
            'is_admin' => !empty($_SESSION['is_admin']),
            'dry_run' => WALMART_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-dashboard.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function products() {
        $this->requireAuth();
        $this->ensureCsrf();

        $connModel = new WalmartConnectionModel($this->db());

        $page_title = 'Walmart - Productos';
        $page_description = 'Explora el catálogo sincronizado desde Walmart.';
        $current_nav = 'walmart-products';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connModel->findActive(),
            'dry_run' => WALMART_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-products.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function productDetail($connectionId = null, $sku = null) {
        $this->requireAuth();
        $this->ensureCsrf();

        if (!$connectionId || !$sku || !is_numeric($connectionId)) {
            header('Location: ' . $this->route('/products'));
            exit;
        }

        $db = $this->db();
        $cache = new WalmartProductsCacheModel($db);
        $product = $cache->getProduct((int) $connectionId, $sku);

        if (!$product) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . $this->route('/products'));
            exit;
        }

        $siblings = $cache->getSiblingsByTitle((int) $connectionId, $product->title, $sku);

        $product->product_type_es = (new WalmartProductTypeService($db))->translate($product->product_type);

        $family = null;
        try {
            $family = (new SkuFamilyService($db))->getFamily($sku);
            // Solo interesa mostrar la sección si hay algo más además del propio SKU.
            if (count($family['members']) <= 1) $family = null;
        } catch (Exception $e) {
            $family = null;
        }

        $connections = (new WalmartConnectionModel($db))->findActive();
        $storeName = '';
        foreach ($connections as $c) {
            if ((int) $c->id === (int) $connectionId) { $storeName = $c->store_name ?? ''; break; }
        }

        $page_title = $product->title;
        $page_description = 'Detalle del producto · ' . ($storeName ?: 'Sin tienda');
        $current_nav = 'walmart-products';

        $data = [
            'product' => $product,
            'siblings' => $siblings,
            'family' => $family,
            'store_name' => $storeName,
            'connection_id' => (int) $connectionId,
            'csrf_token' => $_SESSION['csrf_token'],
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-product-detail.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    // ── JSON: métricas y productos ──

    public function metrics($id = null) {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (empty($id) || !is_numeric($id)) {
            echo json_encode(['error' => 'ID no especificado.']);
            exit;
        }

        $db = $this->db();
        $conn = (new WalmartConnectionModel($db))->findById((int) $id);
        if (!$conn) {
            echo json_encode(['error' => 'Tienda no encontrada.']);
            exit;
        }
        if (!$conn->is_active) {
            echo json_encode(['total' => 0, 'published' => 0, 'low_stock' => 0]);
            exit;
        }

        $metrics = (new WalmartProductsCacheModel($db))->getMetrics((int) $id);
        $sync = (new WalmartSyncStatusModel($db))->get((int) $id);
        $metrics['store_id'] = (int) $id;
        $metrics['store_name'] = $conn->store_name;
        $metrics['sync_status'] = $sync->status ?? 'idle';
        $metrics['last_sync_at'] = $sync->last_sync_at ?? null;
        echo json_encode($metrics);
        exit;
    }

    public function productsData() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $storeId = isset($_GET['store']) && is_numeric($_GET['store']) ? (int) $_GET['store'] : 0;
        if (!$storeId) {
            echo json_encode(['error' => 'Tienda no especificada.']);
            exit;
        }

        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        $cache = new WalmartProductsCacheModel($this->db());
        $result = $cache->getProducts($storeId, $page, 20, $search, $status);

        $translator = new WalmartProductTypeService($this->db());
        foreach ($result['items'] as &$item) {
            $item['product_type'] = $translator->translate($item['product_type'] ?? null);
        }
        unset($item);

        echo json_encode($result);
        exit;
    }

    // ── JSON: sincronización ──

    public function syncStatus() {
        $this->requireAuth();
        header('Content-Type: application/json');
        $sync = new WalmartSyncService($this->db());
        echo json_encode(['stores' => $sync->getAllStatuses()]);
        exit;
    }

    public function syncStart($id = null) {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($id) || !is_numeric($id)) {
            echo json_encode(['ok' => false, 'error' => 'Petición inválida.']);
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $sync = new WalmartSyncService($this->db());
        echo json_encode($sync->startFullSync((int) $id, (int) $_SESSION['user_id']));
        exit;
    }

    public function syncChunk($id = null) {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($id) || !is_numeric($id)) {
            echo json_encode(['ok' => false, 'error' => 'Petición inválida.']);
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $sync = new WalmartSyncService($this->db());
        echo json_encode($sync->processChunk((int) $id, (int) $_SESSION['user_id']));
        exit;
    }

    public function syncStop($id = null) {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($id) || !is_numeric($id)) {
            echo json_encode(['ok' => false, 'error' => 'Petición inválida.']);
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $sync = new WalmartSyncService($this->db());
        echo json_encode($sync->stopSync((int) $id));
        exit;
    }

    public function connect() {
        $this->requireAdmin();
        $this->ensureCsrf();

        $editId = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editConn = null;
        if ($editId) {
            $editConn = (new WalmartConnectionModel($this->db()))->findById($editId);
        }

        $page_title = 'Walmart - Conectar tienda';
        $page_description = 'Conecta una cuenta de vendedor de Walmart Chile.';
        $current_nav = 'walmart-admin';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'edit' => $editConn,
            'default_base_url' => WALMART_API_BASE_URL,
            'dry_run' => WALMART_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-connect.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function adminstores() {
        $this->requireAdmin();
        $this->ensureCsrf();

        $page_title = 'Walmart - Gestión de Tiendas';
        $page_description = 'Administra las cuentas de Walmart Chile conectadas.';
        $current_nav = 'walmart-admin';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $this->getConnections(),
            'dry_run' => WALMART_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-admin-stores.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    // ── Gestión de conexiones (admin, POST + CSRF) ──

    public function saveConnection() {
        $this->requireAdmin();
        $this->requirePostCsrf();

        $editId = isset($_POST['edit_id']) && is_numeric($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
        $storeName = trim((string) ($_POST['store_name'] ?? ''));
        $clientId = trim((string) ($_POST['client_id'] ?? ''));
        $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
        $channelType = trim((string) ($_POST['channel_type'] ?? ''));
        $apiBaseUrl = trim((string) ($_POST['api_base_url'] ?? '')) ?: WALMART_API_BASE_URL;

        $backTo = $this->route('/connect' . ($editId ? '?edit=' . $editId : ''));

        if ($storeName === '' || $clientId === '') {
            $_SESSION['error'] = 'Nombre de tienda y Client ID son obligatorios.';
            header('Location: ' . $backTo);
            exit;
        }
        if (!$editId && $clientSecret === '') {
            $_SESSION['error'] = 'El Client Secret es obligatorio.';
            header('Location: ' . $backTo);
            exit;
        }
        if (!preg_match('#^https://[a-z0-9.\-]+$#i', rtrim($apiBaseUrl, '/'))) {
            $_SESSION['error'] = 'La URL base de la API no es válida (debe ser https://...).';
            header('Location: ' . $backTo);
            exit;
        }
        $apiBaseUrl = rtrim($apiBaseUrl, '/');

        $db = $this->db();
        $connModel = new WalmartConnectionModel($db);

        // En edición sin secret nuevo se conserva el cifrado existente
        $existing = $editId ? $connModel->findById($editId) : null;
        if ($editId && !$existing) {
            $_SESSION['error'] = 'La conexión a editar no existe.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }
        $secretPlain = $clientSecret !== ''
            ? $clientSecret
            : ($existing ? CryptoHelper::decrypt($existing->client_secret) : '');

        $auth = new WalmartAuthService($db);
        $test = $auth->testCredentials($clientId, $secretPlain, $apiBaseUrl, $channelType ?: null);
        if (!$test['ok']) {
            $_SESSION['error'] = 'No se guardó la conexión: ' . $test['error'];
            header('Location: ' . $backTo);
            exit;
        }

        $encryptedSecret = CryptoHelper::encrypt($secretPlain);
        $userId = (int) $_SESSION['user_id'];
        $a = new ActivityService($db);

        if ($editId) {
            $connModel->updateCredentials($editId, $clientId, $encryptedSecret, $channelType ?: null, $apiBaseUrl);
            if ($storeName !== $existing->store_name) {
                $connModel->rename($editId, $storeName);
            }
            $a->log($userId, 'walmart_store_updated', "Actualizó credenciales de la tienda Walmart ID {$editId} ({$storeName})");
            $_SESSION['success'] = 'Conexión actualizada y verificada correctamente.';
        } else {
            $dup = $connModel->findByClientId($clientId);
            if ($dup) {
                $_SESSION['error'] = 'Ya existe una conexión con ese Client ID.';
                header('Location: ' . $backTo);
                exit;
            }
            $newId = $connModel->create($userId, $storeName, $clientId, $encryptedSecret, $channelType ?: null, $apiBaseUrl);
            $a->log($userId, 'walmart_store_connected', "Conectó la tienda Walmart '{$storeName}' (ID {$newId})");
            $_SESSION['success'] = 'Tienda Walmart conectada y verificada correctamente.';
        }

        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    public function testConnection($id = null) {
        $this->requireAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }
        if (empty($id) || !is_numeric($id)) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            exit;
        }

        $auth = new WalmartAuthService($this->db());
        $token = $auth->getToken((int) $id, true);
        echo json_encode($token
            ? ['ok' => true, 'message' => 'Conexión verificada: token obtenido correctamente.']
            : ['ok' => false, 'error' => 'Walmart rechazó las credenciales o no respondió. Revisa el Client ID/Secret.']);
        exit;
    }

    public function disconnect($id) {
        $this->requireAdmin();
        $this->requirePostCsrf();

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $db = $this->db();
        (new WalmartConnectionModel($db))->setActive((int) $id, false);
        (new ActivityService($db))->log((int) $_SESSION['user_id'], 'walmart_store_disconnected', "Desactivó la tienda Walmart ID {$id}");
        $_SESSION['success'] = 'Tienda desactivada correctamente.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    public function reactivate($id) {
        $this->requireAdmin();
        $this->requirePostCsrf();

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $db = $this->db();
        (new WalmartConnectionModel($db))->setActive((int) $id, true);
        (new ActivityService($db))->log((int) $_SESSION['user_id'], 'walmart_store_reactivated', "Reactivó la tienda Walmart ID {$id}");
        $_SESSION['success'] = 'Tienda reactivada correctamente.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    public function deleteStore($id) {
        $this->requireAdmin();
        $this->requirePostCsrf();

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $db = $this->db();
        (new WalmartConnectionModel($db))->delete((int) $id);
        (new ActivityService($db))->log((int) $_SESSION['user_id'], 'walmart_store_deleted', "Eliminó la tienda Walmart ID {$id}");
        $_SESSION['success'] = 'Tienda eliminada permanentemente.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    public function rename($id) {
        $this->requireAdmin();
        $this->requirePostCsrf();

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $storeName = trim((string) ($_POST['store_name'] ?? ''));
        if ($storeName === '') {
            $_SESSION['error'] = 'El nombre de la tienda es obligatorio.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }

        $db = $this->db();
        $connModel = new WalmartConnectionModel($db);
        $oldName = $connModel->findStoreName((int) $id);
        $connModel->rename((int) $id, $storeName);
        (new ActivityService($db))->log((int) $_SESSION['user_id'], 'walmart_store_renamed', "Renombró tienda Walmart ID {$id}: '{$oldName}' → '{$storeName}'");
        $_SESSION['success'] = 'Tienda renombrada correctamente.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    // ── Actualización masiva de precios/stock ──

    public function updates() {
        $this->requireAuth();
        $this->ensureCsrf();

        $db = $this->db();
        $service = new WalmartUpdateService($db);
        $pending = $service->restoreBatchFromDb((int) $_SESSION['user_id']);

        $page_title = 'Walmart - Actualizar precios y stock';
        $page_description = 'Sube un archivo con SKU y valor para actualizar en Walmart.';
        $current_nav = 'walmart-updates';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => (new WalmartConnectionModel($db))->findActive(),
            'pending_batch' => $pending ? [
                'batch_id' => $pending['batch_id'],
                'update_type' => $pending['update_type'],
                'walmart_connection_id' => $pending['walmart_connection_id'],
                'total' => $pending['total'],
                'processed' => $pending['processed'],
                'ok' => $pending['ok'],
                'errors' => $pending['errors'],
            ] : null,
            'dry_run' => WALMART_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-updates.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function updateHistory() {
        $this->requireAuth();
        $this->ensureCsrf();

        $page_title = 'Walmart - Historial de actualizaciones';
        $page_description = 'Batches de precios y stock ejecutados contra Walmart.';
        $current_nav = 'walmart-history';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'is_admin' => !empty($_SESSION['is_admin']),
            'dry_run' => WALMART_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-update-history.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function updateHistoryData() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $type = trim((string) ($_GET['type'] ?? ''));

        $service = new WalmartUpdateService($this->db());
        echo json_encode($service->fetchHistory((int) $_SESSION['user_id'], !empty($_SESSION['is_admin']), $page, 10, $type));
        exit;
    }

    public function updateBatchLogs($batchId = null) {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (empty($batchId) || !preg_match('/^[a-f0-9]{16}$/', $batchId)) {
            echo json_encode(['error' => 'Batch inválido.']);
            exit;
        }

        $service = new WalmartUpdateService($this->db());
        echo json_encode(['logs' => $service->fetchBatchLogs($batchId, (int) $_SESSION['user_id'], !empty($_SESSION['is_admin']))]);
        exit;
    }

    public function prepareUpdate() {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $storeId = isset($_POST['store_id']) && is_numeric($_POST['store_id']) ? (int) $_POST['store_id'] : 0;
        $type = ($_POST['update_type'] ?? '') === 'stock' ? 'stock' : 'price';

        $conn = (new WalmartConnectionModel($this->db()))->findById($storeId);
        if (!$conn || !$conn->is_active) {
            echo json_encode(['ok' => false, 'error' => 'Tienda inválida o inactiva.']);
            exit;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo.']);
            exit;
        }
        $file = $_FILES['file'];
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'El archivo excede 10 MB.']);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'])) {
            echo json_encode(['ok' => false, 'error' => 'Formato no soportado. Usa .xlsx o .csv.']);
            exit;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', 'application/octet-stream',
            'text/plain', 'text/csv', 'application/csv',
        ];
        if (!in_array($mime, $allowedMimes)) {
            echo json_encode(['ok' => false, 'error' => 'Tipo de archivo no permitido (' . $mime . ').']);
            exit;
        }

        try {
            $service = new WalmartUpdateService($this->db());
            $rows = $service->readRows($file['tmp_name'], $ext);
            $parsed = $service->parseEntries($rows, $type);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }

        if (empty($parsed['entries'])) {
            echo json_encode([
                'ok' => false,
                'error' => 'El archivo no contiene filas válidas.',
                'validation_errors' => $parsed['validation_errors'],
            ]);
            exit;
        }

        $batch = $service->createBatch((int) $_SESSION['user_id'], $storeId, $type, $parsed['entries']);

        if (!empty($parsed['validation_errors'])) {
            try {
                (new NotificationService($this->db()))->notifyImportWarning((int) $_SESSION['user_id'], null, count($parsed['validation_errors']));
            } catch (Exception $e) {}
        }

        echo json_encode([
            'ok' => true,
            'batch_id' => $batch['batch_id'],
            'update_type' => $type,
            'total' => $batch['total'],
            'validation_errors' => $parsed['validation_errors'],
            'preview' => array_slice($parsed['entries'], 0, 20),
        ]);
        exit;
    }

    public function applyUpdateBatch() {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $db = $this->db();
        $service = new WalmartUpdateService($db);
        $batch = $service->restoreBatchFromDb($userId);
        if (!$batch) {
            echo json_encode(['ok' => false, 'error' => 'No hay un batch pendiente.']);
            exit;
        }

        $result = $service->processNextChunk($userId, $batch, 10);

        if (!empty($result['error'])) {
            echo json_encode(['ok' => false, 'error' => $result['error'], 'done' => true]);
            exit;
        }

        if ($result['done']) {
            $service->deleteBatchFromDb($userId);
            $typeLabel = $batch['update_type'] === 'stock' ? 'stock' : 'precios';
            try {
                (new NotificationService($db))->notifyPriceBatchDone($userId, null, $result['ok'], $result['errors']);
            } catch (Exception $e) {}
            try {
                (new ActivityService($db))->log($userId, 'walmart_bulk_update', "Batch de {$typeLabel} Walmart {$batch['batch_id']}: {$result['ok']} OK, {$result['errors']} errores de {$result['total']}");
            } catch (Exception $e) {}
        }

        echo json_encode([
            'ok' => true,
            'done' => $result['done'],
            'processed' => $result['processed'],
            'total' => $result['total'],
            'ok_count' => $result['ok'],
            'error_count' => $result['errors'],
            'results' => $result['results'],
        ]);
        exit;
    }

    public function cancelUpdateBatch() {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $service = new WalmartUpdateService($this->db());
        $service->deleteBatchFromDb($userId);
        try {
            (new ActivityService($this->db()))->log($userId, 'walmart_bulk_update', 'Canceló el batch de actualización Walmart pendiente');
        } catch (Exception $e) {}
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Auditorías ──

    public function auditHub() {
        $this->requireAuth();
        $this->ensureCsrf();

        $db = $this->db();
        $connections = (new WalmartConnectionModel($db))->findActive();

        $recentStmt = $db->query(
            'SELECT batch_id, audit_type, MAX(audit_date) as audit_date, store_name,
                    COUNT(*) as total_skus,
                    SUM(CASE WHEN diff_amount != 0 AND diff_amount IS NOT NULL THEN 1 ELSE 0 END) as mismatches,
                    SUM(CASE WHEN wpid = \'-\' OR wpid IS NULL OR wpid = \'\' THEN 1 ELSE 0 END) as not_found
             FROM auditorias_walmart
             GROUP BY batch_id, audit_type, store_name
             ORDER BY MAX(audit_date) DESC
             LIMIT 5'
        );
        $recentStmt->execute();

        $page_title = 'Walmart - Auditorías';
        $page_description = 'Contrasta datos de tu Excel con la caché de Walmart.';
        $current_nav = 'walmart-audit';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connections,
            'recent_audits' => $recentStmt->fetchAll(),
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-audit-hub.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function auditPrices() { $this->renderAuditPage('price'); }
    public function auditStock()  { $this->renderAuditPage('stock'); }

    private function renderAuditPage($type) {
        $this->requireAuth();
        $this->ensureCsrf();

        $db = $this->db();
        $connections = (new WalmartConnectionModel($db))->findActive();
        $activeStoreId = $_GET['store'] ?? ($_SESSION['walmart_active_store_id'] ?? ($connections[0]->id ?? null));
        $_SESSION['walmart_active_store_id'] = $activeStoreId;
        $activeStore = null;
        foreach ($connections as $c) {
            if ((int) $c->id === (int) $activeStoreId) { $activeStore = $c; break; }
        }

        $cacheCount = 0;
        $timeAgo = null;
        if ($activeStore) {
            $cacheCount = (new WalmartProductsCacheModel($db))->getCount((int) $activeStore->id);
            $sync = (new WalmartSyncStatusModel($db))->get((int) $activeStore->id);
            if ($sync && $sync->last_sync_at) {
                $diff = time() - strtotime($sync->last_sync_at . ' UTC');
                if ($diff < 60) $timeAgo = 'hace momentos';
                elseif ($diff < 3600) $timeAgo = 'hace ' . floor($diff / 60) . ' min';
                elseif ($diff < 86400) $timeAgo = 'hace ' . floor($diff / 3600) . ' h';
                else $timeAgo = 'hace ' . floor($diff / 86400) . ' d';
            }
        }

        $configs = [
            'price' => ['cardTitle' => 'Subir archivo de precios', 'endpoint' => '/walmart/compareAuditPrices', 'filePrefix' => 'auditoria_walmart_precios_'],
            'stock' => ['cardTitle' => 'Subir archivo de stock', 'endpoint' => '/walmart/compareAuditStock', 'filePrefix' => 'auditoria_walmart_stock_'],
        ];

        $error = $_SESSION['wm_audit_error'] ?? null;
        unset($_SESSION['wm_audit_error']);

        $page_title = $type === 'price' ? 'Walmart - Auditoría de precios' : 'Walmart - Auditoría de stock';
        $page_description = $type === 'price'
            ? 'Contrasta precios de tu Excel con la caché de Walmart.'
            : 'Contrasta stock de tu Excel con la caché de Walmart.';
        $current_nav = 'walmart-audit';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'type' => $type,
            'audit_cfg' => $configs[$type],
            'connections' => $connections,
            'active_store' => $activeStore,
            'active_store_id' => $activeStoreId,
            'cache_count' => $cacheCount,
            'time_ago' => $timeAgo,
            'error' => $error,
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-audit-form.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function compareAuditPrices() { $this->runCompareAudit('price'); }
    public function compareAuditStock()  { $this->runCompareAudit('stock'); }

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

        $storeId = (int) ($_POST['store_id'] ?? $_SESSION['walmart_active_store_id'] ?? 0);
        $connections = (new WalmartConnectionModel($this->db()))->findActive();
        $activeStore = null;
        foreach ($connections as $c) {
            if ((int) $c->id === $storeId) { $activeStore = $c; break; }
        }
        if (!$activeStore) {
            http_response_code(400);
            echo json_encode(['error' => 'No hay tienda Walmart activa seleccionada.']);
            exit;
        }

        $tmpFile = $_FILES['audit_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['audit_file']['name'], PATHINFO_EXTENSION));

        $auditService = new WalmartAuditService();
        try {
            $rows = $auditService->readFile($tmpFile, $ext);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }

        $config = [
            'price' => [
                'headerRegex' => '/sku|código|codigo|item|precio|price/i',
                'filePrefix' => 'auditoria_walmart_precios_',
                'xlsxHeaders' => ['SKU Subido', 'SKU Encontrado', 'WPID', 'Precio Subido', 'Precio Walmart', 'Diferencia $', 'Diferencia %', 'Resultado', 'Método'],
            ],
            'stock' => [
                'headerRegex' => '/sku|código|codigo|item|stock|cantidad/i',
                'filePrefix' => 'auditoria_walmart_stock_',
                'xlsxHeaders' => ['SKU Subido', 'SKU Encontrado', 'WPID', 'Stock Subido', 'Stock Walmart', 'Diferencia', 'Resultado', 'Método'],
            ],
        ];
        $cfg = $config[$type];

        $startRow = $auditService->detectHeaderRow($rows, $cfg['headerRegex']);
        $headerRow = $rows[max(0, $startRow - 1)] ?? [];
        $skuCol = 0;
        $valCol = 1;
        foreach ($headerRow as $i => $h) {
            $hl = mb_strtolower(trim((string) $h));
            if ($hl === 'sku' || strpos($hl, 'sku') === 0) $skuCol = $i;
            elseif ($type === 'price' && in_array($hl, ['precio', 'price'])) $valCol = $i;
            elseif ($type === 'stock' && in_array($hl, ['stock', 'cantidad', 'quantity'])) $valCol = $i;
        }

        $entries = $auditService->parseEntries($type, $rows, $startRow, $skuCol, $valCol);
        if (empty($entries)) {
            http_response_code(400);
            echo json_encode(['error' => 'No se encontraron datos válidos en el archivo.']);
            exit;
        }

        $cacheModel = new WalmartProductsCacheModel($this->db());
        $cacheRows = $cacheModel->getAllForAudit((int) $activeStore->id);
        $skuMap = $auditService->buildSkuMapFromCache($type, $cacheRows);

        $familyService = new SkuFamilyService($this->db());
        $shortcodeIndex = $familyService->buildShortcodeIndex($cacheRows);

        $results = $auditService->compare($type, $entries, $skuMap, $familyService, $shortcodeIndex);

        // Último recurso: búsqueda difusa por título/SKU parcial para lo que
        // ni el match exacto ni el de código corto lograron resolver.
        foreach ($results as &$r) {
            if ($r['wpid'] !== '-') continue;
            $match = $cacheModel->findFuzzy((int) $activeStore->id, $r['sku']);
            if (!$match) continue;

            $walmartValue = $type === 'price' ? (float) $match->price : (int) $match->available_quantity;
            $diff = $r['uploaded_value'] - $walmartValue;
            $diffPercent = ($type === 'price' && $walmartValue > 0) ? round(($diff / $walmartValue) * 100, 2) : null;

            $r['matched_sku'] = $match->sku;
            $r['match_method'] = 'fuzzy';
            $r['walmart_value'] = $walmartValue;
            $r['wpid'] = $match->wpid;
            $r['status'] = $match->published_status;
            $r['diff_amount'] = $diff;
            $r['diff_percent'] = $diffPercent;
            $r['synced_at'] = $match->synced_at;
            $r['result'] = ($diff == 0) ? 'Coincide' : 'Diferencia';
        }
        unset($r);

        $batchId = 'waudit_' . bin2hex(random_bytes(8));
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $auditDate = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $db = $this->db();
        foreach ($results as $r) {
            $stmt = $db->query(
                'INSERT INTO auditorias_walmart
                 (user_id, walmart_connection_id, store_name, batch_id, audit_type, sku, matched_sku, match_method, wpid, walmart_value, uploaded_value, diff_amount, diff_percent, audit_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId, (int) $activeStore->id, $activeStore->store_name ?? '', $batchId, $type,
                $r['sku'], $r['matched_sku'] ?? null, $r['match_method'] ?? 'exact', $r['wpid'], $r['walmart_value'], $r['uploaded_value'],
                $r['diff_amount'], $r['diff_percent'], $auditDate,
            ]);
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

            try {
                (new ActivityService($db))->log($userId, 'walmart_audit', 'Ejecutó auditoría Walmart de ' . ($type === 'price' ? 'precios' : 'stock'));
            } catch (Exception $e) {}

            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $name . '.xlsx"');
            header('Content-Length: ' . filesize($filename));
            readfile($filename);
            unlink($filename);
            exit;
        } catch (Exception $e) {
            $_SESSION['wm_audit_error'] = $e->getMessage();
            header('Location: ' . $this->route($type === 'price' ? '/auditPrices' : '/auditStock'));
            exit;
        }
    }

    /** Verificación puntual en vivo de un SKU contra la API de Walmart (AJAX, bajo demanda desde el detalle del batch). */
    public function auditVerifyLive() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $sku = trim((string) ($_GET['sku'] ?? ''));
        $type = trim((string) ($_GET['type'] ?? 'price'));
        $connectionId = (int) ($_GET['connection_id'] ?? 0);

        if ($sku === '' || !$connectionId) {
            echo json_encode(['error' => 'Parámetros inválidos.']);
            exit;
        }

        $auditService = new WalmartAuditService();
        $api = new WalmartApiService($this->db());
        $value = $auditService->verifyLive($connectionId, $sku, $type, $api);

        echo json_encode(['ok' => $value !== null, 'value' => $value]);
        exit;
    }

    public function auditHistory() {
        $this->requireAuth();
        $this->ensureCsrf();

        $connections = (new WalmartConnectionModel($this->db()))->findActive();

        $page_title = 'Walmart - Historial de auditorías';
        $page_description = 'Todas las auditorías Walmart ejecutadas.';
        $current_nav = 'walmart-audit';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connections,
            'selected_store_id' => $_GET['store'] ?? 'all',
            'search_query' => trim($_GET['q'] ?? ''),
            'status_filter' => $_GET['status'] ?? 'all',
            'is_admin' => !empty($_SESSION['is_admin']),
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-audit-history.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function auditHistoryData() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $db = $this->db();
        $selectedStoreId = $_GET['store'] ?? 'all';
        $searchQuery = trim($_GET['q'] ?? '');
        $statusFilter = $_GET['status'] ?? 'all';

        $where = '1=1';
        $params = [];
        if ($selectedStoreId !== 'all') { $where .= ' AND walmart_connection_id = ?'; $params[] = (int) $selectedStoreId; }
        if ($searchQuery !== '') { $where .= ' AND batch_id LIKE ?'; $params[] = '%' . $searchQuery . '%'; }

        $stmt = $db->query(
            "SELECT batch_id, audit_type, MAX(audit_date) as audit_date, store_name, u.username as performed_by,
                    COUNT(*) as total_skus,
                    SUM(CASE WHEN diff_amount != 0 AND diff_amount IS NOT NULL THEN 1 ELSE 0 END) as mismatches,
                    SUM(CASE WHEN wpid = '-' OR wpid IS NULL OR wpid = '' THEN 1 ELSE 0 END) as not_found
             FROM auditorias_walmart a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE $where
             GROUP BY batch_id, audit_type, store_name, u.username
             ORDER BY MAX(audit_date) DESC"
        );
        $stmt->execute($params);
        $batches = $stmt->fetchAll();

        $statusCounts = ['perfect' => 0, 'warn' => 0, 'danger' => 0];
        foreach ($batches as $b) {
            $total = (int) $b->total_skus; $m = (int) $b->mismatches; $nf = (int) $b->not_found;
            $hasIssues = $m > 0 || $nf > 0;
            $allFailed = $total > 0 && ($m >= $total || $nf >= $total);
            $status = $allFailed ? 'danger' : ($hasIssues ? 'warn' : 'perfect');
            $statusCounts[$status]++;
        }

        if ($statusFilter !== 'all') {
            $batches = array_values(array_filter($batches, function ($b) use ($statusFilter) {
                $total = (int) $b->total_skus; $m = (int) $b->mismatches; $nf = (int) $b->not_found;
                $hasIssues = $m > 0 || $nf > 0;
                $allFailed = $total > 0 && ($m >= $total || $nf >= $total);
                $status = $allFailed ? 'danger' : ($hasIssues ? 'warn' : 'perfect');
                return $status === $statusFilter;
            }));
        }

        $typeLabels = ['price' => 'Precios', 'stock' => 'Stock'];
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
                $timeAgo = ($diff->h === 0 && $diff->i === 0) ? 'Hoy a las ' . $date->format('H:i') : ($diff->h === 0 ? 'Hace ' . $diff->i . ' min' : 'Hace ' . $diff->h . ' horas');
            } elseif ($isYesterday) {
                $groupKey = 'Ayer'; $timeAgo = 'Ayer ' . $date->format('H:i');
            } elseif ($diff->days < 7) {
                $groupKey = 'Esta semana'; $timeAgo = 'Hace ' . $diff->days . ' días';
            } else {
                $groupKey = 'Anteriores'; $timeAgo = $date->format('d/M');
            }

            $total = (int) $b->total_skus; $m = (int) $b->mismatches; $nf = (int) $b->not_found;
            $allFailed = $total > 0 && ($m >= $total || $nf >= $total);
            $hasIssues = ($m > 0 || $nf > 0);
            $dotColor = $allFailed ? '#ef4444' : ($hasIssues ? '#f59e0b' : '#10b981');

            $groups[$groupKey][] = [
                'batch_id' => $b->batch_id,
                'store_name' => $b->store_name ?? 'Sin tienda',
                'audit_type' => $b->audit_type,
                'type_label' => $typeLabels[$b->audit_type] ?? $b->audit_type,
                'total_skus' => $total, 'mismatches' => $m, 'not_found' => $nf,
                'all_failed' => $allFailed, 'has_issues' => $hasIssues, 'dot_color' => $dotColor,
                'performed_by' => $b->performed_by, 'time_ago' => $timeAgo,
            ];
        }

        echo json_encode(['groups' => $groups, 'status_counts' => $statusCounts]);
        exit;
    }

    public function auditBatchDetail($batchId = null) {
        $this->requireAuth();
        $this->ensureCsrf();

        if (!$batchId || !preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $batchId)) {
            header('Location: ' . $this->route('/auditHistory'));
            exit;
        }

        $db = $this->db();
        $summaryStmt = $db->query(
            'SELECT batch_id, audit_type, MAX(audit_date) as audit_date, store_name, u.username as performed_by,
                    walmart_connection_id,
                    COUNT(*) as total_skus,
                    SUM(CASE WHEN diff_amount != 0 AND diff_amount IS NOT NULL THEN 1 ELSE 0 END) as mismatches,
                    SUM(CASE WHEN wpid = \'-\' OR wpid IS NULL OR wpid = \'\' THEN 1 ELSE 0 END) as not_found
             FROM auditorias_walmart a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE batch_id = ?
             GROUP BY batch_id, audit_type, store_name, u.username, walmart_connection_id'
        );
        $summaryStmt->execute([$batchId]);
        $summary = $summaryStmt->fetch();

        if (!$summary) {
            header('Location: ' . $this->route('/auditHistory'));
            exit;
        }

        $rowsStmt = $db->query('SELECT * FROM auditorias_walmart WHERE batch_id = ? ORDER BY sku ASC');
        $rowsStmt->execute([$batchId]);

        $page_title = ''; $page_description = ''; $current_nav = 'walmart-audit';
        $data = [
            'summary' => $summary,
            'rows' => $rowsStmt->fetchAll(),
            'csrf_token' => $_SESSION['csrf_token'],
            'is_admin' => !empty($_SESSION['is_admin']),
        ];

        $view_content = __DIR__ . '/../Views/walmart/content-audit-batch-detail.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function auditBatchDownload($batchId = null) {
        $this->requireAuth();
        $db = $this->db();

        $summaryStmt = $db->query('SELECT batch_id, audit_type FROM auditorias_walmart WHERE batch_id = ? LIMIT 1');
        $summaryStmt->execute([$batchId]);
        $summary = $summaryStmt->fetch();
        if (!$summary) {
            http_response_code(404);
            echo json_encode(['error' => 'Batch no encontrado.']);
            exit;
        }

        $rowsStmt = $db->query('SELECT * FROM auditorias_walmart WHERE batch_id = ? ORDER BY sku ASC');
        $rowsStmt->execute([$batchId]);
        $rows = $rowsStmt->fetchAll();

        $type = $summary->audit_type;
        $headers = $type === 'price'
            ? ['SKU Subido', 'SKU Encontrado', 'WPID', 'Precio Subido', 'Precio Walmart', 'Diferencia $', 'Diferencia %', 'Resultado', 'Método']
            : ['SKU Subido', 'SKU Encontrado', 'WPID', 'Stock Subido', 'Stock Walmart', 'Diferencia', 'Resultado', 'Método'];

        $methodLabels = ['exact' => 'Exacto', 'shortcode' => 'Código corto', 'fuzzy' => 'Aproximado', 'ambiguous' => 'Ambiguo'];

        require_once __DIR__ . '/../Helpers/XlsxWriter.php';
        $xlsx = new XlsxWriter();
        $xlsx->setHeaders($headers);
        foreach ($rows as $r) {
            $result = $r->match_method === 'ambiguous'
                ? 'Ambiguo — revisar manualmente'
                : (($r->wpid === '-' || !$r->wpid) ? 'No encontrado' : (((float) $r->diff_amount === 0.0) ? 'Coincide' : 'Diferencia'));
            $row = [$r->sku, $r->matched_sku ?: '-', $r->wpid, $r->uploaded_value, $r->walmart_value, $r->diff_amount];
            if ($type === 'price') $row[] = $r->diff_percent !== null ? $r->diff_percent . '%' : '-';
            $row[] = $result;
            $row[] = $methodLabels[$r->match_method ?? 'exact'] ?? $r->match_method;
            $xlsx->addRow($row);
        }

        $filename = 'auditoria_walmart_' . $type . '_' . date('Ymd_His');
        $tmpDir = ini_get('session.save_path') ?: sys_get_temp_dir();
        $tmpPath = tempnam($tmpDir, 'wm_audit_dl_') . '.xlsx';
        $xlsx->output($tmpPath);

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Content-Length: ' . filesize($tmpPath));
        readfile($tmpPath);
        unlink($tmpPath);
        exit;
    }

    /** Exportación manual del catálogo actual (precio o stock) — no compara nada, solo vuelca la caché. */
    public function exportCatalog($type = 'price') {
        $this->requireAuth();

        if (!in_array($type, ['price', 'stock'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Tipo inválido. Usa price o stock.']);
            exit;
        }

        $storeId = (int) ($_GET['store'] ?? $_SESSION['walmart_active_store_id'] ?? 0);
        $connections = (new WalmartConnectionModel($this->db()))->findActive();
        $activeStore = null;
        foreach ($connections as $c) {
            if ((int) $c->id === $storeId) { $activeStore = $c; break; }
        }
        if (!$activeStore && !empty($connections)) {
            $activeStore = $connections[0];
        }
        if (!$activeStore) {
            http_response_code(400);
            echo json_encode(['error' => 'No hay ninguna tienda Walmart conectada.']);
            exit;
        }

        try {
            $exportService = new WalmartExportService($this->db());
            $path = $exportService->generate((int) $activeStore->id, $type);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al generar el export: ' . $e->getMessage()]);
            exit;
        }

        $label = $type === 'price' ? 'precios' : 'stock';
        $filename = 'walmart_' . $label . '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $activeStore->store_name ?? 'tienda') . '_' . date('Ymd_His') . '.xlsx';

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Consulta la familia de un SKU (individual/pack/tripack) para mostrarla como sugerencia en filas "No encontrado". */
    public function auditFamilyLookup() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $sku = trim((string) ($_GET['sku'] ?? ''));
        if ($sku === '') {
            echo json_encode(['error' => 'SKU requerido.']);
            exit;
        }

        $service = new SkuFamilyService($this->db());
        $family = $service->getFamily($sku);

        echo json_encode(['ok' => true, 'base_sku' => $family['base_sku'], 'members' => $family['members']]);
        exit;
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
        $countStmt = $db->query('SELECT COUNT(*) as cnt FROM auditorias_walmart');
        $countStmt->execute();
        $count = (int) $countStmt->fetch()->cnt;

        $db->query('TRUNCATE TABLE auditorias_walmart')->execute();

        try {
            (new ActivityService($db))->log((int) $_SESSION['user_id'], 'logs_purged', "Purgó {$count} registros de auditorías Walmart");
        } catch (Exception $e) {}

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
        $countStmt = $db->query('SELECT COUNT(*) as cnt FROM auditorias_walmart WHERE batch_id = ?');
        $countStmt->execute([$batchId]);
        $count = (int) $countStmt->fetch()->cnt;

        if ($count === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Lote no encontrado.']);
            exit;
        }

        $db->query('DELETE FROM auditorias_walmart WHERE batch_id = ?')->execute([$batchId]);

        try {
            (new ActivityService($db))->log((int) $_SESSION['user_id'], 'logs_purged', "Eliminó lote de auditoría Walmart {$batchId} ({$count} registros)");
        } catch (Exception $e) {}

        echo json_encode(['ok' => true, 'deleted' => $count]);
        exit;
    }
}
