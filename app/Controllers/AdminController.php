<?php

class AdminController extends Controller {

    public function __construct() {
        $this->db = new Database();
    }

    public function purgeLogs() {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $_SESSION['error'] = 'Método no permitido.';
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $rateLimiter = new RateLimiter();
        $adminCheck = $rateLimiter->attempt($userId, 'admin_action', $userId);

        if (!$adminCheck['allowed']) {
            $retryAfter = ceil($adminCheck['retry_after'] / 60);
            http_response_code(429);
            error_log("Rate limit exceeded for admin action: user_id={$userId}, action=purgeLogs, retry_after={$adminCheck['retry_after']}s");
            $_SESSION['error'] = "Demasiadas solicitudes administrativas. Intenta en {$retryAfter} minuto(s).";
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        $table = trim((string) ($_POST['table'] ?? ''));
        $limit = (int) ($_POST['limit'] ?? 0);

        $allowedTables = ['price_change_logs', 'login_attempts', 'rate_limits', 'price_batch_pending'];
        if (!in_array($table, $allowedTables) || $limit <= 0) {
            $_SESSION['error'] = 'Parámetros inválidos.';
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        $db = $this->db();
        $limitInt = (int) $limit;
        $stmt = $db->query("DELETE FROM $table ORDER BY id ASC LIMIT $limitInt");
        $stmt->execute();

        $deleted = $stmt->rowCount();

        $activity = new ActivityService($db);
        $activity->log($userId, 'logs_purged', "Purgó {$deleted} registros de {$table}");

        $_SESSION['success'] = "Se eliminaron {$deleted} registros de '{$table}'.";
        header('Location: ' . URLROOT . '/settings');
        exit;
    }

    public function saveSettings() {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $rateLimiter = new RateLimiter();
        $adminCheck = $rateLimiter->attempt($userId, 'admin_action', $userId);

        if (!$adminCheck['allowed']) {
            $retryAfter = ceil($adminCheck['retry_after'] / 60);
            http_response_code(429);
            error_log("Rate limit exceeded for admin action: user_id={$userId}, action=saveSettings, retry_after={$adminCheck['retry_after']}s");
            $_SESSION['error'] = "Demasiadas solicitudes administrativas. Intenta en {$retryAfter} minuto(s).";
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        $allowed = ['logs_retention_months', 'batch_retry_max', 'batch_rate_limit_ms', 'price_batch_chunk_size'];
        $changes = [];

        try {
            $setting = new AppSettingModel($this->db());
            foreach ($allowed as $key) {
                if (isset($_POST[$key])) {
                    $val = trim((string) $_POST[$key]);
                    $old = $setting->get($key, '');
                    if ($old !== $val) {
                        $changes[] = "{$key}: {$old}→{$val}";
                    }
                    $setting->set($key, $val);
                }
            }

            if (!empty($changes)) {
                $activity = new ActivityService($this->db());
                $activity->log($userId, 'settings_changed', 'Cambió configuración: ' . implode(', ', $changes));
            }

            $_SESSION['success'] = 'Configuración guardada correctamente.';
        } catch (Exception $e) {
            error_log('Error al guardar configuración: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al guardar la configuración.';
        }

        header('Location: ' . URLROOT . '/settings');
        exit;
    }

    public function activityLog() {
        $this->requireAdmin();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $search = trim($_GET['q'] ?? '');
        $actionFilter = trim($_GET['action'] ?? '');
        $userId = ($_GET['user'] ?? '') !== '' ? $_GET['user'] : null;

        $activity = new ActivityService($this->db());
        $result = $activity->getLogs($page, $search, $actionFilter, $userId);
        $actions = $activity->getDistinctActions();
        $users = $activity->getDistinctUsers();
        $totalPages = max(1, (int) ceil($result['total'] / $result['per_page']));

        $page_title = 'Registro de actividad';
        $page_description = 'Historial de acciones de administradores.';
        $current_nav = 'activity';
        $is_admin = true;

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'total_pages' => $totalPages,
            'search' => $search,
            'action_filter' => $actionFilter,
            'user_filter' => $userId !== null ? (int) $userId : null,
            'actions' => $actions,
            'users' => $users,
        ];

        $view_content = __DIR__ . '/../Views/admin/content-activity-logs.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function activityLogData() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $search = trim($_GET['q'] ?? '');
        $actionFilter = trim($_GET['action'] ?? '');
        $userId = ($_GET['user'] ?? '') !== '' ? $_GET['user'] : null;

        $activity = new ActivityService($this->db());
        $result = $activity->getLogs($page, $search, $actionFilter, $userId);
        $actions = $activity->getDistinctActions();
        $users = $activity->getDistinctUsers();
        $totalPages = max(1, (int) ceil($result['total'] / $result['per_page']));

        $actionLabels = [
            'login_success'    => 'Inicio de sesión exitoso',
            'login_failed'     => 'Inicio de sesión fallido',
            'logout'           => 'Cierre de sesión',
            'sync_completed'   => 'Sincronización completada',
            'export'           => 'Exportación a XLSX',
            'audit'            => 'Auditoría ejecutada',
            'store_disconnected' => 'Tienda desactivada',
            'store_reactivated'  => 'Tienda reactivada',
            'store_deleted'    => 'Tienda eliminada',
            'store_renamed'    => 'Tienda renombrada',
            'store_selected'   => 'Tienda seleccionada',
            'price_batch'      => 'Lote de precios procesado',
            'user_created'     => 'Usuario creado',
            'user_updated'     => 'Usuario actualizado',
            'user_toggled'     => 'Estado de usuario cambiado',
            'settings_changed' => 'Configuración modificada',
            'logs_purged'      => 'Registros purgados',
            'password_changed' => 'Contraseña cambiada',
            'recovery_codes'   => 'Códigos de recuperación regenerados',
        ];

        $logs = [];
        foreach ($result['items'] as $log) {
            $action = strtolower($log->action ?? '');
            $rowClass = '';
            if (str_contains($action, 'failed') || str_contains($action, 'delete') ||
                str_contains($action, 'error') || str_contains($action, 'purge'))
                $rowClass = 'al-row-delete';
            elseif (str_contains($action, 'export') || str_contains($action, 'audit') ||
                    str_contains($action, 'disconnect') || str_contains($action, 'settings'))
                $rowClass = 'al-row-export';
            elseif (str_contains($action, 'create') || str_contains($action, 'update') ||
                    str_contains($action, 'rename') || str_contains($action, 'reactivate') ||
                    str_contains($action, 'price') || str_contains($action, 'password') ||
                    str_contains($action, 'changed') || str_contains($action, 'upload'))
                $rowClass = 'al-row-modify';
            elseif (str_contains($action, 'login') || str_contains($action, 'logout') ||
                    str_contains($action, 'select') || str_contains($action, 'success'))
                $rowClass = 'al-row-auth';

            $logs[] = [
                'id' => (int) $log->id,
                'time' => date('d/m H:i', strtotime($log->created_at . ' UTC')),
                'user_id' => (int) ($log->user_id ?? 0),
                'username' => $log->username ?? 'Sistema',
                'action' => $log->action,
                'action_label' => $actionLabels[$log->action] ?? $log->action,
                'description' => $log->description,
                'ip' => $log->ip_address ?? '-',
                'row_class' => $rowClass,
            ];
        }

        // Translate actions list for filter dropdown
        $translatedActions = [];
        foreach ($actions as $a) {
            $translatedActions[] = [
                'action' => $a->action,
                'label' => $actionLabels[$a->action] ?? $a->action,
            ];
        }

        // Build users list for filter dropdown
        $usersList = [];
        foreach ($users as $u) {
            $usersList[] = [
                'id' => (int) $u->user_id,
                'username' => $u->username,
            ];
        }

        echo json_encode([
            'logs' => $logs,
            'total' => (int) $result['total'],
            'page' => $page,
            'total_pages' => $totalPages,
            'search' => $search,
            'action_filter' => $actionFilter,
            'user_filter' => $userId !== null ? (int) $userId : null,
            'actions' => $translatedActions,
            'users' => $usersList,
        ]);
        exit;
    }

    public function activityLogDetail($id = null) {
        $this->requireAdmin();

        if (!$id || !is_numeric($id)) {
            header('Location: ' . URLROOT . '/admin/activityLog');
            exit;
        }

        $db = $this->db();
        $stmt = $db->query(
            'SELECT a.*, u.username
             FROM activity_logs a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.id = ?'
        );
        $stmt->execute([(int) $id]);
        $log = $stmt->fetch();

        if (!$log) {
            $_SESSION['error'] = 'Registro de actividad no encontrado.';
            header('Location: ' . URLROOT . '/admin/activityLog');
            exit;
        }

        $actionLabels = [
            'login_success'    => 'Inicio de sesión exitoso',
            'login_failed'     => 'Inicio de sesión fallido',
            'logout'           => 'Cierre de sesión',
            'sync_completed'   => 'Sincronización completada',
            'export'           => 'Exportación a XLSX',
            'audit'            => 'Auditoría ejecutada',
            'store_disconnected' => 'Tienda desactivada',
            'store_reactivated'  => 'Tienda reactivada',
            'store_deleted'    => 'Tienda eliminada',
            'store_renamed'    => 'Tienda renombrada',
            'store_selected'   => 'Tienda seleccionada',
            'price_batch'      => 'Lote de precios procesado',
            'user_created'     => 'Usuario creado',
            'user_updated'     => 'Usuario actualizado',
            'user_toggled'     => 'Estado de usuario cambiado',
            'settings_changed' => 'Configuración modificada',
            'logs_purged'      => 'Registros purgados',
            'password_changed' => 'Contraseña cambiada',
            'recovery_codes'   => 'Códigos de recuperación regenerados',
        ];

        $actionLabel = $actionLabels[$log->action] ?? $log->action;

        $date = new DateTime($log->created_at, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone(date_default_timezone_get()));

        // Compute time ago
        $now = new DateTime();
        $diff = $now->diff($date);
        if ($diff->days === 0 && $diff->h === 0 && $diff->i === 0) {
            $timeAgo = 'Ahora';
        } elseif ($diff->days === 0 && $diff->h === 0) {
            $timeAgo = 'Hace ' . $diff->i . ' min';
        } elseif ($diff->days === 0) {
            $timeAgo = 'Hace ' . $diff->h . ' horas';
        } elseif ($diff->days === 1) {
            $timeAgo = 'Ayer';
        } elseif ($diff->days < 30) {
            $timeAgo = 'Hace ' . $diff->days . ' días';
        } else {
            $timeAgo = $date->format('d/m/Y H:i');
        }

        $page_title = 'Detalle de actividad';
        $page_description = $actionLabel;
        $current_nav = 'activity';
        $is_admin = true;

        $data = [
            'log' => $log,
            'action_label' => $actionLabel,
            'time_ago' => $timeAgo,
            'formatted_date' => $date->format('d/m/Y H:i'),
        ];

        $view_content = __DIR__ . '/../Views/admin/content-activity-log-detail.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function deleteLog() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            exit;
        }

        $db = $this->db();
        $db->query('DELETE FROM activity_logs WHERE id = ?')->execute([$id]);

        $activity = new ActivityService($db);
        $activity->log((int) $_SESSION['user_id'], 'logs_purged', "Eliminó el registro de actividad N°{$id}");

        echo json_encode(['ok' => true]);
        exit;
    }

    public function purgeAllLogs() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $db = $this->db();
        $stmt = $db->query('SELECT COUNT(*) as cnt FROM activity_logs');
        $stmt->execute();
        $count = (int) ($stmt->fetch()->cnt ?? 0);

        $db->query('TRUNCATE TABLE activity_logs')->execute();

        $activity = new ActivityService($db);
        $activity->log((int) $_SESSION['user_id'], 'logs_purged', "Purgó todos los registros de actividad ({$count} eliminados)");

        echo json_encode(['ok' => true, 'deleted' => $count]);
        exit;
    }

    public function activityChartData() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        $db = $this->db();

        // Daily activity (last 7 days)
        $dailyStmt = $db->query(
            "SELECT DATE(created_at) as day, COUNT(*) as cnt
             FROM activity_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $dailyStmt->execute();
        $daily = $dailyStmt->fetchAll();
        $dailyMap = [];
        foreach ($daily as $d) {
            $dailyMap[$d->day] = (int) $d->cnt;
        }
        $dailyLabels = [];
        $dailyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $label = date('d/m', strtotime($date));
            $dailyLabels[] = $label;
            $dailyData[] = $dailyMap[$date] ?? 0;
        }

        // Action distribution
        $actionStmt = $db->query(
            'SELECT action, COUNT(*) as cnt
             FROM activity_logs
             GROUP BY action
             ORDER BY cnt DESC
             LIMIT 6'
        );
        $actionStmt->execute();
        $actions = $actionStmt->fetchAll();

        $actionLabels = [
            'login_success'    => 'Inicio exitoso',
            'login_failed'     => 'Inicio fallido',
            'logout'           => 'Cierre sesión',
            'sync_completed'   => 'Sincronización',
            'export'           => 'Exportación XLSX',
            'audit'            => 'Auditoría',
            'store_disconnected' => 'Tienda desactivada',
            'store_reactivated'  => 'Tienda reactivada',
            'store_deleted'    => 'Tienda eliminada',
            'store_renamed'    => 'Tienda renombrada',
            'store_selected'   => 'Tienda seleccionada',
            'price_batch'      => 'Lote precios',
            'user_created'     => 'Usuario creado',
            'user_updated'     => 'Usuario actualizado',
            'user_toggled'     => 'Usuario modificado',
            'settings_changed' => 'Configuración',
            'logs_purged'      => 'Registros purgados',
            'password_changed' => 'Contraseña',
            'recovery_codes'   => 'Códigos recuperación',
        ];
        $donutLabels = [];
        $donutData = [];
        foreach ($actions as $a) {
            $donutLabels[] = $actionLabels[$a->action] ?? $a->action;
            $donutData[] = (int) $a->cnt;
        }

        // Hourly activity (last 12 hours)
        $hourlyStmt = $db->query(
            "SELECT HOUR(created_at) as hr, COUNT(*) as cnt
             FROM activity_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
             GROUP BY HOUR(created_at)
             ORDER BY hr ASC"
        );
        $hourlyStmt->execute();
        $hourly = $hourlyStmt->fetchAll();
        $hourlyMap = [];
        foreach ($hourly as $h) {
            $hourlyMap[(int) $h->hr] = (int) $h->cnt;
        }
        $hourlyLabels = [];
        $hourlyData = [];
        $currentHour = (int) date('H');
        for ($i = 11; $i >= 0; $i--) {
            $h = ($currentHour - $i + 24) % 24;
            $hourlyLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $hourlyData[] = $hourlyMap[$h] ?? 0;
        }

        echo json_encode([
            'daily' => ['labels' => $dailyLabels, 'data' => $dailyData],
            'donut' => ['labels' => $donutLabels, 'data' => $donutData],
            'hourly' => ['labels' => $hourlyLabels, 'data' => $hourlyData],
        ]);
        exit;
    }

