<?php

class SettingsController extends Controller {

    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $this->checkSessionValidity();

        $db = $this->db();
        $isAdmin = !empty($_SESSION['is_admin']);

        // Get user info from DB
        $stmt = $db->query('SELECT twofa_enabled, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $userRow = $stmt->fetch();
        $twofaEnabled = !empty($userRow->twofa_enabled);
        $memberSince = $userRow->created_at ?? '';
        $dbRole = $userRow->role ?? '';

        // Count remaining recovery codes
        $stmt = $db->query('SELECT COUNT(*) as cnt FROM recovery_codes WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$_SESSION['user_id']]);
        $recoveryCount = (int)$stmt->fetch()->cnt;

        // Load admin settings if admin
        $settings = [];
        if ($isAdmin) {
            $stmt = $db->query('SELECT setting_key, setting_value, description FROM app_settings ORDER BY id');
            $stmt->execute();
            $rows = $stmt->fetchAll();
            foreach ($rows as $r) {
                $settings[$r->setting_key] = $r;
            }
        }

        $page_title = 'Configuración';
        $page_description = 'Preferencias de la cuenta y configuración del sistema.';
        $current_nav = 'settings';

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'is_admin' => $isAdmin,
            'is_owner' => !empty($_SESSION['is_owner']),
            'username' => $_SESSION['username'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role' => $dbRole ?: $_SESSION['role'] ?? '',
            'member_since' => $memberSince,
            'settings' => $settings,
            'twofactor_enabled' => $twofaEnabled,
            'recovery_codes_count' => $recoveryCount,
        ];

        $view_content = __DIR__ . '/../Views/settings/index.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function changePassword() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'No autenticado.']);
            exit;
        }

        $this->checkSessionValidity();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Método no permitido.']);
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }

        $currentPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if ($newPw !== $confirmPw) {
            echo json_encode(['error' => 'Las contraseñas no coinciden.']);
            exit;
        }

        $auth = new AuthService();
        $pwErrors = $auth->validatePassword($newPw);
        if (!empty($pwErrors)) {
            echo json_encode(['error' => implode(' ', $pwErrors)]);
            exit;
        }

        $db = $this->db();
        $stmt = $db->query('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPw, $user->password)) {
            echo json_encode(['error' => 'La contraseña actual es incorrecta.']);
            exit;
        }

        $hashed = password_hash($newPw, PASSWORD_BCRYPT);
        $db->query('UPDATE users SET password = ? WHERE id = ?')->execute([$hashed, $_SESSION['user_id']]);

        $act = new ActivityService($db);
        $act->log((int) $_SESSION['user_id'], 'password_changed', 'Cambió su contraseña');

        echo json_encode(['success' => true]);
        exit;
    }

    public function logoutAllDevices() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $this->checkSessionValidity();

        $db = $this->db();
        $db->query('UPDATE users SET session_invalidated_at = NOW() WHERE id = ?')->execute([$_SESSION['user_id']]);

        $isHttps = isHttps();
        setcookie('flash_success', 'Se cerró la sesión en todos los dispositivos.', [
            'expires' => time() + 5,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_destroy();
        header('Location: ' . URLROOT . '/auth');
        exit;
    }
}
