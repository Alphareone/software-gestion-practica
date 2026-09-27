<?php

/**
 * Módulo Cencosud Chile Marketplace.
 * Rutas: /cencosud/{metodo} (resueltas automáticamente por app/core/App.php).
 */
class CencosudController extends Controller {

    protected $baseRoute = '/cencosud';

    protected function route($path = '') {
        return URLROOT . $this->baseRoute . $path;
    }

    protected function ensureCsrf() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    protected function getConnections() {
        $connModel = new CencosudConnectionModel($this->db());
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
            $_SESSION['error'] = 'Token de seguridad inválido.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }
    }

    // ── Páginas (Vistas HTML) ──

    public function index() {
        $this->requireAuth();
        $this->ensureCsrf();

        $connModel = new CencosudConnectionModel($this->db());

        $page_title = 'Cencosud - Panel';
        $page_description = 'Resumen de las tiendas Cencosud Chile conectadas.';
        $current_nav = 'cencosud';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connModel->findActive(),
            'is_admin' => !empty($_SESSION['is_admin']),
            'dry_run' => CENCOSUD_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/cencosud/content-dashboard.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function products() {
        $this->requireAuth();
        $this->ensureCsrf();

        $connModel = new CencosudConnectionModel($this->db());

        $page_title = 'Cencosud - Productos';
        $page_description = 'Explora el catálogo sincronizado desde Cencosud.';
        $current_nav = 'cencosud-products';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connModel->findActive(),
            'dry_run' => CENCOSUD_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/cencosud/content-products.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    // ── Endpoints JSON: Métricas y productos ──

    public function metrics($id = null) {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (empty($id) || !is_numeric($id)) {
            echo json_encode(['error' => 'ID no especificado.']);
            exit;
        }

        $db = $this->db();
        $conn = (new CencosudConnectionModel($db))->findById((int) $id);
        if (!$conn) {
            echo json_encode(['error' => 'Tienda no encontrada.']);
            exit;
        }
        if (!$conn->is_active) {
            echo json_encode(['total' => 0, 'published' => 0, 'low_stock' => 0]);
            exit;
        }

        $metrics = (new CencosudProductsCacheModel($db))->getMetrics((int) $id);
        $sync = (new CencosudSyncStatusModel($db))->get((int) $id);
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
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? min(100, max(10, (int) $_GET['limit'])) : 50;
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        $cache = new CencosudProductsCacheModel($this->db());
        echo json_encode($cache->getProducts($storeId, $page, $limit, $search, $status));
        exit;
    }

    // ── Endpoints JSON: Sincronización ──

    public function syncStatus() {
        $this->requireAuth();
        header('Content-Type: application/json');
        $sync = new CencosudSyncService($this->db());
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
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        $sync = new CencosudSyncService($this->db());
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
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        $sync = new CencosudSyncService($this->db());
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
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        $sync = new CencosudSyncService($this->db());
        echo json_encode($sync->stopSync((int) $id));
        exit;
    }

    // ── Conexiones y CRUD (Administración) ──

    public function connect() {
        $this->requireAdmin();
        $this->ensureCsrf();

        $editId = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editConn = null;
        if ($editId) {
            $editConn = (new CencosudConnectionModel($this->db()))->findById($editId);
        }

        $page_title = 'Cencosud - Conectar tienda';
        $page_description = 'Conecta una cuenta de vendedor de Cencosud Chile.';
        $current_nav = 'cencosud-admin';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'edit' => $editConn,
            'default_base_url' => CENCOSUD_API_BASE_URL,
            'dry_run' => CENCOSUD_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/cencosud/content-connect.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function adminstores() {
        $this->requireAdmin();
        $this->ensureCsrf();

        $page_title = 'Cencosud - Gestión de Tiendas';
        $page_description = 'Administra las cuentas de Cencosud Chile conectadas.';
        $current_nav = 'cencosud-admin';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $this->getConnections(),
            'dry_run' => CENCOSUD_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/cencosud/content-admin-stores.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function saveConnection() {
        $this->requireAdmin();
        $this->requirePostCsrf();

        $editId = isset($_POST['edit_id']) && is_numeric($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
        $storeName = trim((string) ($_POST['store_name'] ?? ''));
        // Cencosud autentica con una sola API Key (Mi Cuenta -> Integraciones).
        // Se admite tambien el nombre antiguo del campo por compatibilidad.
        $apiKey = trim((string) ($_POST['api_key'] ?? ($_POST['client_secret'] ?? '')));
        $channelType = '';
        $apiBaseUrl = trim((string) ($_POST['api_base_url'] ?? '')) ?: CENCOSUD_API_BASE_URL;

        $backTo = $this->route('/connect' . ($editId ? '?edit=' . $editId : ''));

        if ($storeName === '') {
            $_SESSION['error'] = 'El nombre de la tienda es obligatorio.';
            header('Location: ' . $backTo);
            exit;
        }
        if (!$editId && $apiKey === '') {
            $_SESSION['error'] = 'La API Key es obligatoria.';
            header('Location: ' . $backTo);
            exit;
        }
        if (!preg_match('#^https://[a-z0-9.\-]+$#i', rtrim($apiBaseUrl, '/'))) {
            $_SESSION['error'] = 'La URL base de la API debe ser válida (https://...).';
            header('Location: ' . $backTo);
            exit;
        }
        $apiBaseUrl = rtrim($apiBaseUrl, '/');

        $db = $this->db();
        $connModel = new CencosudConnectionModel($db);

        $existing = $editId ? $connModel->findById($editId) : null;
        if ($editId && !$existing) {
            $_SESSION['error'] = 'La conexión a editar no existe.';
            header('Location: ' . $this->route('/adminstores'));
            exit;
        }
        
        $keyPlain = $apiKey !== ''
            ? $apiKey
            : ($existing ? CryptoHelper::decrypt($existing->client_secret) : '');

        // Validar la API Key contactando la API antes de guardar
        $auth = new CencosudAuthService($db);
        $test = $auth->testCredentials($keyPlain, $apiBaseUrl);
        if (!$test['ok']) {
            $_SESSION['error'] = 'No se pudo verificar la conexión: ' . $test['error'];
            header('Location: ' . $backTo);
            exit;
        }

        // El identificador del vendedor lo entrega la propia API al autenticar.
        $clientId = (string) ($test['seller_id'] ?? $keyPlain);
        $encryptedSecret = CryptoHelper::encrypt($keyPlain);
        $userId = (int) $_SESSION['user_id'];
        $a = new ActivityService($db);

        if ($editId) {
            $connModel->updateCredentials($editId, $clientId, $encryptedSecret, $channelType ?: null, $apiBaseUrl);
            if ($storeName !== $existing->store_name) {
                $connModel->rename($editId, $storeName);
            }
            $a->log($userId, 'cencosud_store_updated', "Actualizó credenciales de la tienda Cencosud ID {$editId} ({$storeName})");
            $_SESSION['success'] = 'Conexión actualizada y verificada correctamente.';
        } else {
            $dup = $connModel->findByClientId($clientId);
            if ($dup) {
                $_SESSION['error'] = 'Ya existe una conexión para ese vendedor de Cencosud.';
                header('Location: ' . $backTo);
                exit;
            }
            $newId = $connModel->create($userId, $storeName, $clientId, $encryptedSecret, $channelType ?: null, $apiBaseUrl);
            $a->log($userId, 'cencosud_store_connected', "Conectó la tienda Cencosud '{$storeName}' (ID {$newId})");
            $_SESSION['success'] = 'Tienda Cencosud conectada y verificada correctamente.';
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
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }
        if (empty($id) || !is_numeric($id)) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            exit;
        }

        $auth = new CencosudAuthService($this->db());
        $token = $auth->getToken((int) $id, true);
        echo json_encode($token
            ? ['ok' => true, 'message' => 'Conexión verificada: token de acceso generado correctamente.']
            : ['ok' => false, 'error' => 'Cencosud rechazó las credenciales o no respondió.']);
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
        (new CencosudConnectionModel($db))->setActive((int) $id, false);
        (new ActivityService($db))->log((int) $_SESSION['user_id'], 'cencosud_store_disconnected', "Desactivó la tienda Cencosud ID {$id}");
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
        (new CencosudConnectionModel($db))->setActive((int) $id, true);
        (new ActivityService($db))->log((int) $_SESSION['user_id'], 'cencosud_store_reactivated', "Reactivó la tienda Cencosud ID {$id}");
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
        (new CencosudConnectionModel($db))->delete((int) $id);
        (new ActivityService($db))->log((int) $_SESSION['user_id'], 'cencosud_store_deleted', "Eliminó la tienda Cencosud ID {$id}");
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
        $connModel = new CencosudConnectionModel($db);
        $oldName = $connModel->findStoreName((int) $id);
        $connModel->rename((int) $id, $storeName);
        (new ActivityService($db))->log((int) $_SESSION['user_id'], 'cencosud_store_renamed', "Renombró tienda Cencosud ID {$id}: '{$oldName}' → '{$storeName}'");
        $_SESSION['success'] = 'Tienda renombrada correctamente.';
        header('Location: ' . $this->route('/adminstores'));
        exit;
    }

    // ── Actualización Masiva de Precios/Stock (POST) ──

    public function updates() {
        $this->requireAuth();
        $this->ensureCsrf();

        $db = $this->db();
        $service = new CencosudUpdateService($db);
        $pending = $service->restoreBatchFromDb((int) $_SESSION['user_id']);

        $page_title = 'Cencosud - Actualizar precios y stock';
        $page_description = 'Sube un archivo con SKU y valor para actualizar en Cencosud.';
        $current_nav = 'cencosud-updates';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => (new CencosudConnectionModel($db))->findActive(),
            'pending_batch' => $pending ? [
                'batch_id' => $pending['batch_id'],
                'update_type' => $pending['update_type'],
                'cencosud_connection_id' => $pending['cencosud_connection_id'],
                'total' => $pending['total'],
                'processed' => $pending['processed'],
                'ok' => $pending['ok'],
                'errors' => $pending['errors'],
            ] : null,
            'dry_run' => CENCOSUD_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/cencosud/content-updates.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function updateHistory() {
        $this->requireAuth();
        $this->ensureCsrf();

        $page_title = 'Cencosud - Historial de actualizaciones';
        $page_description = 'Batches de precios y stock ejecutados contra Cencosud.';
        $current_nav = 'cencosud-history';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'is_admin' => !empty($_SESSION['is_admin']),
            'dry_run' => CENCOSUD_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/cencosud/content-update-history.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function updateHistoryData() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $type = trim((string) ($_GET['type'] ?? ''));

        $service = new CencosudUpdateService($this->db());
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

        $service = new CencosudUpdateService($this->db());
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
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        $storeId = isset($_POST['store_id']) && is_numeric($_POST['store_id']) ? (int) $_POST['store_id'] : 0;
        $type = ($_POST['update_type'] ?? '') === 'stock' ? 'stock' : 'price';

        $conn = (new CencosudConnectionModel($this->db()))->findById($storeId);
        if (!$conn || !$conn->is_active) {
            echo json_encode(['ok' => false, 'error' => 'Tienda inválida o inactiva.']);
            exit;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'No se recibió ningún archivo.']);
            exit;
        }
        
        $file = $_FILES['file'];
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'El archivo no puede exceder los 10 MB.']);
            exit;
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'])) {
            echo json_encode(['ok' => false, 'error' => 'Formato no soportado. Debe usar .xlsx o .csv.']);
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
            $service = new CencosudUpdateService($this->db());
            $rows = $service->readRows($file['tmp_name'], $ext);
            $parsed = $service->parseEntries($rows, $type);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }

        if (empty($parsed['entries'])) {
            echo json_encode([
                'ok' => false,
                'error' => 'El archivo no contiene filas con datos válidos.',
                'validation_errors' => $parsed['validation_errors'],
            ]);
            exit;
        }

        // Crear lote de procesamiento
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
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $db = $this->db();
        $service = new CencosudUpdateService($db);
        $batch = $service->restoreBatchFromDb($userId);
        if (!$batch) {
            echo json_encode(['ok' => false, 'error' => 'No hay ningún lote de actualización pendiente.']);
            exit;
        }

        // Procesar lote de 10 productos por llamada AJAX
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
                (new ActivityService($db))->log($userId, 'cencosud_bulk_update', "Lote de {$typeLabel} Cencosud {$batch['batch_id']}: {$result['ok']} OK, {$result['errors']} errores de {$result['total']}");
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
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $service = new CencosudUpdateService($this->db());
        $service->deleteBatchFromDb($userId);
        try {
            (new ActivityService($this->db()))->log($userId, 'cencosud_bulk_update', 'Canceló el lote de actualización pendiente de Cencosud');
        } catch (Exception $e) {}
        echo json_encode(['ok' => true]);
        exit;
    }

