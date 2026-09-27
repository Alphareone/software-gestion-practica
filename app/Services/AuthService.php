<?php


class AuthService {
    private $user;

    public function __construct($db = null) {
        $this->user = new UserModel($db);
    }

    public function getUserByUsername($username) {
        return $this->user->findByUsername($username);
    }

    public function getUserById($id) {
        return $this->user->findById($id);
    }

    public function verifyPassword($user, $password) {
        if (!$user) return false;
        return password_verify($password, (string) $user->password);
    }

    public function isActive($user) {
        return $user && (!isset($user->status) || $user->status === 'active');
    }

    public function hasTwoFactorColumns() {
        return $this->user->hasTwoFactorColumns();
    }

    public function saveTwoFactorSecret($userId, $secret) {
        $this->user->saveTwoFactorSecret($userId, $secret);
    }

    public function enableTwoFactor($userId) {
        $this->user->enableTwoFactor($userId);
    }

    public function verifyTwoFactor($userId, $otp) {
        $user = $this->getUserById($userId);
        if (!$user) return false;

        $secret = trim((string) ($user->twofa_secret ?? ''));
        if ($secret === '') return false;

        $ga = new GoogleAuthenticator();
        return $ga->verifyCode($secret, $otp, 1);
    }

    public function getOrCreateTwoFactorSecret($userId) {
        $user = $this->getUserById($userId);
        $secret = trim((string) ($user->twofa_secret ?? ''));
        $needsSetup = ((int) ($user->twofa_enabled ?? 0) !== 1) || $secret === '';

        if ($needsSetup && $secret === '') {
            $ga = new GoogleAuthenticator();
            $secret = $ga->createSecret();
            $this->saveTwoFactorSecret($userId, $secret);
        }

        return ['secret' => $secret, 'needs_setup' => $needsSetup];
    }

