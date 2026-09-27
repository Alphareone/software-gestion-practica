<?php


class ConnectionsController extends MlController {

    protected $baseRoute = '/connections';

    public function dashboard() {
        $this->requireAuth();
        $this->ensureCsrf();

        $userId = (int) $_SESSION['user_id'];
        $db = $this->db();

        $sql = 'SELECT id, store_name, ml_user_id, ml_email, ml_nickname, country, token_expires_at, is_active, created_at FROM ml_connections WHERE is_active = 1 ORDER BY created_at DESC';
        $stmt = $db->query($sql);
        $stmt->execute();
        $connections = $stmt->fetchAll();

        $syncModel = new MlSyncStatusModel($db);
        $cacheModel = new MlProductsCacheModel($db);

        foreach ($connections as $c) {
            $c->token_ok = $c->token_expires_at && strtotime($c->token_expires_at . ' UTC') > time();

            $sync = $syncModel->get((int) $c->id);
            $c->sync_status = $sync->status ?? 'idle';
            $c->last_sync_at = $sync->last_sync_at ?? null;

            $c->product_count = $cacheModel->getCount((int) $c->id);
        }

        $activeStoreId = $_SESSION['ml_active_store_id'] ?? null;
        $activeStore = null;

        if ($activeStoreId) {
            foreach ($connections as $c) {
                if ((int)$c->id === (int)$activeStoreId) {
                    $activeStore = $c;
                    break;
                }
            }
        }

        $hasPendingBatch = $this->hasPendingBatch((int)$_SESSION['user_id']);

        $page_title = 'Conexiones';
        $page_description = 'Gestiona tus tiendas conectadas y métricas.';
        $current_nav = 'connections';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connections,
            'active_store_id' => $activeStoreId,
            'active_store' => $activeStore,
            'has_pending_batch' => $hasPendingBatch,
            'async_metrics' => true,
        ];

        $view_content = __DIR__ . '/../Views/ml/content-dashboard.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function deselect() {
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

        unset($_SESSION['ml_active_store_id']);
        header('Location: ' . $this->route('/dashboard'));
        exit;
    }
}
