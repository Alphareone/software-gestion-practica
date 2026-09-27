<?php


class NotificationsController extends Controller {

    private function auth() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No autenticado.']);
            exit;
        }
    }

    private function requirePost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
            exit;
        }
    }

    public function data() {
        $this->auth();
        $this->checkSessionValidity();
        header('Content-Type: application/json');

        $userId = (int) $_SESSION['user_id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $unreadOnly = !empty($_GET['unread_only']);

        $service = new NotificationService($this->db());
        $result = $service->getNotifications($userId, $page, 20, $unreadOnly);
        $unread = $service->getUnreadCount($userId);

        echo json_encode(array_merge($result, ['unread' => $unread]));
        exit;
    }

    public function markRead() {
        $this->auth();
        $this->checkSessionValidity();
        $this->requirePost();
        header('Content-Type: application/json');

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['error' => 'ID inválido.']);
            exit;
        }

        $service = new NotificationService($this->db());
        $service->markRead($id, (int) $_SESSION['user_id']);

        echo json_encode(['success' => true]);
        exit;
    }

    public function markAllRead() {
        $this->auth();
        $this->checkSessionValidity();
        $this->requirePost();
        header('Content-Type: application/json');

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }

        $service = new NotificationService($this->db());
        $service->markAllRead((int) $_SESSION['user_id']);

        echo json_encode(['success' => true]);
        exit;
    }

    public function delete($id = null) {
        $this->auth();
        $this->checkSessionValidity();
        $this->requirePost();
        header('Content-Type: application/json');

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }

        $id = (int) ($id ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['error' => 'ID inválido.']);
            exit;
        }

        $service = new NotificationService($this->db());
        $service->delete($id, (int) $_SESSION['user_id']);

        echo json_encode(['success' => true]);
        exit;
    }

    public function deleteAll() {
        $this->auth();
        $this->checkSessionValidity();
        $this->requirePost();
        header('Content-Type: application/json');

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }

        $service = new NotificationService($this->db());
        $service->deleteAllForUser((int) $_SESSION['user_id']);

        echo json_encode(['success' => true]);
        exit;
    }

    public function deleteAllUsers() {
        $this->auth();
        $this->checkSessionValidity();
        $this->requirePost();
        header('Content-Type: application/json');

        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Solo administradores.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }

        $service = new NotificationService($this->db());
        $service->deleteAll();

        echo json_encode(['success' => true]);
        exit;
    }

    public function generate() {
        $this->auth();
        $this->checkSessionValidity();
        $this->requirePost();
        header('Content-Type: application/json');

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $storeId = $_SESSION['ml_active_store_id'] ?? null;

        if (!$storeId) {
            echo json_encode(['error' => 'No hay tienda activa.']);
            exit;
        }

        $db = $this->db();

        $stmt = $db->query('SELECT id FROM ml_connections WHERE id = ? AND user_id = ?');
        $stmt->execute([$storeId, $userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Tienda no encontrada o no te pertenece.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Generación de notificaciones deshabilitada.']);
        exit;
    }
}