    public function emailLogs() {
        $this->requireAdmin();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $search = trim($_GET['q'] ?? '');
        $templateFilter = trim($_GET['template'] ?? '');

        $logModel = new EmailLogModel($this->db());
        $result = $logModel->findAll($page, 50, $search, $templateFilter);
        $templates = $logModel->getDistinctTemplates();
        $totalPages = max(1, (int) ceil($result['total'] / $result['per_page']));

        $page_title = 'Registro de correos';
        $page_description = 'Historial de correos electrónicos enviados.';
        $current_nav = 'email-logs';
        $is_admin = true;

        $data = [
            'csrf_token'      => $_SESSION['csrf_token'],
            'logs'            => $result['items'],
            'total'           => $result['total'],
            'page'            => $page,
            'total_pages'     => $totalPages,
            'search'          => $search,
            'template_filter' => $templateFilter,
            'templates'       => $templates,
        ];

        $view_content = __DIR__ . '/../Views/admin/content-email-logs.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function purgeEmailLogs() {
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

        $logModel = new EmailLogModel($this->db());
        $deleted = $logModel->purgeAll();

        echo json_encode(['ok' => true, 'deleted' => $deleted]);
        exit;
    }

    public function emailLogData() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $search = trim($_GET['q'] ?? '');
        $templateFilter = trim($_GET['template'] ?? '');

