<?php
class Controller {
    use TrackActivityTrait;

    protected $db = null;

    public function view($view, $data = []) {
        if (file_exists(dirname(__DIR__) . '/Views/' . $view . '.php')) {
            require_once dirname(__DIR__) . '/Views/' . $view . '.php';
        } else {
            die("La vista no existe.");
        }
    }

    protected function db() {
        if ($this->db === null) {
            $this->db = new Database();
        }
        return $this->db;
    }

    protected function checkSessionValidity() {
        // Session should already be started by the controller
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!isset($_SESSION['user_id'])) {
            return;
        }

        // Detect AJAX / JSON requests — return 403 instead of redirect
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

        // Idle timeout check
        if (defined('SESSION_IDLE_TIMEOUT') && SESSION_IDLE_TIMEOUT > 0) {
            $lastActivity = $_SESSION['last_activity'] ?? 0;
            if ($lastActivity > 0 && (time() - $lastActivity) > SESSION_IDLE_TIMEOUT) {
                session_destroy();
                if ($isAjax) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Sesión expirada.', 'reason' => 'idle_timeout']);
                    exit;
                }
                header('Location: ' . URLROOT . '/auth?reason=idle_timeout');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();

        try {
            $db = new Database();
            $stmt = $db->query('SELECT session_invalidated_at FROM users WHERE id = :id');
            $stmt->bindValue(':id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user && $user->session_invalidated_at) {
                // Force UTC interpretation since MySQL stores datetime in UTC
                $invalidatedTime = strtotime($user->session_invalidated_at . ' UTC');
                $sessionStartTime = $_SESSION['login_time'] ?? 0;

                if ($invalidatedTime > $sessionStartTime) {
                    session_destroy();
                    if ($isAjax) {
                        http_response_code(403);
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Sesión expirada.', 'reason' => 'session_closed']);
                        exit;
                    }
                    header('Location: ' . URLROOT . '/auth?reason=session_closed');
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log('Session validity check failed: ' . $e->getMessage());
        }
    }

    protected function requireAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->checkSessionValidity();
        $this->trackActivity();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }
    }

    protected function requireAdmin() {
        $this->requireAuth();
        if (empty($_SESSION['is_admin'])) {
            $_SESSION['error'] = 'No tienes permiso para acceder a esta sección.';
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    protected function requireOwner() {
        $this->requireAuth();
        if (empty($_SESSION['is_owner'])) {
            $_SESSION['error'] = 'Solo el dueño puede realizar esta acción.';
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}