<?php

class ProductcentralController extends Controller {

    protected function ensureCsrf() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public function index() {
        $this->requireAuth();
        $this->ensureCsrf();

        $connModel = new ConnectionModel($this->db());
        $connections = $connModel->findAll();

        $page_title = 'Catálogo Central de Productos';
        $page_description = 'Vista unificada de todos los productos sincronizados desde tus tiendas conectadas.';
        $current_nav = 'productcentral';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'connections' => $connections,
            'store_filter' => (isset($_GET['store']) && is_numeric($_GET['store'])) ? (int) $_GET['store'] : null,
            'search_query' => trim($_GET['q'] ?? ''),
            'status_filter' => trim($_GET['status'] ?? ''),
            'is_admin' => !empty($_SESSION['is_admin']),
        ];

        $view_content = __DIR__ . '/../Views/productcentral/content-index.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function data() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $connectionId = (isset($_GET['store']) && is_numeric($_GET['store'])) ? (int) $_GET['store'] : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(10, (int) ($_GET['limit'] ?? 20)));
        $search = trim($_GET['q'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $model = new ProductsCentralModel($this->db());
        $result = $model->getProducts($connectionId, $page, $limit, $search, $status);

        if (!empty($result['items'])) {
            $pairs = [];
            foreach ($result['items'] as $item) {
                $pairs[] = ['connection_id' => $item['ml_connection_id'], 'family_key' => $item['family_key']];
            }
            $linkModel = new ProductCrossLinkModel($this->db());
            $links = $linkModel->getLinksForFamilies($pairs);
            foreach ($result['items'] as &$item) {
                $key = $item['ml_connection_id'] . ':' . $item['family_key'];
                $item['cross_link_id'] = $links[$key]['link_id'] ?? null;
                $item['cross_link_store_count'] = $links[$key]['member_count'] ?? 0;
            }
            unset($item);
        }

        echo json_encode($result);
        exit;
    }

    // ── Familias (variantes/packs agrupados) ──

    public function familyMembers() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $connectionId = (int) ($_GET['connection'] ?? 0);
        $familyKey = trim($_GET['family'] ?? '');
        if (!$connectionId || $familyKey === '') {
            echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos.']);
            exit;
        }

        $model = new ProductsCentralModel($this->db());
        echo json_encode(['ok' => true, 'items' => $model->getFamilyMembers($connectionId, $familyKey)]);
        exit;
    }

    // ── Vínculo manual entre tiendas (admin, ver Docs/database/MIGRATION-2026-07-30-PRODUCT-CROSS-LINKS.sql) ──

    public function linkSearch() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        $q = trim($_GET['q'] ?? '');
        $exclude = (isset($_GET['exclude']) && is_numeric($_GET['exclude'])) ? (int) $_GET['exclude'] : null;

        $linkModel = new ProductCrossLinkModel($this->db());
        echo json_encode(['ok' => true, 'items' => $linkModel->search($q, $exclude)]);
        exit;
    }

    public function linkCreate() {
        $this->requireAdmin();
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

        $rawMembers = json_decode($_POST['members'] ?? '[]', true);
        $members = [];
        if (is_array($rawMembers)) {
            foreach ($rawMembers as $m) {
                if (!empty($m['connection_id']) && !empty($m['family_key'])) {
                    $members[] = ['connection_id' => (int) $m['connection_id'], 'family_key' => (string) $m['family_key']];
                }
            }
        }

        $linkModel = new ProductCrossLinkModel($this->db());
        $result = $linkModel->createLink((int) $_SESSION['user_id'], $members);
        echo json_encode($result);
        exit;
    }

    public function linkAddMember() {
        $this->requireAdmin();
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

        $linkId = (int) ($_POST['link_id'] ?? 0);
        $connectionId = (int) ($_POST['connection_id'] ?? 0);
        $familyKey = trim($_POST['family_key'] ?? '');
        if (!$linkId || !$connectionId || $familyKey === '') {
            echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos.']);
            exit;
        }

        $linkModel = new ProductCrossLinkModel($this->db());
        echo json_encode($linkModel->addMember($linkId, $connectionId, $familyKey, (int) $_SESSION['user_id']));
        exit;
    }

    public function linkRemoveMember() {
        $this->requireAdmin();
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

        $linkId = (int) ($_POST['link_id'] ?? 0);
        $connectionId = (int) ($_POST['connection_id'] ?? 0);
        $familyKey = trim($_POST['family_key'] ?? '');
        if (!$linkId || !$connectionId || $familyKey === '') {
            echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos.']);
            exit;
        }

        $linkModel = new ProductCrossLinkModel($this->db());
        echo json_encode($linkModel->removeMember($linkId, $connectionId, $familyKey));
        exit;
    }

    public function linkMembers() {
        $this->requireAuth();
        header('Content-Type: application/json');

        $linkId = (int) ($_GET['link_id'] ?? 0);
        if (!$linkId) {
            echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos.']);
            exit;
        }

        $linkModel = new ProductCrossLinkModel($this->db());
        echo json_encode(['ok' => true, 'items' => $linkModel->getLinkMembers($linkId)]);
        exit;
    }

    // ── Sugerencias en lote (mismo título exacto, 2+ tiendas, sin vínculo) ──

    public function linkSuggestions() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        $linkModel = new ProductCrossLinkModel($this->db());
        echo json_encode([
            'ok' => true,
            'total' => $linkModel->countSuggestions(),
            'items' => $linkModel->getSuggestions($limit, $offset),
        ]);
        exit;
    }

    public function linkDismissSuggestion() {
        $this->requireAdmin();
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

        $matchType = trim($_POST['match_type'] ?? '');
        $matchValue = trim($_POST['match_value'] ?? '');
        if (!in_array($matchType, ['title', 'sku'], true) || $matchValue === '') {
            echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos.']);
            exit;
        }

        $linkModel = new ProductCrossLinkModel($this->db());
        echo json_encode($linkModel->dismissSuggestion($matchType, $matchValue, (int) $_SESSION['user_id']));
        exit;
    }

    // ── Carga de descripciones (admin, botón manual reanudable) ──
    // Ver Docs/database/PRODUCTS-CENTRAL-DESCRIPTIONS.md

    public function descriptionsStatus() {
        $this->requireAdmin();
        header('Content-Type: application/json');

        $model = new ProductsCentralModel($this->db());
        echo json_encode(['ok' => true] + $model->getDescriptionStats());
        exit;
    }

    public function descriptionsProcess() {
        $this->requireAdmin();
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

        $service = new ProductDescriptionService($this->db());
        $result = $service->processNextChunk(100);
        $stats = $service->getStats();

        echo json_encode(['ok' => true, 'chunk' => $result, 'stats' => $stats]);
        exit;
    }
}