        $logModel = new EmailLogModel($this->db());
        $result = $logModel->findAll($page, 50, $search, $templateFilter);
        $totalPages = max(1, (int) ceil($result['total'] / $result['per_page']));

        $logs = [];
        foreach ($result['items'] as $log) {
            $logs[] = [
                'id'        => (int) $log->id,
                'time'      => date('d/m H:i', strtotime($log->sent_at)),
                'recipient' => $log->recipient_user
                    ? $log->recipient_user . ' <' . $log->recipient_email . '>'
                    : $log->recipient_email,
                'template'  => $log->template,
                'subject'   => $log->subject,
                'status'    => $log->status,
            ];
        }

        echo json_encode([
            'logs' => $logs,
            'total' => (int) $result['total'],
            'page' => $page,
            'total_pages' => $totalPages,
            'search' => $search,
            'template_filter' => $templateFilter,
        ]);
        exit;
    }

    public function mlCredentials() {
        $this->requireAdmin();

        $db = $this->db();
        $settings = new AppSettingModel($db);
        $mlAppId = MlConfigService::getAppId($db);
        $hasDbSecret = (bool) $settings->get('ml_client_secret');

        $page_title = 'Credenciales de MercadoLibre';
        $page_description = 'Configuración de credenciales de MercadoLibre.';
        $current_nav = 'ml-credentials';
        $is_admin = true;

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'ml_app_id' => $mlAppId,
            'ml_has_db' => $hasDbSecret,
        ];

        $view_content = __DIR__ . '/../Views/admin/content-ml-credentials.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function saveMlCredentials() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido.']);
            exit;
        }

        $appId = trim($input['ml_app_id'] ?? '');
        $clientSecret = trim($input['ml_client_secret'] ?? '');

        $db = $this->db();

        if (empty($appId) && empty($clientSecret)) {
            MlConfigService::clearCredentials($db);
            echo json_encode(['ok' => true, 'message' => 'Credenciales eliminadas correctamente.']);
            exit;
        }

        $existingAppId = MlConfigService::getAppId($db);
        $existingSecret = MlConfigService::getClientSecret($db);

        $saveAppId = $appId ?: $existingAppId;
        $saveSecret = $clientSecret ?: $existingSecret;

        if (empty($saveAppId) || empty($saveSecret)) {
            http_response_code(400);
            echo json_encode(['error' => 'Ambos valores deben estar configurados.']);
            exit;
        }

        if ($appId && $clientSecret) {
            MlConfigService::saveCredentials($db, $appId, $clientSecret);
        } elseif ($appId) {
            MlConfigService::saveCredentials($db, $appId, $saveSecret);
        } else {
            MlConfigService::saveCredentials($db, $saveAppId, $clientSecret);
        }

        $a = new ActivityService($db);
        $a->log((int) $_SESSION['user_id'], 'settings_changed', 'Actualizó credenciales de MercadoLibre');

        echo json_encode(['ok' => true, 'message' => 'Credenciales guardadas correctamente.']);
        exit;
    }

    public function getMlSecret() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
            exit;
        }

        if (empty($_SESSION['is_owner'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Solo el dueño puede ver el Client Secret.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido.']);
            exit;
        }

        $db = $this->db();
        $secret = MlConfigService::getClientSecret($db);

        if (empty($secret)) {
            echo json_encode(['ok' => false, 'error' => 'No hay Client Secret configurado.']);
            exit;
        }

        echo json_encode(['ok' => true, 'client_secret' => $secret]);
        exit;
    }

    public function databaseTools() {
        $this->requireAdmin();

        $db = $this->db();

        $notifStmt = $db->query('SELECT COUNT(*) as cnt FROM notifications');
        $notifStmt->execute();
        $notifCount = (int) $notifStmt->fetch()->cnt;

        $activityStmt = $db->query('SELECT COUNT(*) as cnt FROM activity_logs');
        $activityStmt->execute();
        $activityCount = (int) $activityStmt->fetch()->cnt;

        $emailStmt = $db->query('SELECT COUNT(*) as cnt FROM email_logs');
        $emailStmt->execute();
        $emailCount = (int) $emailStmt->fetch()->cnt;

        $auditStmt = $db->query('SELECT COUNT(*) as cnt FROM auditorias');
        $auditStmt->execute();
        $auditCount = (int) $auditStmt->fetch()->cnt;

        $page_title = 'Base de Datos';
        $page_description = 'Mantenimiento y limpieza de registros.';
        $current_nav = 'database-tools';
        $is_admin = true;

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'notif_count' => $notifCount,
            'activity_count' => $activityCount,
            'email_count' => $emailCount,
            'audit_count' => $auditCount,
        ];

        $view_content = __DIR__ . '/../Views/admin/content-database-tools.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }
}
