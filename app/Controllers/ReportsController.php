<?php

class ReportsController extends Controller {

    protected function ensureCsrf() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public function index() {
        $this->requireAuth();
        $this->ensureCsrf();

        $page_title = 'Reportes';
        $page_description = 'Inventario, auditorías y precios de ambos canales.';
        $current_nav = 'reports';

        $data = ['csrf_token' => $_SESSION['csrf_token']];

        $view_content = __DIR__ . '/../Views/reports/content-hub.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    // ── Inventario ──

    public function inventory() {
        $this->requireAuth();
        $this->ensureCsrf();

        $page_title = 'Reportes - Inventario';
        $page_description = 'Estado del catálogo por tienda, ambos canales.';
        $current_nav = 'reports';

        $service = new ReportService($this->db());
        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'summary' => $service->inventorySummary(),
        ];

        $view_content = __DIR__ . '/../Views/reports/content-inventory.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function exportInventory($channel = 'all') {
        $this->requireAuth();

        $service = new ReportService($this->db());
        $rows = $service->inventoryDetail($channel);

        require_once __DIR__ . '/../Helpers/XlsxWriter.php';
        $xlsx = new XlsxWriter();
        $xlsx->setHeaders(['Canal', 'Tienda', 'SKU', 'Título', 'Precio', 'Stock', 'Estado', 'Actualizado']);
        foreach ($rows as $r) {
            $xlsx->addRow([$r['channel'], $r['store_name'], $r['sku'], $r['title'], $r['price'], $r['stock'], $r['status'], $r['synced_at']]);
        }

        $filename = 'reporte_inventario_' . $channel . '_' . date('Ymd_His') . '.xlsx';
        $tmpDir = ini_get('session.save_path') ?: sys_get_temp_dir();
        $tmpPath = tempnam($tmpDir, 'report_inv_') . '.xlsx';
        $xlsx->output($tmpPath);

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpPath));
        readfile($tmpPath);
        unlink($tmpPath);
        exit;
    }

    // ── Auditorías ──

    public function audits() {
        $this->requireAuth();
        $this->ensureCsrf();

        $page_title = 'Reportes - Auditorías';
        $page_description = 'Resumen histórico de auditorías por canal.';
        $current_nav = 'reports';

        $service = new ReportService($this->db());
        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'summary' => $service->auditsSummary(),
            'recurring' => $service->recurringMismatches(20),
        ];

        $view_content = __DIR__ . '/../Views/reports/content-audits.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    // ── Precios ──

    public function prices() {
        $this->requireAuth();
        $this->ensureCsrf();

        $page_title = 'Reportes - Precios';
        $page_description = 'Snapshot de precios actuales, ambos canales.';
        $current_nav = 'reports';

        $service = new ReportService($this->db());
        $rows = $service->priceSnapshot();

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'total' => count($rows),
            'preview' => array_slice($rows, 0, 50),
        ];

        $view_content = __DIR__ . '/../Views/reports/content-prices.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function exportPrices() {
        $this->requireAuth();

        $service = new ReportService($this->db());
        $rows = $service->priceSnapshot();

        require_once __DIR__ . '/../Helpers/XlsxWriter.php';
        $xlsx = new XlsxWriter();
        $xlsx->setHeaders(['Canal', 'Tienda', 'SKU', 'Título', 'Precio', 'Stock', 'Estado', 'Actualizado']);
        foreach ($rows as $r) {
            $xlsx->addRow([$r['channel'], $r['store_name'], $r['sku'], $r['title'], $r['price'], $r['stock'], $r['status'], $r['synced_at']]);
        }

        $filename = 'reporte_precios_' . date('Ymd_His') . '.xlsx';
        $tmpDir = ini_get('session.save_path') ?: sys_get_temp_dir();
        $tmpPath = tempnam($tmpDir, 'report_price_') . '.xlsx';
        $xlsx->output($tmpPath);

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpPath));
        readfile($tmpPath);
        unlink($tmpPath);
        exit;
    }

    // ── Ventas ──

    public function sales() {
        $this->requireAuth();
        $this->ensureCsrf();

        $page_title = 'Reportes - Ventas';
        $page_description = 'Ventas Walmart por tienda, producto y día.';
        $current_nav = 'reports';

        $dateFrom = $_GET['from'] ?? (new DateTime('-30 days'))->format('Y-m-d');
        $dateTo = $_GET['to'] ?? (new DateTime())->format('Y-m-d');

        $service = new ReportService($this->db());
        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'summary' => $service->salesSummary($dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'),
            'sync_status' => $service->salesSyncStatus(),
        ];

        $view_content = __DIR__ . '/../Views/reports/content-sales.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    /** Sync manual de órdenes (botón "Actualizar ventas") — la versión automática corre por cron. */
    public function syncSales() {
        $this->requireAuth();
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

        set_time_limit(300);
        $db = $this->db();
        $connections = (new WalmartConnectionModel($db))->findActive();
        $syncService = new WalmartOrderSyncService($db);

        $results = [];
        foreach ($connections as $c) {
            $r = $syncService->syncStore((int) $c->id, 30);
            $results[] = ['store' => $c->store_name, 'ok' => $r['ok'], 'synced' => $r['synced'], 'failed' => $r['failed'], 'error' => $r['error']];
        }

        echo json_encode(['ok' => true, 'results' => $results]);
        exit;
    }
}