    public function generateRecoveryCodeString() {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= $chars[random_int(0, 30)];
            if ($i === 3 || $i === 7) $code .= '-';
        }
        return $code;
    }

    public function generateRecoveryCodes($userId, $count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $plain = $this->generateRecoveryCodeString();
            $hash = password_hash($plain, PASSWORD_BCRYPT);
            $this->user->insertRecoveryCode($userId, $hash);
            $codes[] = $plain;
        }
        return $codes;
    }

    public function verifyRecoveryCode($userId, $inputCode) {
        $codes = $this->user->getRecoveryCodes($userId);
        foreach ($codes as $rc) {
            if (password_verify($inputCode, $rc->code_hash)) {
                $this->user->markRecoveryCodeUsed($rc->id);
                return true;
            }
        }
        return false;
    }

    public function deleteUnusedRecoveryCodes($userId) {
        $this->user->deleteUnusedRecoveryCodes($userId);
    }

    public function countRecoveryCodes($userId) {
        return $this->user->countRecoveryCodes($userId);
    }

    public function isUserAdmin($username) {
        return $this->user->isAdmin($username);
    }

    public function generateRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAtTs = time() + TWOFA_REMEMBER_SECONDS;
        $expiresAtDb = (new DateTime('now', new DateTimeZone('UTC')))->setTimestamp($expiresAtTs)->format('Y-m-d H:i:s');
        $this->user->setRememberToken($userId, $tokenHash, $expiresAtDb);
        return ['token' => $token, 'expires_at' => $expiresAtTs];
    }

    public function setRememberToken($userId, $tokenHash, $expiresAt) {
        $this->user->setRememberToken($userId, $tokenHash, $expiresAt);
    }

    public function clearRememberToken($userId) {
        $this->user->clearRememberToken($userId);
    }

    public function getRememberTokenData($userId) {
        return $this->user->findById($userId);
    }

    public function hasValidRememberDevice($user, $cookieValue) {
        if (empty($cookieValue)) return false;
        $parts = explode(':', $cookieValue, 2);
        if (count($parts) !== 2) return false;

        $cookieUserId = (int) $parts[0];
        $cookieToken = $parts[1];

        if ($cookieUserId !== (int) $user->id || $cookieToken === '') return false;

        $storedHash = (string) ($user->twofa_remember_token_hash ?? '');
        $storedExpiry = (string) ($user->twofa_remember_expires_at ?? '');
        if ($storedHash === '' || $storedExpiry === '') return false;
        if (strtotime($storedExpiry . ' UTC') < time()) return false;

        return hash_equals($storedHash, hash('sha256', $cookieToken));
    }

    public function completeLoginSession($user) {
        session_regenerate_id(false);
        unset($_SESSION['warning'], $_SESSION['error'], $_SESSION['info'], $_SESSION['success']);

        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['username'] = (string) $user->username;
        $_SESSION['email'] = (string) $user->email;
        $role = $user->role ?? 'user';
        $_SESSION['is_admin'] = ($role === 'admin' || $role === 'owner');
        $_SESSION['is_owner'] = ($role === 'owner');
        $_SESSION['role'] = $role;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        unset($_SESSION['pending_2fa']);

        if (!empty($user->current_store_id)) {
            $_SESSION['ml_active_store_id'] = (int) $user->current_store_id;
        }

        $this->user->clearSessionInvalidation((int) $user->id);
        $this->user->updateLastLogin((int) $user->id);

        // Enviar notificación si IP o dispositivo son nuevos
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $uaParsed = MailService::parseUserAgent($ua);

        $isNewIp  = $ip && $ip !== ($user->last_ip ?? '');
        $isNewUa  = $uaParsed !== 'Desconocido' && $uaParsed !== ($user->last_user_agent ?? '');

        if ($isNewIp || $isNewUa) {
            try {
                $mail = new MailService();
                $mail->send(
                    $user->email,
                    'Nuevo inicio de sesión detectado - ' . SITENAME,
                    'login-notification',
                    [
                        'user_id'      => (int) $user->id,
                        'username'     => $user->username,
                        'first_name'   => $user->first_name ?? '',
                        'login_date'   => date('d/m/Y'),
                        'login_time'   => date('H:i'),
                        'login_ip'     => $ip,
                        'login_device' => $uaParsed,
                        'login_location' => 'Desconocida',
                        'protect_url'   => URLROOT . '/auth/resetpassword?token=',
                    ]
                );
            } catch (Exception $e) {
                error_log('[LoginNotification] Error al enviar: ' . $e->getMessage());
            }
        }

        $this->user->updateLoginMeta((int) $user->id, $ip, $ua);
    }

    public function logAttempt($username, $ipAddress) {
        $this->user->logAttempt($username, $ipAddress);
    }

    public function clearAttempts($username) {
        $this->user->clearAttempts($username);
    }

    public function purgeExpiredAttempts() {
        $this->user->purgeExpiredAttempts(AUTH_LOCKOUT_MINUTES);
    }

    public function countAttempts($username, $ipAddress) {
        $usernameAttempts = $this->user->countAttemptsByUsername($username, AUTH_LOCKOUT_MINUTES);
        $ipAttempts = $this->user->countAttemptsByIp($ipAddress, AUTH_LOCKOUT_MINUTES);
        return max($usernameAttempts, $ipAttempts);
    }

    public function isLoginBlocked($username, $ipAddress) {
        return $this->countAttempts($username, $ipAddress) >= AUTH_MAX_ATTEMPTS;
    }

    public function updateActivity($userId) {
        $this->user->updateActivity($userId);
    }

    public function verifyPasswordById($userId, $password) {
        return $this->user->verifyPassword($userId, $password);
    }

    public function updatePassword($userId, $hashed) {
        $this->user->updatePassword($userId, $hashed);
    }

    public function validatePassword($password) {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una mayúscula.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una minúscula.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un símbolo.';
        }
        return $errors;
    }

    public function countUsers() {
        return $this->user->countUsers();
    }

    public function getLatestUsers($limit = 5) {
        return $this->user->getLatestUsers($limit);
    }

    public function getProfileData($userId) {
        return $this->user->getProfileData($userId);
    }

    // ── Password reset ──

    public function findByEmail($email) {
        return $this->user->findByEmail($email);
    }

    public function requestPasswordReset($email) {
        $row = $this->user->findByEmail($email);
        if (!$row) return null;

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))->modify('+1 hour')->format('Y-m-d H:i:s');

        $this->user->insertResetToken((int) $row->id, $token, $expiresAt);

        return [
            'token'      => $token,
            'username'   => $row->username,
            'first_name' => $row->first_name ?? '',
            'email'      => $row->email,
        ];
    }

    public function validateResetToken($token) {
        return $this->user->findResetToken($token);
    }

    public function completePasswordReset($tokenId, $userId, $newPassword) {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->user->markResetTokenUsed((int) $tokenId);
        $this->user->updatePassword((int) $userId, $hashed);
        $this->user->clearRememberToken((int) $userId);
        $this->user->deactivateSession((int) $userId);
    }
}