    public function reports() {
        $this->requireAuth();
        $this->ensureCsrf();

        $connModel = new CencosudConnectionModel($this->db());

        $page_title = 'Cencosud - Reportes';
        $page_description = 'Control de inventario, stock y precios de bodega vs Cencosud.';
        $current_nav = 'cencosud-reports';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connModel->findActive(),
            'dry_run' => CENCOSUD_DRY_RUN,
        ];

        $view_content = __DIR__ . '/../Views/cencosud/content-reports.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function reportsData() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $storeId = isset($_GET['store']) && is_numeric($_GET['store']) ? (int) $_GET['store'] : 0;
        if (!$storeId) {
            echo json_encode(['error' => 'Tienda no especificada.']);
            exit;
        }

        $search = trim((string) ($_GET['q'] ?? ''));
        $discrepancy = trim((string) ($_GET['discrepancy'] ?? ''));
        $highStock = trim((string) ($_GET['high_stock'] ?? ''));
        $minStock = isset($_GET['min_stock']) && is_numeric($_GET['min_stock']) ? (int)$_GET['min_stock'] : 50;

        $db = $this->db();
        $params = [$storeId];
        $where = 'WHERE c.cencosud_connection_id = ?';

        if ($search !== '') {
            $clean = preg_replace('/^(TRIPACK|PACK)-/i', '', trim($search));
            $baseSku = null;
            if (preg_match('/^([A-Za-z0-9]+-[0-9]+)/', $clean, $matches)) {
                $baseSku = $matches[1];
            }

            if ($baseSku !== null) {
                $where .= ' AND (c.sku REGEXP ?)';
                $params[] = '^(TRIPACK-|PACK-)?' . $baseSku . '(-|$)';
            } else {
                $where .= ' AND (c.title LIKE ? OR c.sku LIKE ?)';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }
        }

