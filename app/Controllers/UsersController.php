<?php

class UsersController extends Controller {

    public function __construct() {
        $this->db = new Database();
    }

    private function validateCsrf() {
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            return false;
        }
        return true;
    }

    private function checkRateLimit() {
        $userId = (int) $_SESSION['user_id'];
        $rateLimiter = new RateLimiter();
        $check = $rateLimiter->attempt($userId, 'admin_action', $userId);
        if (!$check['allowed']) {
            $retryAfter = ceil($check['retry_after'] / 60);
            http_response_code(429);
            error_log("Rate limit exceeded for admin action: user_id={$userId}, retry_after={$check['retry_after']}s");
            $_SESSION['error'] = "Demasiadas solicitudes administrativas. Intenta en {$retryAfter} minuto(s).";
        }
        return $check;
    }

    public function index() {
        $this->requireAdmin();

        $search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

        $userService = new UserService($this->db);
        $roleFilter = isset($_GET['role']) ? $userService->sanitizeRole(trim((string)$_GET['role'])) : '';
        $statusFilter = isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive']) ? $_GET['status'] : '';
        $usuarios = $userService->searchUsers($search, $roleFilter, $statusFilter);

        $page_title = 'Gestionar usuarios';
        $page_description = 'Aquí podrás ver todos los usuarios del sistema.';
        $current_nav = 'users';
        $is_admin = true;

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'usuarios' => $usuarios,
            'search' => $search,
            'role_filter' => $roleFilter,
            'status_filter' => $statusFilter,
            'is_owner' => !empty($_SESSION['is_owner']),
            'urlroot' => URLROOT
        ];

        $view_content = __DIR__ . '/../Views/users/content-index.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function create() {
        $this->requireAdmin();

        $page_title = 'Crear usuario';
        $page_description = 'Crea un nuevo usuario en el sistema.';
        $current_nav = 'users';
        $is_admin = true;

        $data = [
            'csrf_token' => $_SESSION['csrf_token']
        ];

        $view_content = __DIR__ . '/../Views/users/content-create.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function store() {
        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            header('Location: ' . URLROOT . '/users/create');
            exit;
        }

        $rateCheck = $this->checkRateLimit();
        if (!$rateCheck['allowed']) {
            header('Location: ' . URLROOT . '/users/create');
            exit;
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'user'));
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));

        // No se puede crear un Dueño desde la interfaz (solo BD directa)
        if ($role === 'owner') {
            $_SESSION['error'] = 'No puedes crear usuarios Dueño desde la interfaz.';
            header('Location: ' . URLROOT . '/users/create');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'El correo electrónico ingresado no es válido.';
            header('Location: ' . URLROOT . '/users/create');
            exit;
        }

        if (empty($firstName) || empty($lastName)) {
            $_SESSION['error'] = 'Todos los campos son obligatorios.';
            header('Location: ' . URLROOT . '/users/create');
            exit;
        }

        // Generar nombre de usuario automáticamente desde nombre y apellido
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9.]/', '', $firstName . '.' . $lastName)));
        $base = substr($base, 0, 30);
        $newUsername = $base;

        if (empty($newUsername)) {
            $_SESSION['error'] = 'No se pudo generar un nombre de usuario.';
            header('Location: ' . URLROOT . '/users/create');
            exit;
        }

        // Contraseña aleatoria — solo para cumplir el INSERT, nadie la conoce
        // El usuario establece su propia contraseña vía el enlace de activación
        $password = bin2hex(random_bytes(32));

        $userService = new UserService($this->db);

        if ($userService->usernameOrEmailExists($newUsername, $email)) {
            $_SESSION['error'] = 'El usuario o email ya existe.';
            header('Location: ' . URLROOT . '/users/create');
            exit;
        }

        try {
            $userService->createUser($newUsername, $email, $password, $role, $firstName, $lastName);

            // Enviar email de activación con token de un solo uso
            try {
                $auth = new AuthService();
                $resetData = $auth->requestPasswordReset($email);
                if ($resetData) {
                    $resetUrl = URLROOT . '/auth/resetpassword?token=' . urlencode($resetData['token']);
                    $subject = 'Bienvenido a ' . SITENAME;

                    $mail = new MailService();
                    $mail->send($resetData['email'], $subject, 'welcome', [
                        'username'  => $resetData['username'],
                        'first_name' => $resetData['first_name'] ?? $resetData['username'],
                        'reset_url' => $resetUrl,
                    ]);
                }
            } catch (Exception $e) {
                error_log('Error sending activation email: ' . $e->getMessage());
            }

            $n = new NotificationService($this->db());
            $n->notifyUserCreated((int) $_SESSION['user_id'], $newUsername);
            $a = new ActivityService($this->db());
            $a->log((int) $_SESSION['user_id'], 'user_created', "Creó el usuario '{$newUsername}'");
            $_SESSION['success'] = 'Usuario creado exitosamente. Se le ha enviado un correo con las instrucciones de activación.';
            header('Location: ' . URLROOT . '/users');
        } catch (Exception $e) {
            error_log('Error creating user: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al crear el usuario.';
            header('Location: ' . URLROOT . '/users/create');
        }
        exit;
    }

    public function edit($id = null) {
        $this->requireAdmin();

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID de usuario inválido.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        $userService = new UserService($this->db);
        $result = $userService->getUserForEdit((int)$id);

        if (!$result) {
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        // No permitir editar la propia cuenta
        if ((int)$id === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'No puedes editar tu propia cuenta. Pide a otro administrador que lo haga.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        // Solo el dueño puede editar a otro dueño
        if (($result['stats']['role'] ?? '') === 'owner' && empty($_SESSION['is_owner'])) {
            $_SESSION['error'] = 'No tienes permiso para editar a un Dueño.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        $page_title = 'Editar usuario';
        $page_description = 'Modifica los datos del usuario seleccionado.';
        $current_nav = 'users';
        $is_admin = true;

        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'usuario' => $result['user'],
            'user_stats' => $result['stats'],
        ];

        $view_content = __DIR__ . '/../Views/users/content-edit.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function update($id = null) {
        $this->requireAdmin();

        if (!$this->validateCsrf()) {
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        $rateCheck = $this->checkRateLimit();
        if (!$rateCheck['allowed']) {
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID de usuario inválido.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'user'));
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));

        // No permitir editar la propia cuenta
        if ((int)$id === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'No puedes editar tu propia cuenta. Pide a otro administrador que lo haga.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        if (empty($email)) {
            $_SESSION['error'] = 'El email es obligatorio.';
            header('Location: ' . URLROOT . '/users/edit/' . $id);
            exit;
        }

        $userService = new UserService($this->db);

        // Obtener datos actuales para validación de permisos
        $userModel = new UserModel($this->db());
        $currentUser = $userModel->findById((int)$id);
        $currentRole = $currentUser->role ?? '';
        $currentEmail = $currentUser->email ?? null;

        // Solo el dueño puede editar a un dueño (si existe vía BD)
        if ($currentRole === 'owner' && empty($_SESSION['is_owner'])) {
            $_SESSION['error'] = 'No tienes permiso para editar a un Dueño.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }
        // No se puede asignar el rol Dueño desde la interfaz (solo BD directa)
        if ($role === 'owner') {
            $_SESSION['error'] = 'No puedes asignar el rol Dueño desde la interfaz.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        // Validar campos obligatorios
        if (empty($firstName) || empty($lastName)) {
            $_SESSION['error'] = 'Los campos Nombres y Apellidos son obligatorios.';
            header('Location: ' . URLROOT . '/users/edit/' . $id);
            exit;
        }

        if ($userService->emailExistsExcept($email, (int)$id)) {
            $_SESSION['error'] = 'El email ya está en uso.';
            header('Location: ' . URLROOT . '/users/edit/' . $id);
            exit;
        }

        $status = $_POST['status'] ?? '';
        if ($status === 'inactive' && $userService->isTargetingAdmin((int)$id) && empty($_SESSION['is_owner'])) {
            $_SESSION['error'] = 'No puedes deshabilitar al administrador.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }
        if ($status === 'inactive' && (int)$id === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'No puedes deshabilitar tu propia cuenta.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        $db = $this->db();
        try {
            $db->beginTransaction();

            $userService->updateUser((int)$id, $email, $role, $status, null, $firstName, $lastName);

            if ($status === 'inactive') {
                $userService->deactivateUser((int)$id);

                // Notificar deshabilitación
                if ($currentEmail) {
                    try {
                        $mail = new MailService();
                        $mail->send(
                            $currentEmail,
                            'Cuenta deshabilitada - ' . SITENAME,
                            'account-suspended',
                            [
                                'user_id'       => (int) $id,
                                'username'      => $currentUser->username ?? 'Usuario',
                                'first_name'    => $currentUser->first_name ?? ($currentUser->username ?? 'Usuario'),
                                'admin_name'    => $_SESSION['username'] ?? 'Administrador',
                                'suspended_at'  => date('d/m/Y H:i'),
                                'reason'        => 'Actualización realizada por administrador.',
                            ]
                        );
                    } catch (Exception $e) {
                        error_log('[AccountSuspended] Error al notificar: ' . $e->getMessage());
                    }
                }
            }

            $db->commit();
            $a = new ActivityService($this->db());
            $changes = [];
            if ($status === 'inactive') $changes[] = 'deshabilitado';
            $desc = 'Actualizó usuario ID ' . (int)$id . (!empty($changes) ? ' (' . implode(', ', $changes) . ')' : '');
            $a->log((int) $_SESSION['user_id'], 'user_updated', $desc);
            $_SESSION['success'] = 'Usuario actualizado exitosamente.';
            header('Location: ' . URLROOT . '/users');
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error updating user: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al actualizar el usuario.';
            header('Location: ' . URLROOT . '/users/edit/' . $id);
        }
        exit;
    }

    public function delete($id = null) {
        $this->requireOwner();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID inválido.']);
            exit;
        }

        $userId = (int)$id;
        $userService = new UserService($this->db);
        $user = $userService->getUserStatus($userId);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado.']);
            exit;
        }

        if ($user->role === 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'No se puede eliminar a otro dueño.']);
            exit;
        }

        if ($userId === (int) $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'No puedes eliminarte a ti mismo.']);
            exit;
        }

        try {
            $userService->deleteUser($userId);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar el usuario: ' . $e->getMessage()]);
            exit;
        }

        $a = new ActivityService($this->db());
        $a->log((int) $_SESSION['user_id'], 'delete', 'Eliminó al usuario ' . $user->username);

        echo json_encode(['ok' => true]);
        exit;
    }

    public function toggleStatus($id = null) {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        if (!$this->validateCsrf()) {
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        $rateCheck = $this->checkRateLimit();
        if (!$rateCheck['allowed']) {
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            $_SESSION['error'] = 'ID de usuario invalido.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        $userService = new UserService($this->db);
        $user = $userService->getUserStatus((int)$id);

        if (!$user) {
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        if (!empty($user->is_admin) && empty($_SESSION['is_owner'])) {
            $_SESSION['error'] = 'No puedes deshabilitar al administrador.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        // No permitir desactivarse a sí mismo
        if ((int)$id === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'No puedes deshabilitar tu propia cuenta.';
            header('Location: ' . URLROOT . '/users');
            exit;
        }

        // Obtener email antes de togglear (getUserStatus no lo incluye)
        $userModel = new UserModel($this->db());
        $userFull = $userModel->findById((int)$id);
        $userEmail = $userFull->email ?? null;

        try {
            $isBeingDisabled = $user->status === 'active';
            $userService->toggleStatus((int)$id);
            $action = $user->status === 'active' ? 'Deshabilitó' : 'Habilitó';
            $msg = $user->status === 'active'
                ? 'Usuario deshabilitado correctamente.'
                : 'Usuario habilitado correctamente.';

            // Notificar si la cuenta fue deshabilitada
            if ($isBeingDisabled && $userEmail) {
                try {
                    $mail = new MailService();
                    $mail->send(
                        $userEmail,
                        'Cuenta deshabilitada - ' . SITENAME,
                        'account-suspended',
                        [
                            'user_id'       => (int) $user->id,
                            'username'      => $user->username,
                            'first_name'    => $userFull->first_name ?? $user->username,
                            'admin_name'    => $_SESSION['username'] ?? 'Administrador',
                            'suspended_at'  => date('d/m/Y H:i'),
                            'reason'        => 'Sin motivo especificado',
                        ]
                    );
                } catch (Exception $e) {
                    error_log('[AccountSuspended] Error al notificar: ' . $e->getMessage());
                }
            }

            $a = new ActivityService($this->db());
            $a->log((int) $_SESSION['user_id'], 'user_toggled', "{$action} al usuario '{$user->username}'");
            $_SESSION['success'] = $msg;
            header('Location: ' . URLROOT . '/users');
        } catch (Exception $e) {
            error_log('Error toggling user status: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al cambiar el estado del usuario.';
            header('Location: ' . URLROOT . '/users');
        }
        exit;
    }

    public function searchData() {
        header('Content-Type: application/json');
        $this->requireAdmin();

        $search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        $roleFilter = isset($_GET['role']) && $_GET['role'] !== '' ? (new UserService($this->db))->sanitizeRole(trim((string)$_GET['role'])) : '';
        $statusFilter = isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive']) ? $_GET['status'] : '';

        $userService = new UserService($this->db);
        $usuarios = $userService->searchUsers($search, $roleFilter, $statusFilter);

        $roleLabels = ['admin'=>'Administrador','logistics'=>'Logística','sales'=>'Ventas','products'=>'Productos','user'=>'Usuario'];

        $rows = [];
        foreach ($usuarios as $u) {
            $st = $u->status ?? 'active';
            $role = $u->role ?? 'user';
            $lastActivity = $u->last_activity_at ?? null;
            $isOnline = $lastActivity ? (time() - strtotime($lastActivity . ' UTC')) < USER_ONLINE_THRESHOLD : false;
            if ($isOnline) {
                $activityLabel = 'En línea';
                $activityClass = 'online';
            } elseif ($lastActivity) {
                $diff = time() - strtotime($lastActivity . ' UTC');
                if ($diff < 86400) {
                    $activityLabel = 'Hoy ' . date('H:i', strtotime($lastActivity . ' UTC'));
                } elseif ($diff < 172800) {
                    $activityLabel = 'Ayer ' . date('H:i', strtotime($lastActivity . ' UTC'));
                } else {
                    $activityLabel = date('d/m/Y', strtotime($lastActivity . ' UTC'));
                }
                $activityClass = 'offline';
            } else {
                $activityLabel = 'Nunca';
                $activityClass = 'offline';
            }
            $rows[] = [
                'id' => (int)$u->id,
                'username' => $u->username,
                'email' => $u->email,
                'first_name' => $u->first_name ?? '',
                'last_name' => $u->last_name ?? '',
                'full_name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'role' => $role,
                'role_label' => $roleLabels[$role] ?? 'Usuario',
                'status' => $st,
                'twofa_enabled' => !empty($u->twofa_enabled),
                'is_admin' => !empty($u->is_admin),
                'activity_label' => $activityLabel,
                'activity_class' => $activityClass,
                'created_at' => date('d/m/Y', strtotime($u->created_at . ' UTC')),
            ];
        }

        echo json_encode(['users' => $rows, 'total' => count($rows), 'search' => $search, 'role_filter' => $roleFilter, 'is_owner' => !empty($_SESSION['is_owner'])]);
        exit;
    }

    public function status() {
        header('Content-Type: application/json');

        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Metodo no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF invalido']);
            exit;
        }

        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID invalido']);
            exit;
        }

        $userService = new UserService($this->db);
        $user = $userService->getUserStatus($id);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            exit;
        }

        if (!empty($user->is_admin) && empty($_SESSION['is_owner'])) {
            http_response_code(403);
            echo json_encode(['error' => 'No puedes deshabilitar al administrador']);
            exit;
        }

        if ($id === (int)$_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'No puedes deshabilitar tu propia cuenta']);
            exit;
        }

        try {
            $oldStatus = $user->status;
            $newStatus = $userService->toggleStatus($id);
            $action = $oldStatus === 'active' ? 'Deshabilitó' : 'Habilitó';
            $a = new ActivityService($this->db());
            $a->log((int) $_SESSION['user_id'], 'user_toggled', "{$action} al usuario '{$user->username}'");
            echo json_encode(['status' => $newStatus]);
        } catch (Exception $e) {
            error_log('Error toggling user status: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error interno']);
        }
        exit;
    }

    public function sendResetLink($id = null) {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido']);
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID inválido']);
            exit;
        }

        $userService = new UserService($this->db());
        $user = $userService->getUserStatus((int)$id);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            exit;
        }

        try {
            $auth = new AuthService();
            $resetData = $auth->requestPasswordReset($user->email);

            if (!$resetData) {
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo generar el enlace de restablecimiento']);
                exit;
            }

            $resetUrl = URLROOT . '/auth/resetpassword?token=' . urlencode($resetData['token']);
            $subject = 'Restablecimiento de contraseña - ' . SITENAME;

            $mail = new MailService();
            $mail->send($resetData['email'], $subject, 'admin-reset', [
                'username'  => $resetData['username'],
                'first_name' => $resetData['first_name'] ?? $resetData['username'],
                'reset_url' => $resetUrl,
            ]);

            $a = new ActivityService($this->db());
            $a->log((int) $_SESSION['user_id'], 'password_reset', "Envió link de restablecimiento a '{$user->username}' (ID {$id})");

            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            error_log('Error sending reset link: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al enviar el enlace']);
        }
        exit;
    }

    public function resetTwoFactor($id = null) {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido']);
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID inválido']);
            exit;
        }

        $userService = new UserService($this->db());
        $user = $userService->getUserStatus((int)$id);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            exit;
        }

        if (!empty($user->is_admin) && empty($_SESSION['is_owner'])) {
            http_response_code(403);
            echo json_encode(['error' => 'No puedes modificar a un administrador']);
            exit;
        }

        try {
            $model = new UserModel($this->db());
            $model->resetTwoFactor((int)$id);

            $a = new ActivityService($this->db());
            $a->log((int) $_SESSION['user_id'], 'user_2fa_reset', "Reseteó 2FA del usuario ID {$id}");

            echo json_encode(['ok' => true, 'message' => 'Autenticación en dos pasos reseteada.']);
        } catch (Exception $e) {
            error_log('Error resetting 2FA: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al resetear 2FA']);
        }
        exit;
    }

    public function forceLogout($id = null) {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido']);
            exit;
        }

        if (empty($id) || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID inválido']);
            exit;
        }

        $userService = new UserService($this->db());
        $user = $userService->getUserStatus((int)$id);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            exit;
        }

        if (!empty($user->is_admin) && empty($_SESSION['is_owner'])) {
            http_response_code(403);
            echo json_encode(['error' => 'No puedes forzar cierre a un administrador']);
            exit;
        }

        try {
            $userService->deactivateSession((int)$id);

            $a = new ActivityService($this->db());
            $a->log((int) $_SESSION['user_id'], 'force_logout', "Forzó cierre de sesión del usuario ID {$id}");

            echo json_encode(['ok' => true, 'message' => 'Sesión cerrada forzosamente.']);
        } catch (Exception $e) {
            error_log('Error forcing logout: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al forzar cierre de sesión']);
        }
        exit;
    }
}
