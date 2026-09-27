<?php

class DashboardController extends Controller {

    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->checkSessionValidity();
        $this->trackActivity();

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $userId = (int) $_SESSION['user_id'];
        $isAdmin = !empty($_SESSION['is_admin']);
        $db = $this->db();

        $page_title = 'Bienvenido, ' . ($_SESSION['username'] ?? 'Usuario');
        $page_description = 'Aquí tienes un resumen del estado de tus tiendas y productos.';
        $current_nav = 'dashboard';
        $is_admin = $isAdmin;

        // ── Store filter ──
        $selectedStoreId = $_GET['store'] ?? 'all';
        $selectedStoreName = '';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'role' => $isAdmin ? 'Administrador' : 'Usuario',
            'is_admin' => $isAdmin,
            'total_users' => 0,
            'latest_users' => [],
            'connections' => [],
            'cenco_connections' => [],
            'product_metrics' => ['total' => 0, 'active' => 0, 'low_stock' => 0, 'stock_ranges' => []],
            'sync_statuses' => [],
            'recent_activity' => [],
            'recent_batches' => [],
            'recent_audits' => [],
            'connected_now' => 0,
            'table_counts' => [],
            'today_activity' => 0,
            'top_action' => null,
            'selected_store_id' => $selectedStoreId,
            'selected_store_name' => '',
        ];

        try {
            $db = $this->db();

            // ── Users ──
            $stmt = $db->query('SELECT COUNT(*) as cnt FROM users');
            $stmt->execute();
            $data['total_users'] = (int) ($stmt->fetch()->cnt ?? 0);

            $stmt = $db->query('SELECT id, username, email, created_at FROM users ORDER BY id DESC LIMIT 5');
            $stmt->execute();
            $data['latest_users'] = $stmt->fetchAll();

            // ── Load Connections (MercadoLibre and Cencosud) ──
            $connModel = new ConnectionModel($db);
            $connections = $connModel->findAll();
            $data['connections'] = $connections;

            $cencoConnModel = new CencosudConnectionModel($db);
            $cencoConnections = $cencoConnModel->findAll();
            $data['cenco_connections'] = $cencoConnections;

            // ── Validate selected store ──
            // El selector acepta "ml_<id>" / "<id>" (MercadoLibre) y "cenco_<id>" (Cencosud).
            $filterConnId = null;
            $filterCencoConnId = null;
            if ($selectedStoreId !== 'all') {
                foreach ($connections as $c) {
                    if ((string) $c->id === $selectedStoreId || 'ml_' . $c->id === $selectedStoreId) {
                        $filterConnId = (int) $c->id;
                        $selectedStoreName = '[MercadoLibre] ' . ($c->store_name ?? '');
                        break;
                    }
                }
                if ($filterConnId === null) {
                    foreach ($cencoConnections as $c) {
                        if ('cenco_' . $c->id === $selectedStoreId) {
                            $filterCencoConnId = (int) $c->id;
                            $selectedStoreName = '[Cencosud] ' . ($c->store_name ?? '');
                            break;
                        }
                    }
                }
                if ($filterConnId === null && $filterCencoConnId === null) {
                    $selectedStoreId = 'all';
                    $data['selected_store_id'] = 'all';
                }
            }
            $data['selected_store_name'] = $selectedStoreName;

            $syncModel = new MlSyncStatusModel($db);
            $centralModel = new ProductsCentralModel($db);
            $cencoSyncModel = new CencosudSyncStatusModel($db);
            $cencoCacheModel = new CencosudProductsCacheModel($db);

            // Process MercadoLibre Connections
            foreach ($connections as $c) {
                $c->unique_id = 'ml_' . $c->id;
                $c->token_ok = $c->token_expires_at && strtotime($c->token_expires_at . ' UTC') > time();
                $sync = $syncModel->get((int) $c->id);
                $c->sync_status = $sync->status ?? 'idle';
                $c->last_sync_at = $sync->last_sync_at ?? null;
                $c->synced_products = (int) ($sync->synced_products ?? 0);
                $c->total_products_sync = (int) ($sync->total_products ?? 0);

                // Conteo crudo de esta tienda (cuántos ítems ML tiene, sin
                // deduplicar por título) — para la tabla "Estado de tiendas".
                $metrics = $centralModel->getMetrics((int) $c->id);
                $c->cache_total = $metrics['total'];
                $c->cache_active = $metrics['active'];
                $c->cache_low_stock = $metrics['low_stock'];
            }

            // Process Cencosud Connections — conteo crudo por tienda para la
            // tabla "Estado de tiendas" (los KPIs globales salen más abajo).
            foreach ($cencoConnections as $c) {
                $c->unique_id = 'cenco_' . $c->id;
                $c->token_ok = $c->token_expires_at && strtotime($c->token_expires_at . ' UTC') > time();
                $sync = $cencoSyncModel->get((int) $c->id);
                $c->sync_status = $sync->status ?? 'idle';
                $c->last_sync_at = $sync->last_sync_at ?? null;
                $c->synced_products = (int) ($sync->synced_products ?? 0);
                $c->total_products_sync = (int) ($sync->total_products ?? 0);

                $metrics = $cencoCacheModel->getMetrics((int) $c->id);
                $c->cache_total = $metrics['total'] ?? 0;
                $c->cache_active = $metrics['published'] ?? 0;
                $c->cache_low_stock = $metrics['low_stock'] ?? 0;
            }

            // KPIs / gráfico: catálogo DEDUPLICADO por título (mismo criterio
            // que /productcentral) — no la suma cruda por tienda, que cuenta
            // el mismo producto una vez por cada tienda donde está publicado.
            // Nota: getGlobalMetrics sólo filtra por conexión de MercadoLibre;
            // al seleccionar una tienda Cencosud ($filterCencoConnId) todavía
            // devuelve el global.
            $data['product_metrics'] = $centralModel->getGlobalMetrics($filterConnId);

            // ── Recent Activity (last 10) ──
            $actModel = new ActivityLogModel($db);
            $actResult = $actModel->findAll(1, 10);
            $data['recent_activity'] = $actResult['items'];

            // ── Activity stats ──
            $data['today_activity'] = $actModel->getTodayCount();
            $topAction = $actModel->getTopAction();
            $data['top_action'] = $topAction ? $topAction->action . ' (' . $topAction->cnt . ')' : null;

            // ── Connected users (active in last 15 min) ──
            $stmt = $db->query("SELECT COUNT(DISTINCT user_id) as cnt FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND user_id IS NOT NULL");
            $stmt->execute();
            $data['connected_now'] = (int) ($stmt->fetch()->cnt ?? 0);

            // ── Price batches (last 5) ──
            $priceLog = new PriceLogModel($db);
            // Handle filters for price batches (MercadoLibre only)
            $isMlFilter = (strpos($selectedStoreId, 'ml_') === 0 || is_numeric($selectedStoreId));
            if ($isMlFilter && $selectedStoreId !== 'all') {
                $mlId = (int)str_replace('ml_', '', $selectedStoreId);
                $data['recent_batches'] = $priceLog->getRecentBatches('l.ml_connection_id = ?', [$mlId]);
            } else {
                $data['recent_batches'] = $priceLog->getRecentBatches('1=1', []);
            }

            // ── Recent audits (last 5) ──
            if ($isMlFilter && $selectedStoreId !== 'all') {
                $mlId = (int)str_replace('ml_', '', $selectedStoreId);
                $auditWhere = 'a.ml_connection_id = ?';
                $auditParams = [$mlId];
            } else {
                $auditWhere = '1=1';
                $auditParams = [];
            }
            $auditSql = "SELECT a.batch_id, a.audit_type, MAX(a.audit_date) as audit_date, a.store_name,
                                COUNT(*) as total_skus,
                                SUM(CASE WHEN a.ml_item_id IS NOT NULL AND a.ml_price IS NOT NULL AND a.diff_amount != 0 THEN 1 ELSE 0 END) as mismatches,
                                SUM(CASE WHEN a.ml_item_id IS NULL THEN 1 ELSE 0 END) as not_found
                         FROM auditorias a
                         WHERE $auditWhere
                         GROUP BY a.batch_id, a.audit_type, a.store_name
                         ORDER BY MAX(a.audit_date) DESC
                         LIMIT 5";
            $stmt = $db->query($auditSql);
            $stmt->execute($auditParams);
            $data['recent_audits'] = $stmt->fetchAll();

            // ── Table counts ──
            $tables = [
                'products_central' => 'products_central',
                'ml_products_cache' => 'ml_products_cache',
                'cencosud_products_cache' => 'cencosud_products_cache',
                'price_change_logs' => 'price_change_logs',
                'auditorias' => 'auditorias',
                'activity_logs' => 'activity_logs',
                'notifications' => 'notifications',
                'users' => 'users',
                'products_master' => 'products_master',
                'web_products_source' => 'web_products_source',
                'ml_connections' => 'ml_connections',
                'cencosud_connections' => 'cencosud_connections',
            ];
            $counts = [];
            foreach ($tables as $label => $table) {
                try {
                    $stmt = $db->query("SELECT COUNT(*) as cnt FROM $table");
                    $stmt->execute();
                    $counts[$label] = (int) ($stmt->fetch()->cnt ?? 0);
                } catch (Exception $e) {
                    $counts[$label] = 0;
                }
            }
            $data['table_counts'] = $counts;

        } catch (Exception $e) {
            error_log('DashboardController error: ' . $e->getMessage());
        }

        $view_content = __DIR__ . '/../Views/dashboard/content-index.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }
}

