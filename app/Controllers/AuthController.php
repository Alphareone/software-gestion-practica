<?php

class AuthController extends Controller {

    private $dummyHash = '$2y$10$8xj9PYwlx0WPe0ri0h8W9en5Bq4JfUrRcQOQpQ5xByNuuQboPzTGW';
    private $rememberCookieName = 'remember_2fa';

    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // If idle_timeout reason is present, force session destruction first
        if (isset($_GET['reason']) && $_GET['reason'] === 'idle_timeout') {
            $_SESSION = [];
            session_destroy();
            $forceLogout = true;
        } else {
            $forceLogout = false;
        }

        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }

        $this->ensureCsrfToken();

        if ($forceLogout) {
            session_start();
            session_regenerate_id(true);
            $_SESSION['warning'] = 'Tu sesión ha expirado por inactividad. Por favor inicia sesión nuevamente.';
            $this->ensureCsrfToken();
            header('Location: ' . URLROOT . '/auth');
            exit;
        } elseif (isset($_GET['reason']) && $_GET['reason'] === 'session_closed') {
            $_SESSION['warning'] = 'Un administrador ha cerrado tu sesión. Por favor inicia sesión nuevamente.';
        }

        $page_title = 'Inicio de sesión';
        $view_content = __DIR__ . '/../Views/auth/login.php';
        $data = [
            'csrf_token' => $_SESSION['csrf_token']
        ];
        require __DIR__ . '/../Views/layouts/auth.php';
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Limpiar flash messages de sesiones anteriores antes de cualquier validación
        unset($_SESSION['warning'], $_SESSION['info']);

        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Sesion invalida. Recarga la pagina e intenta nuevamente.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || empty($password)) {
            $_SESSION['error'] = 'Credenciales invalidas';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $rateLimiter = new RateLimiter();
        $loginCheck = $rateLimiter->attempt($username, 'login', null);

        if (!$loginCheck['allowed']) {
            $retryAfter = ceil($loginCheck['retry_after'] / 60);
            error_log("Rate limit exceeded for login: username={$username}, ip={$ipAddress}, retry_after={$loginCheck['retry_after']}s");
            $_SESSION['error'] = "Demasiados intentos de inicio de sesion. Intenta nuevamente en {$retryAfter} minuto(s).";
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $auth = new AuthService();
        $user = $auth->getUserByUsername($username);
        $passwordHash = $user ? (string)$user->password : $this->dummyHash;
        $isValidPassword = password_verify($password, $passwordHash);

        if (!$user || !$isValidPassword) {
            $auth->logAttempt($username, $ipAddress);
            $act = new ActivityService();
            $act->log(null, 'login_failed', "Intento de login fallido para '{$username}' desde {$ipAddress}");
            $_SESSION['error'] = 'Usuario o contrasena incorrectos';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (!$auth->isActive($user)) {
            $_SESSION['error'] = 'Tu cuenta ha sido deshabilitada. Contacta al administrador.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (!$auth->hasTwoFactorColumns()) {
            $_SESSION['error'] = 'Faltan columnas 2FA en la tabla users. Ejecuta el SQL de migracion.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $auth->clearAttempts($username);
        $rateLimiter->reset($username, 'login', null);

        if ($this->hasValidRememberDevice($user)) {
            $auth->completeLoginSession($user);
            $this->setRememberDevice($user->id);
            $act = new ActivityService();
            $act->log($user->id, 'login_success', "Inicio de sesión desde {$ipAddress} (recordar dispositivo)");
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }

        $tfa = $auth->getOrCreateTwoFactorSecret($user->id);

        $_SESSION['pending_2fa'] = [
            'user_id' => (int)$user->id,
            'username' => (string)$user->username,
            'email' => (string)$user->email,
            'needs_setup' => $tfa['needs_setup'],
            'secret' => $tfa['secret']
        ];

        $this->ensureCsrfToken(true);

        header('Location: ' . URLROOT . '/auth/twofactor');
        exit;
    }

    public function twofactor() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['pending_2fa'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $this->ensureCsrfToken();

        $pending = $_SESSION['pending_2fa'];
        if (!empty($pending['needs_setup'])) {
            $issuer = SITENAME;
            $accountName = (string)$pending['username'];
            $secret = (string)$pending['secret'];
            $ga = new GoogleAuthenticator();
            $qrCodeUrl = $ga->getQRCodeGoogleUrl($accountName, $secret, $issuer, ['width' => 240, 'height' => 240]);
            $otpauthUri = 'otpauth://totp/' . urlencode($issuer . ':' . $accountName) . '?secret=' . $secret . '&issuer=' . urlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';

            $page_title = 'Configurar 2FA';
            $view_content = __DIR__ . '/../Views/auth/twofactor_setup.php';
            $data = [
                'csrf_token' => $_SESSION['csrf_token'],
                'secret' => $secret,
                'qrcode_url' => $qrCodeUrl,
                'otpauth_uri' => $otpauthUri
            ];
            require __DIR__ . '/../Views/layouts/auth.php';
            return;
        }

        $recoveryMode = !empty($_SESSION['recovery_mode']);
        unset($_SESSION['recovery_mode']);

        $page_title = 'Verificar 2FA';
        $view_content = __DIR__ . '/../Views/auth/twofactor_verify.php';
        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'recovery_mode' => $recoveryMode
        ];
        require __DIR__ . '/../Views/layouts/auth.php';
    }

    public function verifytwofactor() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['pending_2fa'])) {
            $_SESSION['error'] = 'Sesion 2FA no iniciada.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Sesion invalida. Recarga la pagina e intenta nuevamente.';
            header('Location: ' . URLROOT . '/auth/twofactor');
            exit;
        }

        $pending = $_SESSION['pending_2fa'];
        $userId = (int)$pending['user_id'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $rateLimiter = new RateLimiter();
        $tfaCheck = $rateLimiter->attempt($userId, '2fa', $userId);

        if (!$tfaCheck['allowed']) {
            $retryAfter = ceil($tfaCheck['retry_after'] / 60);
            error_log("Rate limit exceeded for 2FA: user_id={$userId}, ip={$ipAddress}, retry_after={$tfaCheck['retry_after']}s");
            $_SESSION['error'] = "Demasiados intentos de verificacion 2FA. Intenta nuevamente en {$retryAfter} minuto(s).";
            header('Location: ' . URLROOT . '/auth/twofactor');
            exit;
        }

        $otp = preg_replace('/\s+/', '', (string)($_POST['otp'] ?? ''));
        $rememberDevice = !empty($_POST['remember_device']) && $_POST['remember_device'] === '1';

        if (!preg_match('/^\d{6}$/', $otp)) {
            $_SESSION['error'] = 'El codigo debe tener 6 digitos.';
            header('Location: ' . URLROOT . '/auth/twofactor');
            exit;
        }

        $auth = new AuthService();
        $user = $auth->getUserById($userId);
        if (!$user) {
            unset($_SESSION['pending_2fa']);
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (!$auth->verifyTwoFactor($userId, $otp)) {
            $_SESSION['error'] = 'Codigo 2FA invalido.';
            header('Location: ' . URLROOT . '/auth/twofactor');
            exit;
        }

        $rateLimiter->reset($userId, '2fa', $userId);

        if ((int)($user->twofa_enabled ?? 0) !== 1) {
            $auth->enableTwoFactor($user->id);
        }

        $wasFirstSetup = !empty($pending['needs_setup']);
        $auth->completeLoginSession($user);

        $act = new ActivityService();
        $act->log($user->id, 'login_success', "Inicio de sesión desde {$ipAddress}");

        if ($wasFirstSetup) {
            $codes = $auth->generateRecoveryCodes($user->id);
            $_SESSION['pending_recovery_codes'] = $codes;
            session_write_close();
            header('Location: ' . URLROOT . '/auth/recoverycodes');
            exit;
        }

        if ($rememberDevice) {
            $this->setRememberDevice($user->id);
        } else {
            $this->clearRememberDevice($user->id);
        }

        header('Location: ' . URLROOT . '/dashboard');
        exit;
    }

    public function recoveryCodes() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $codes = $_SESSION['pending_recovery_codes'] ?? [];
        if (empty($codes)) {
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }

        $this->ensureCsrfToken();
        $page_title = 'Códigos de recuperación';
        $view_content = __DIR__ . '/../Views/auth/recovery_codes.php';
        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'codes' => $codes
        ];
        require __DIR__ . '/../Views/layouts/auth.php';
    }

    public function confirmRecoveryCodes() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Sesion invalida.';
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }

        unset($_SESSION['pending_recovery_codes']);
        $_SESSION['success'] = 'Inicio de sesion exitoso.';
        header('Location: ' . URLROOT . '/dashboard');
        exit;
    }

    public function regenerateRecoveryCodes() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Sesion invalida.';
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $password = $_POST['password'] ?? '';

        $auth = new AuthService();
        if (!$auth->verifyPasswordById($userId, $password)) {
            $_SESSION['error'] = 'Contrasena incorrecta.';
            header('Location: ' . URLROOT . '/settings');
            exit;
        }

        $auth->deleteUnusedRecoveryCodes($userId);
        $codes = $auth->generateRecoveryCodes($userId);
        $_SESSION['pending_recovery_codes'] = $codes;

        $a = new ActivityService();
        $a->log($userId, 'recovery_codes', 'Regeneró sus códigos de recuperación');

        header('Location: ' . URLROOT . '/auth/recoverycodes');
        exit;
    }

    public function verifyrecovery() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['pending_2fa'])) {
            $_SESSION['error'] = 'Sesion 2FA no iniciada.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Sesion invalida.';
            $_SESSION['recovery_mode'] = true;
            header('Location: ' . URLROOT . '/auth/twofactor');
            exit;
        }

        $pending = $_SESSION['pending_2fa'];
        $userId = (int)$pending['user_id'];
        $inputCode = trim(strtoupper((string)($_POST['recovery_code'] ?? '')));

        if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $inputCode)) {
            $_SESSION['error'] = 'Formato invalido. El codigo debe ser XXXX-XXXX-XXXX.';
            $_SESSION['recovery_mode'] = true;
            header('Location: ' . URLROOT . '/auth/twofactor');
            exit;
        }

        $rateLimiter = new RateLimiter();
        $check = $rateLimiter->attempt($userId, '2fa', $userId);
        if (!$check['allowed']) {
            $retryAfter = ceil($check['retry_after'] / 60);
            $_SESSION['error'] = "Demasiados intentos. Intenta nuevamente en {$retryAfter} minuto(s).";
            $_SESSION['recovery_mode'] = true;
            header('Location: ' . URLROOT . '/auth/twofactor');
            exit;
        }

        $auth = new AuthService();
        $matched = $auth->verifyRecoveryCode($userId, $inputCode);

        if (!$matched) {
            $_SESSION['error'] = 'Codigo de recuperacion incorrecto o ya utilizado.';
            $_SESSION['recovery_mode'] = true;
            header('Location: ' . URLROOT . '/auth/twofactor');
            exit;
        }

        $rateLimiter->reset($userId, '2fa', $userId);

        $user = $auth->getUserById($userId);
        if (!$user) {
            unset($_SESSION['pending_2fa']);
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $auth->completeLoginSession($user);
        $act = new ActivityService();
        $act->log($user->id, 'login_success', "Inicio de sesión con código de recuperación");
        header('Location: ' . URLROOT . '/dashboard');
        exit;
    }

    public static function isUserAdmin($username) {
        if (isset($_SESSION['is_admin'])) {
            return (bool)$_SESSION['is_admin'];
        }
        try {
            $auth = new AuthService();
            return $auth->isUserAdmin($username);
        } catch (Exception $e) {
            return false;
        }
    }

    private function ensureCsrfToken($forceNew = false) {
        if ($forceNew || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    private function isValidCsrf($token) {
        if (empty($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    private function setRememberDevice($userId) {
        $auth = new AuthService();
        $tokenData = $auth->generateRememberToken($userId);
        $this->setRememberCookie($userId . ':' . $tokenData['token'], $tokenData['expires_at']);
    }

    private function hasValidRememberDevice($user) {
        if (empty($_COOKIE[$this->rememberCookieName])) {
            return false;
        }
        $cookieValue = (string)$_COOKIE[$this->rememberCookieName];
        $auth = new AuthService();
        return $auth->hasValidRememberDevice($user, $cookieValue);
    }

    private function clearRememberDevice($userId) {
        $auth = new AuthService();
        $auth->clearRememberToken($userId);
        $this->setRememberCookie('', time() - 3600);
    }

    private function setRememberCookie($value, $expiresAt) {
        $isHttps = isHttps();
        setcookie($this->rememberCookieName, $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }


    // ── Help / Recovery ──

    public function helpauth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }
        $this->ensureCsrfToken();
        $page_title = 'Ayuda / Recuperación';
        $view_content = __DIR__ . '/../Views/auth/help.php';
        $data = ['csrf_token' => $_SESSION['csrf_token']];
        require __DIR__ . '/../Views/layouts/auth.php';
    }

    public function forgotpassword() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Sesión inválida. Recarga la página e intenta nuevamente.';
                header('Location: ' . URLROOT . '/auth/helpauth');
                exit;
            }

            $email = trim($_POST['email'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Ingresa un correo electrónico válido.';
                header('Location: ' . URLROOT . '/auth/helpauth');
                exit;
            }

            $rateLimiter = new RateLimiter();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $check = $rateLimiter->attempt($ipAddress, 'password_reset', null);

            if (!$check['allowed']) {
                $retryAfter = ceil($check['retry_after'] / 60);
                $_SESSION['error'] = "Demasiados intentos. Intenta nuevamente en {$retryAfter} minuto(s).";
                header('Location: ' . URLROOT . '/auth/helpauth');
                exit;
            }

            $auth = new AuthService();
            $resetData = $auth->requestPasswordReset($email);

            if ($resetData) {
                $resetUrl = URLROOT . '/auth/resetpassword?token=' . urlencode($resetData['token']);
                $subject = 'Recuperación de contraseña - ' . SITENAME;

                $mail = new MailService();
                $mail->send($resetData['email'], $subject, 'password-reset', [
                    'username'  => $resetData['username'],
                    'first_name' => $resetData['first_name'] ?? $resetData['username'],
                    'reset_url' => $resetUrl,
                ]);
            }

            $_SESSION['info'] = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $this->ensureCsrfToken();
        $page_title = 'Restablecer contraseña';
        $view_content = __DIR__ . '/../Views/auth/forgot_password.php';
        $data = ['csrf_token' => $_SESSION['csrf_token']];
        require __DIR__ . '/../Views/layouts/auth.php';
    }

    public function resetpassword() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = trim($_GET['token'] ?? '');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Sesión inválida. Recarga la página e intenta nuevamente.';
                header('Location: ' . URLROOT . '/auth');
                exit;
            }

            $token = trim($_POST['token'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['password_confirm'] ?? '';

            if ($password !== $confirmPassword) {
                $_SESSION['error'] = 'Las contraseñas no coinciden.';
                header('Location: ' . URLROOT . '/auth/resetpassword?token=' . urlencode($token));
                exit;
            }

            $auth = new AuthService();
            $errors = $auth->validatePassword($password);
            if (!empty($errors)) {
                $_SESSION['error'] = $errors[0];
                header('Location: ' . URLROOT . '/auth/resetpassword?token=' . urlencode($token));
                exit;
            }

            $resetRow = $auth->validateResetToken($token);
            if (!$resetRow) {
                $_SESSION['error'] = 'El enlace de restablecimiento es inválido o ya expiró.';
                header('Location: ' . URLROOT . '/auth');
                exit;
            }

            $auth->completePasswordReset((int) $resetRow->id, (int) $resetRow->user_id, $password);

            // Notificar cambio de contraseña
            try {
                $user = $auth->getUserById((int) $resetRow->user_id);
                if ($user && !empty($user->email)) {
                    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $mail = new MailService();
                    $mail->send(
                        $user->email,
                        'Contraseña actualizada - ' . SITENAME,
                        'password-changed',
                        [
                        'user_id'       => (int) $user->id,
                        'username'      => $user->username,
                        'first_name'    => $user->first_name ?? $user->username,
                        'changed_at'    => date('d/m/Y H:i'),
                            'changed_ip'    => $ip,
                            'changed_device' => MailService::parseUserAgent($ua),
                            'reset_url'     => URLROOT . '/auth/resetpassword?token=',
                        ]
                    );
                }
            } catch (Exception $e) {
                error_log('[PasswordChanged] Error al notificar: ' . $e->getMessage());
            }

            $act = new ActivityService();
            $act->log((int) $resetRow->user_id, 'password_reset', 'Restableció su contraseña vía email');

            $isHttps = isHttps();
            setcookie('flash_success', 'Contraseña actualizada correctamente. Inicia sesión.', [
                'expires' => time() + 5,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if ($token === '') {
            $_SESSION['error'] = 'Enlace inválido.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $auth = new AuthService();
        $resetRow = $auth->validateResetToken($token);
        if (!$resetRow) {
            $_SESSION['error'] = 'El enlace de restablecimiento es inválido o ya expiró.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $this->ensureCsrfToken();
        $page_title = 'Nueva contraseña';
        $view_content = __DIR__ . '/../Views/auth/reset_password.php';
        $data = [
            'csrf_token' => $_SESSION['csrf_token'],
            'token' => $token,
        ];
        require __DIR__ . '/../Views/layouts/auth.php';
    }

    public function forgotusername() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Sesión inválida. Recarga la página e intenta nuevamente.';
                header('Location: ' . URLROOT . '/auth/helpauth');
                exit;
            }

            $email = trim($_POST['email'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Ingresa un correo electrónico válido.';
                header('Location: ' . URLROOT . '/auth/helpauth');
                exit;
            }

            $rateLimiter = new RateLimiter();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $check = $rateLimiter->attempt($ipAddress, 'password_reset', null);

            if (!$check['allowed']) {
                $retryAfter = ceil($check['retry_after'] / 60);
                $_SESSION['error'] = "Demasiados intentos. Intenta nuevamente en {$retryAfter} minuto(s).";
                header('Location: ' . URLROOT . '/auth/helpauth');
                exit;
            }

            $auth = new AuthService();
            $user = $auth->findByEmail($email);

            if ($user) {
                $subject = 'Recordatorio de usuario - ' . SITENAME;

                $mail = new MailService();
                $mail->send($user->email, $subject, 'username-reminder', [
                    'username' => $user->username,
                    'first_name' => $user->first_name ?? $user->username,
                ]);
            }

            $_SESSION['info'] = 'Si el correo está registrado, recibirás un recordatorio de tu usuario.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $this->ensureCsrfToken();
        $page_title = 'Recordar usuario';
        $view_content = __DIR__ . '/../Views/auth/forgot_username.php';
        $data = ['csrf_token' => $_SESSION['csrf_token']];
        require __DIR__ . '/../Views/layouts/auth.php';
    }

    public function contact() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/dashboard');
            exit;
        }
        $page_title = 'Contactar a soporte';
        $view_content = __DIR__ . '/../Views/auth/contact.php';
        $data = [];
        require __DIR__ . '/../Views/layouts/auth.php';
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token de sesión inválido.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $isHttps = isHttps();

        $flash = 'Has cerrado sesión correctamente.';
        setcookie('flash_success', $flash, [
            'expires' => time() + 5,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        $loggedUserId = (int) ($_SESSION['user_id'] ?? 0);
        $loggedUsername = (string) ($_SESSION['username'] ?? '');

        if ($loggedUserId > 0) {
            $act = new ActivityService();
            $act->log($loggedUserId, 'logout', "Cierre de sesión de '{$loggedUsername}'");
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        header('Location: ' . URLROOT . '/auth');
        exit;
    }

    public function keepalive() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user_id'])) {
            $_SESSION['last_activity'] = time();
            $auth = new AuthService();
            $auth->updateActivity((int) $_SESSION['user_id']);
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    public function forgetdevice() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            $_SESSION['error'] = 'Debes estar autenticado.';
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $currentUsername = (string)$_SESSION['username'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token de sesion inválido.';
                header('Location: ' . URLROOT . '/dashboard');
                exit;
            }

            $confirmUsername = trim((string)($_POST['username_confirm'] ?? ''));
            if ($confirmUsername !== $currentUsername) {
                $_SESSION['error'] = 'Nombre de usuario incorrecto.';
                header('Location: ' . URLROOT . '/dashboard');
                exit;
            }
        }

        $this->clearRememberDevice($userId);

        $_SESSION['success'] = 'El dispositivo ha sido desvínculado. Necesitarás tu código 2FA en el próximo inicio de sesión.';
        header('Location: ' . URLROOT . '/dashboard');
        exit;
    }
}