        if ($discrepancy === 'true' || $discrepancy === '1') {
            $where .= ' AND COALESCE(c.available_quantity, 0) != COALESCE(m.stock, 0)';
        }

        if ($highStock === 'true' || $highStock === '1') {
            $where .= ' AND (COALESCE(m.stock, 0) >= ? OR COALESCE(c.available_quantity, 0) >= ?)';
            $params[] = $minStock;
            $params[] = $minStock;
        }

        $stmt = $db->query("
            SELECT 
                c.id AS cache_id,
                c.sku,
                c.title AS cencosud_title,
                c.price AS cencosud_price,
                c.available_quantity AS cencosud_stock,
                m.brand AS master_brand,
                m.title AS master_title,
                m.price AS master_price,
                m.stock AS master_stock,
                m.category AS master_category,
                m.description AS master_description,
                m.color AS master_color,
                m.size AS master_size,
                m.gender AS master_gender,
                ml.thumbnail
            FROM cencosud_products_cache c
            LEFT JOIN products_master m ON c.sku COLLATE utf8mb4_unicode_ci = m.sku
            LEFT JOIN (
                SELECT sku, MIN(thumbnail) AS thumbnail 
                FROM ml_products_cache 
                WHERE sku IS NOT NULL AND thumbnail != '' 
                GROUP BY sku
            ) ml ON c.sku COLLATE utf8mb4_unicode_ci = ml.sku
            $where
            ORDER BY c.sku ASC
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cargar tabla product_medidas en memoria
        $medidasList = [];
        try {
            $medQuery = $db->query("SELECT categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante FROM product_medidas ORDER BY LENGTH(categoria) DESC, id ASC");
            $medidasList = $medQuery->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        // Enriquecer el tipo de SKU (Unidad, Pack, Tripack) y medidas
        foreach ($items as &$item) {
            $sku = strtoupper($item['sku']);
            if (strpos($sku, 'TRIPACK-') === 0) {
                $item['tipo'] = 'Tripack';
            } elseif (strpos($sku, 'PACK-') === 0) {
                $item['tipo'] = 'Pack';
            } else {
                $item['tipo'] = 'Unidad';
            }

            // Buscar medidas
            $peso = null;
            $alto = null;
            $ancho = null;
            $largo = null;

            // 1. Coincidencia directa por variante/SKU
            foreach ($medidasList as $m) {
                if (!empty($m['variante']) && strtoupper(trim($m['variante'])) === $sku) {
                    $peso = $m['peso_kg'];
                    $alto = $m['alto_cm'];
                    $ancho = $m['ancho_cm'];
                    $largo = $m['grueso_cm'];
                    break;
                }
            }

            // 2. Coincidencia por categoría/nombre
            if ($peso === null) {
                $nombre = $item['master_title'] ?: $item['cencosud_title'];
                $categoria = $item['master_category'] ?? '';
                $texto = mb_strtolower($nombre . ' ' . $categoria);
                $bestLen = 0;
                foreach ($medidasList as $m) {
                    if (empty($m['variante']) && !empty($m['categoria'])) {
                        $catLower = mb_strtolower(trim($m['categoria']));
                        if (strpos($texto, $catLower) !== false) {
                            $len = mb_strlen($catLower);
                            if ($len > $bestLen) {
                                $bestLen = $len;
                                $peso = $m['peso_kg'];
                                $alto = $m['alto_cm'];
                                $ancho = $m['ancho_cm'];
                                $largo = $m['grueso_cm'];
                            }
                        }
                    }
                }
            }

            $item['peso'] = $peso;
            $item['alto'] = $alto;
            $item['ancho'] = $ancho;
            $item['largo'] = $largo;
            $item['profundidad'] = $largo;
        }

        echo json_encode(['items' => $items]);
        exit;
    }

    public function exportReportsXlsx() {
        $this->requireAuth();

        $storeId = isset($_GET['store']) && is_numeric($_GET['store']) ? (int) $_GET['store'] : 0;
        if (!$storeId) {
            $_SESSION['pc_error'] = 'Tienda no especificada para exportación.';
            header('Location: ' . URLROOT . '/cencosud/reports');
            exit;
        }

        $connModel = new CencosudConnectionModel($this->db());
        $store = $connModel->findById($storeId);
        $storeName = $store ? ($store->store_name ?: 'Sin nombre') : 'Tienda ' . $storeId;

        $search = trim((string) ($_GET['q'] ?? ''));
        $discrepancy = trim((string) ($_GET['discrepancy'] ?? ''));
        $highStock = trim((string) ($_GET['high_stock'] ?? ''));
        $minStock = isset($_GET['min_stock']) && is_numeric($_GET['min_stock']) ? (int)$_GET['min_stock'] : 50;

        $db = $this->db();
        $params = [$storeId];
        $where = 'WHERE c.cencosud_connection_id = ?';

        if ($search !== '') {
            $clean = preg_replace('/^(TRIPACK|PACK)-/i', '', trim($search));
            $baseSku = null;
            if (preg_match('/^([A-Za-z0-9]+-[0-9]+)/', $clean, $matches)) {
                $baseSku = $matches[1];
            }

            if ($baseSku !== null) {
                $where .= ' AND (c.sku REGEXP ?)';
                $params[] = '^(TRIPACK-|PACK-)?' . $baseSku . '(-|$)';
            } else {
                $where .= ' AND (c.title LIKE ? OR c.sku LIKE ?)';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }
        }

        if ($discrepancy === 'true' || $discrepancy === '1') {
            $where .= ' AND COALESCE(c.available_quantity, 0) != COALESCE(m.stock, 0)';
        }

        if ($highStock === 'true' || $highStock === '1') {
            $where .= ' AND (COALESCE(m.stock, 0) >= ? OR COALESCE(c.available_quantity, 0) >= ?)';
            $params[] = $minStock;
            $params[] = $minStock;
        }

        $stmt = $db->query("
            SELECT 
                c.sku,
                c.title AS cencosud_title,
                c.price AS cencosud_price,
                c.available_quantity AS cencosud_stock,
                m.brand AS master_brand,
                m.title AS master_title,
                m.price AS master_price,
                m.stock AS master_stock,
                m.category AS master_category,
                m.description AS master_description,
                ml.thumbnail
            FROM cencosud_products_cache c
            LEFT JOIN products_master m ON c.sku COLLATE utf8mb4_unicode_ci = m.sku
            LEFT JOIN (
                SELECT sku, MIN(thumbnail) AS thumbnail 
                FROM ml_products_cache 
                WHERE sku IS NOT NULL AND thumbnail != '' 
                GROUP BY sku
            ) ml ON c.sku COLLATE utf8mb4_unicode_ci = ml.sku
            $where
            ORDER BY c.sku ASC
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cargar tabla product_medidas en memoria
        $medidasList = [];
        try {
            $medQuery = $db->query("SELECT categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante FROM product_medidas ORDER BY LENGTH(categoria) DESC, id ASC");
            $medidasList = $medQuery->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Auditoría Inventario');

            // Título general del Reporte
            $sheet->setCellValue('A1', 'REPORTE DE INVENTARIO Y COMPARACIÓN DE STOCK');
            $sheet->mergeCells('A1:Q1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $sheet->setCellValue('A2', 'Tienda: ' . $storeName);
            $sheet->setCellValue('A3', 'Fecha Generación: ' . date('d-m-Y H:i:s'));
            $sheet->getStyle('A2:A3')->getFont()->setItalic(true);

            // Cabeceras de tabla
            $headers = [
                'SKU', 'Marca', 'Producto', 'Categoría', 'Color', 'Talla', 'Género', 'Descripción', 'Peso (Kg)', 'Alto (cm)', 'Ancho (cm)', 'Largo (cm)', 'URL Foto',
                'Tipo', 'Stock Bodega', 'Stock Cencosud', 'Diferencia Stock', 'Precio Lista', 'Precio Cencosud', 'Estado Promocional'
            ];
            
            $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];
            $headerRow = 5;

            foreach ($headers as $i => $h) {
                $col = $colLetters[$i] . $headerRow;
                $sheet->setCellValue($col, $h);
                $sheet->getStyle($col)->getFont()->setBold(true);
                $sheet->getStyle($col)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FF1E3A8A'); // Azul oscuro premium
                $sheet->getStyle($col)->getFont()->getColor()->setRGB('FFFFFF');
            }

            // Datos
            $currentRow = $headerRow + 1;
            foreach ($items as $item) {
                $sku = strtoupper($item['sku']);
                if (strpos($sku, 'TRIPACK-') === 0) {
                    $tipo = 'Tripack';
                } elseif (strpos($sku, 'PACK-') === 0) {
                    $tipo = 'Pack';
                } else {
                    $tipo = 'Unidad';
                }

                // Buscar medidas
                $peso = null;
                $alto = null;
                $ancho = null;
                $largo = null;

                // 1. Coincidencia directa por variante/SKU
                foreach ($medidasList as $m) {
                    if (!empty($m['variante']) && strtoupper(trim($m['variante'])) === $sku) {
                        $peso = $m['peso_kg'];
                        $alto = $m['alto_cm'];
                        $ancho = $m['ancho_cm'];
                        $largo = $m['grueso_cm'];
                        break;
                    }
                }

                // 2. Coincidencia por categoría/nombre
                if ($peso === null) {
                    $nombre = $item['master_title'] ?: $item['cencosud_title'];
                    $categoria = $item['master_category'] ?? '';
                    $texto = mb_strtolower($nombre . ' ' . $categoria);
                    $bestLen = 0;
                    foreach ($medidasList as $m) {
                        if (empty($m['variante']) && !empty($m['categoria'])) {
                            $catLower = mb_strtolower(trim($m['categoria']));
                            if (strpos($texto, $catLower) !== false) {
                                $len = mb_strlen($catLower);
                                if ($len > $bestLen) {
                                    $bestLen = $len;
                                    $peso = $m['peso_kg'];
                                    $alto = $m['alto_cm'];
                                    $ancho = $m['ancho_cm'];
                                    $largo = $m['grueso_cm'];
                                }
                            }
                        }
                    }
                }

                $mStock = $item['master_stock'] !== null ? (int)$item['master_stock'] : 0;
                $cStock = $item['cencosud_stock'] !== null ? (int)$item['cencosud_stock'] : 0;
                $diffStock = $mStock - $cStock;

                $mPrice = $item['master_price'] !== null ? (float)$item['master_price'] : 0.0;
                $cPrice = $item['cencosud_price'] !== null ? (float)$item['cencosud_price'] : 0.0;

                $promoStatus = '-';
                if ($cPrice > 0 && $mPrice > 0 && $cPrice < $mPrice) {
                    $discountPct = round((1 - ($cPrice / $mPrice)) * 100);
                    $promoStatus = "Oferta (" . $discountPct . "% desc.)";
                }

                $sheet->setCellValue('A' . $currentRow, $item['sku']);
                $sheet->setCellValue('B' . $currentRow, $item['master_brand'] ?: '—');
                $sheet->setCellValue('C' . $currentRow, $item['cencosud_title']);
                $sheet->setCellValue('D' . $currentRow, $item['master_category'] ?: '—');
                $sheet->setCellValue('E' . $currentRow, $item['master_color'] ?: '—');
                $sheet->setCellValue('F' . $currentRow, $item['master_size'] ?: '—');
                $sheet->setCellValue('G' . $currentRow, $item['master_gender'] ?: '—');
                $sheet->setCellValue('H' . $currentRow, $item['master_description'] ?: '—');
                $sheet->setCellValue('I' . $currentRow, $peso ?: '—');
                $sheet->setCellValue('J' . $currentRow, $alto ?: '—');
                $sheet->setCellValue('K' . $currentRow, $ancho ?: '—');
                $sheet->setCellValue('L' . $currentRow, $largo ?: '—');
                $sheet->setCellValue('M' . $currentRow, $item['thumbnail'] ?: '—');
                $sheet->setCellValue('N' . $currentRow, $tipo);
                $sheet->setCellValue('O' . $currentRow, $mStock);
                $sheet->setCellValue('P' . $currentRow, $cStock);
                $sheet->setCellValue('Q' . $currentRow, $diffStock);
                $sheet->setCellValue('R' . $currentRow, $mPrice);
                $sheet->setCellValue('S' . $currentRow, $cPrice);
                $sheet->setCellValue('T' . $currentRow, $promoStatus);

                // Estilo para discrepancias de stock (resaltado en rojo suave)
                if ($diffStock !== 0) {
                    $sheet->getStyle('Q' . $currentRow)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFFECACA'); // Rojo suave
                    $sheet->getStyle('Q' . $currentRow)->getFont()->getColor()->setRGB('B91C1C'); // Texto rojo oscuro
                }

                // Estilo para ofertas activas (resaltado en verde suave)
                if ($promoStatus !== '-') {
                    $sheet->getStyle('T' . $currentRow)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFDCFCE7'); // Verde suave
                    $sheet->getStyle('T' . $currentRow)->getFont()->getColor()->setRGB('15803D'); // Texto verde oscuro
                }

                $currentRow++;
            }

            foreach ($colLetters as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $sheet->getSheetView()->setZoomScale(90);

            $filename = 'reporte_inventario_' . strtolower(str_replace(' ', '_', $storeName)) . '_' . date('Ymd_His') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            exit;

        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al exportar reporte: ' . $e->getMessage();
            header('Location: ' . URLROOT . '/cencosud/reports');
            exit;
        }
    }

    public function replicateStock() {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        $storeId = isset($_POST['store']) && is_numeric($_POST['store']) ? (int) $_POST['store'] : 0;
        $sku = trim((string) ($_POST['sku'] ?? ''));

        if (!$storeId || $sku === '') {
            echo json_encode(['ok' => false, 'error' => 'Datos insuficientes.']);
            exit;
        }

        $db = $this->db();
        // Obtener stock master
        $stmt = $db->query("SELECT stock FROM products_master WHERE sku = ?");
        $stmt->execute([$sku]);
        $master = $stmt->fetch();
        if (!$master) {
            echo json_encode(['ok' => false, 'error' => 'El SKU no existe en la base de datos de bodega (products_master).']);
            exit;
        }

        $stock = (int) $master->stock;

        // Si el stock maestro es menor o igual a 5 y mayor a 0, por regla de stock critico Cencosud debe quedar en 0
        $cencosudStock = $stock;
        if ($stock > 0 && $stock <= 5) {
            $cencosudStock = 0;
        }

        // Llamar a la API de Cencosud
        $api = new CencosudApiService($db);
        $result = $api->updateInventory($storeId, $sku, $cencosudStock);

        if ($result['code'] !== 200) {
            echo json_encode(['ok' => false, 'error' => 'Error al comunicar con la API de Cencosud.']);
            exit;
        }

        // Actualizar caché local
        $cache = new CencosudProductsCacheModel($db);
        $cache->updateLocalStock($storeId, $sku, $cencosudStock);

        // Si fue forzado a 0 por regla de stock critico, gatillar notificacion
        if ($stock > 0 && $stock <= 5) {
            try {
                $userId = (int) $_SESSION['user_id'];
                $notif = new NotificationService($db);
                $storeName = (new CencosudConnectionModel($db))->findStoreName($storeId) ?: "Tienda #{$storeId}";
                $notif->createNotification(
                    $userId, 'stock_warning', "No queda stock — {$storeName} (SKU: {$sku})",
                    "Se replicó stock de bodega ({$stock} unidades). Por regla crítica de Cencosud (stock <= 5), se forzó stock a 0."
                );
            } catch (Exception $e) {}
        }

        try {
            $userId = (int) $_SESSION['user_id'];
            (new ActivityService($db))->log($userId, 'cencosud_stock_sync', "Se replicó stock de bodega a Cencosud para SKU: {$sku}. Stock publicado: {$cencosudStock}");
        } catch (Exception $e) {}

        echo json_encode(['ok' => true]);
        exit;
    }

    public function searchMasterProduct() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $sku = trim((string) ($_GET['sku'] ?? ''));
        if ($sku === '') {
            echo json_encode(['error' => 'SKU no especificado.']);
            exit;
        }

        $db = $this->db();
        // Buscar en products_master y hacer join para thumbnail
        $stmt = $db->prepare("
            SELECT 
                m.sku,
                m.title,
                m.brand,
                m.category,
                m.description,
                m.price,
                m.stock,
                ml.thumbnail
            FROM products_master m
            LEFT JOIN (
                SELECT sku, MIN(thumbnail) AS thumbnail 
                FROM ml_products_cache 
                WHERE sku IS NOT NULL AND thumbnail != '' 
                GROUP BY sku
            ) ml ON m.sku COLLATE utf8mb4_unicode_ci = ml.sku
            WHERE m.sku = ?
        ");
        $stmt->execute([$sku]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo json_encode(['error' => 'Producto no encontrado en bodega.']);
            exit;
        }

        // Buscar medidas asociadas a este SKU o categoría
        $medQuery = $db->query("SELECT categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante FROM product_medidas ORDER BY LENGTH(categoria) DESC, id ASC");
        $medidasList = $medQuery->fetchAll(PDO::FETCH_ASSOC);

        $peso = null;
        $alto = null;
        $ancho = null;
        $largo = null;

        // 1. Coincidencia directa por variante/SKU
        foreach ($medidasList as $m) {
            if (!empty($m['variante']) && strtoupper(trim($m['variante'])) === strtoupper($sku)) {
                $peso = $m['peso_kg'];
                $alto = $m['alto_cm'];
                $ancho = $m['ancho_cm'];
                $largo = $m['grueso_cm'];
                break;
            }
        }

        // 2. Coincidencia por categoría/nombre
        if ($peso === null) {
            $nombre = $product['title'];
            $categoria = $product['category'] ?? '';
            $texto = mb_strtolower($nombre . ' ' . $categoria);
            $bestLen = 0;
            foreach ($medidasList as $m) {
                if (empty($m['variante']) && !empty($m['categoria'])) {
                    $catLower = mb_strtolower(trim($m['categoria']));
                    if (strpos($texto, $catLower) !== false) {
                        $len = mb_strlen($catLower);
                        if ($len > $bestLen) {
                            $bestLen = $len;
                            $peso = $m['peso_kg'];
                            $alto = $m['alto_cm'];
                            $ancho = $m['ancho_cm'];
                            $largo = $m['grueso_cm'];
                        }
                    }
                }
            }
        }

        $product['peso'] = $peso;
        $product['alto'] = $alto;
        $product['ancho'] = $ancho;
        $product['largo'] = $largo;

        echo json_encode(['ok' => true, 'product' => $product]);
        exit;
    }

    public function saveAndSyncProduct() {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        $storeId = isset($_POST['store']) && is_numeric($_POST['store']) ? (int) $_POST['store'] : 0;
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $brand = trim((string) ($_POST['brand'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $peso = trim((string) ($_POST['peso'] ?? ''));
        $alto = trim((string) ($_POST['alto'] ?? ''));
        $ancho = trim((string) ($_POST['ancho'] ?? ''));
        $largo = trim((string) ($_POST['largo'] ?? '')); // grueso

        if (!$storeId || $sku === '' || $title === '') {
            echo json_encode(['ok' => false, 'error' => 'SKU, título y tienda son obligatorios.']);
            exit;
        }

        $db = $this->db();

        // 1. Actualizar products_master
        $stmt = $db->prepare("
            UPDATE products_master 
            SET title = ?, brand = ?, category = ?, description = ?
            WHERE sku = ?
        ");
        $stmt->execute([$title, $brand, $category, $description, $sku]);

        // Si no existía en products_master, insertarlo
        if ($stmt->rowCount() === 0) {
            $check = $db->prepare("SELECT sku FROM products_master WHERE sku = ?");
            $check->execute([$sku]);
            if (!$check->fetch()) {
                $ins = $db->prepare("
                    INSERT INTO products_master (sku, title, brand, category, description, price, stock)
                    VALUES (?, ?, ?, ?, ?, 0, 15)
                ");
                $ins->execute([$sku, $title, $brand, $category, $description]);
            }
        }

        // 2. Guardar medidas en product_medidas asociadas a la SKU (columna variante)
        $medCheck = $db->prepare("SELECT id FROM product_medidas WHERE variante = ?");
        $medCheck->execute([$sku]);
        $medRow = $medCheck->fetch();

        if ($medRow) {
            $medUpd = $db->prepare("
                UPDATE product_medidas 
                SET categoria = ?, peso_kg = ?, alto_cm = ?, ancho_cm = ?, grueso_cm = ?
                WHERE id = ?
            ");
            $medUpd->execute([$category, $peso, $alto, $ancho, $largo, $medRow->id]);
        } else {
            $medIns = $db->prepare("
                INSERT INTO product_medidas (categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $medIns->execute([$category, $peso, $alto, $ancho, $largo, $sku]);
        }

        // 3. Obtener stock y precio maestro actual
        $masterQuery = $db->prepare("SELECT price, stock FROM products_master WHERE sku = ?");
        $masterQuery->execute([$sku]);
        $mProduct = $masterQuery->fetch();
        $price = $mProduct ? (float) $mProduct->price : 0.0;
        $stock = $mProduct ? (int) $mProduct->stock : 15;

        // Regla de stock crítico
        $cencosudStock = $stock;
        if ($stock > 0 && $stock <= 5) {
            $cencosudStock = 0;
        }

        // 4. Upsert en cencosud_products_cache
        $cacheModel = new CencosudProductsCacheModel($db);
        $extra = ['unpublishedReasons' => []];
        $cacheData = [
            'cencosud_product_id' => 'CSPID-' . $sku,
            'title' => $title,
            'product_type' => $category ?: 'Ropa',
            'price' => $price,
            'currency' => 'CLP',
            'available_quantity' => $cencosudStock,
            'published_status' => 'PUBLISHED',
            'lifecycle_status' => 'ACTIVE',
            'extra_data' => json_encode($extra)
        ];
        $cacheModel->upsert($storeId, $sku, $cacheData);

        // 5. Simular la llamada de creación en la API de Cencosud
        $api = new CencosudApiService($db);
        $api->updateInventory($storeId, $sku, $cencosudStock);

        // 6. Logguear actividad
        try {
            $userId = (int) $_SESSION['user_id'];
            (new ActivityService($db))->log($userId, 'cencosud_product_sync', "Se cargó y sincronizó plantilla completa de medidas para SKU: {$sku}");
        } catch (Exception $e) {}

        echo json_encode(['ok' => true]);
        exit;
    }

    public function sendHighStockEmail() {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        $storeId = isset($_POST['store']) && is_numeric($_POST['store']) ? (int) $_POST['store'] : 0;
        $emailsRaw = trim((string) ($_POST['emails'] ?? ''));
        $minStock = isset($_POST['min_stock']) && is_numeric($_POST['min_stock']) ? (int)$_POST['min_stock'] : 50;

        if (!$storeId || empty($emailsRaw)) {
            echo json_encode(['ok' => false, 'error' => 'Tienda y correo de destino son obligatorios.']);
            exit;
        }

        $emailList = preg_split('/[\s,;]+/', $emailsRaw);
        $validEmails = [];
        foreach ($emailList as $e) {
            $e = trim($e);
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $e;
            }
        }

        if (empty($validEmails)) {
            echo json_encode(['ok' => false, 'error' => 'No se ingresaron correos electrónicos válidos.']);
            exit;
        }

        $db = $this->db();
        $connModel = new CencosudConnectionModel($db);
        $store = $connModel->findById($storeId);
        $storeName = $store ? ($store->store_name ?: 'Sin nombre') : 'Tienda ' . $storeId;

        $params = [$storeId, $minStock, $minStock];
        $where = 'WHERE c.cencosud_connection_id = ? AND (COALESCE(m.stock, 0) >= ? OR COALESCE(c.available_quantity, 0) >= ?)';

        $stmt = $db->prepare("
            SELECT 
                c.sku,
                c.title AS cencosud_title,
                c.price AS cencosud_price,
                c.available_quantity AS cencosud_stock,
                m.brand AS master_brand,
                m.title AS master_title,
                m.price AS master_price,
                m.stock AS master_stock,
                m.category AS master_category,
                m.description AS master_description,
                ml.thumbnail
            FROM cencosud_products_cache c
            LEFT JOIN products_master m ON c.sku COLLATE utf8mb4_unicode_ci = m.sku
            LEFT JOIN (
                SELECT sku, MIN(thumbnail) AS thumbnail 
                FROM ml_products_cache 
                WHERE sku IS NOT NULL AND thumbnail != '' 
                GROUP BY sku
            ) ml ON c.sku COLLATE utf8mb4_unicode_ci = ml.sku
            $where
            ORDER BY c.sku ASC
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $medidasList = [];
        try {
            $medQuery = $db->query("SELECT categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante FROM product_medidas ORDER BY LENGTH(categoria) DESC, id ASC");
            $medidasList = $medQuery->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Alto Stock');

        $sheet->setCellValue('A1', 'REPORTE DE ALTO STOCK (≥ ' . $minStock . ' UNIDADES)');
        $sheet->mergeCells('A1:Q1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Tienda: ' . $storeName);
        $sheet->setCellValue('A3', 'Fecha Generación: ' . date('d-m-Y H:i:s'));

        $headers = [
            'SKU', 'Marca', 'Producto', 'Categoría', 'Descripción', 'Peso (Kg)', 'Alto (cm)', 'Ancho (cm)', 'Largo (cm)', 'URL Foto',
            'Tipo', 'Stock Bodega', 'Stock Cencosud', 'Diferencia Stock', 'Precio Lista', 'Precio Cencosud', 'Estado Promocional'
        ];
        $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q'];
        $headerRow = 5;

        foreach ($headers as $i => $h) {
            $col = $colLetters[$i] . $headerRow;
            $sheet->setCellValue($col, $h);
            $sheet->getStyle($col)->getFont()->setBold(true);
            $sheet->getStyle($col)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FF1E3A8A');
            $sheet->getStyle($col)->getFont()->getColor()->setRGB('FFFFFF');
        }

        $currentRow = $headerRow + 1;
        $totalUnits = 0;

        foreach ($items as $item) {
            $sku = strtoupper($item['sku']);
            $tipo = (strpos($sku, 'TRIPACK-') === 0) ? 'Tripack' : ((strpos($sku, 'PACK-') === 0) ? 'Pack' : 'Unidad');

            $peso = null; $alto = null; $ancho = null; $largo = null;
            foreach ($medidasList as $m) {
                if (!empty($m['variante']) && strtoupper(trim($m['variante'])) === $sku) {
                    $peso = $m['peso_kg']; $alto = $m['alto_cm']; $ancho = $m['ancho_cm']; $largo = $m['grueso_cm'];
                    break;
                }
            }
            if ($peso === null) {
                $nombre = $item['master_title'] ?: $item['cencosud_title'];
                $categoria = $item['master_category'] ?? '';
                $texto = mb_strtolower($nombre . ' ' . $categoria);
                $bestLen = 0;
                foreach ($medidasList as $m) {
                    if (empty($m['variante']) && !empty($m['categoria'])) {
                        $catLower = mb_strtolower(trim($m['categoria']));
                        if (strpos($texto, $catLower) !== false) {
                            $len = mb_strlen($catLower);
                            if ($len > $bestLen) {
                                $bestLen = $len;
                                $peso = $m['peso_kg']; $alto = $m['alto_cm']; $ancho = $m['ancho_cm']; $largo = $m['grueso_cm'];
                            }
                        }
                    }
                }
            }

            $mStock = $item['master_stock'] !== null ? (int)$item['master_stock'] : 0;
            $cStock = $item['cencosud_stock'] !== null ? (int)$item['cencosud_stock'] : 0;
            $diffStock = $mStock - $cStock;
            $mPrice = $item['master_price'] !== null ? (float)$item['master_price'] : 0.0;
            $cPrice = $item['cencosud_price'] !== null ? (float)$item['cencosud_price'] : 0.0;

            $totalUnits += max($mStock, $cStock);

            $sheet->setCellValue('A' . $currentRow, $item['sku']);
            $sheet->setCellValue('B' . $currentRow, $item['master_brand'] ?: '—');
            $sheet->setCellValue('C' . $currentRow, $item['cencosud_title']);
            $sheet->setCellValue('D' . $currentRow, $item['master_category'] ?: '—');
            $sheet->setCellValue('E' . $currentRow, $item['master_description'] ?: '—');
            $sheet->setCellValue('F' . $currentRow, $peso ?: '—');
            $sheet->setCellValue('G' . $currentRow, $alto ?: '—');
            $sheet->setCellValue('H' . $currentRow, $ancho ?: '—');
            $sheet->setCellValue('I' . $currentRow, $largo ?: '—');
            $sheet->setCellValue('J' . $currentRow, $item['thumbnail'] ?: '—');
            $sheet->setCellValue('K' . $currentRow, $tipo);
            $sheet->setCellValue('L' . $currentRow, $mStock);
            $sheet->setCellValue('M' . $currentRow, $cStock);
            $sheet->setCellValue('N' . $currentRow, $diffStock);
            $sheet->setCellValue('O' . $currentRow, $mPrice);
            $sheet->setCellValue('P' . $currentRow, $cPrice);
            $sheet->setCellValue('Q' . $currentRow, '-');

            $currentRow++;
        }

        foreach ($colLetters as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tempDir = sys_get_temp_dir();
        $filename = 'Reporte_Alto_Stock_' . date('Ymd_His') . '.xlsx';
        $tempPath = $tempDir . '/' . $filename;

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempPath);

        $mailService = new MailService();
        $senderName = $_SESSION['first_name'] ?? $_SESSION['username'] ?? 'Administrador';

        $dataEmail = [
            'store_name' => $storeName,
            'generated_date' => date('d/m/Y H:i'),
            'total_skus' => count($items),
            'total_units' => $totalUnits,
            'sender_name' => $senderName,
            'user_id' => $_SESSION['user_id'] ?? null
        ];

        $sentCount = 0;
        foreach ($validEmails as $email) {
            $subject = "Reporte de Alto Stock (≥ {$minStock} u.) - {$storeName}";
            if ($mailService->sendWithAttachment($email, $subject, 'high_stock_report', $dataEmail, $tempPath, $filename)) {
                $sentCount++;
            }
        }

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        try {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $destStr = implode(', ', $validEmails);
            (new ActivityService($db))->log($userId, 'cencosud_email_report', "Se envió reporte de Alto Stock ({$sentCount} correos) a: {$destStr}");
        } catch (Exception $e) {}

        echo json_encode([
            'ok' => true,
            'sent_count' => $sentCount,
            'message' => "Se envió el reporte a {$sentCount} correo(s) correctamente."
        ]);
        exit;
    }
}
