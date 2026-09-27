<?php

class UserModel extends Model {

    // ── CRUD ──

    public function findByUsername($username) {
        return $this->row('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);
    }

    public function findById($id) {
        return $this->row('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
    }

    // ── Session & activity ──

    public function updateLastLogin($id) {
        return $this->execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public function updateActivity($id) {
        return $this->execute('UPDATE users SET last_activity_at = NOW() WHERE id = ?', [$id]);
    }

    public function updateLoginMeta($id, $ip, $userAgent) {
        return $this->execute('UPDATE users SET last_ip = ?, last_user_agent = ? WHERE id = ?', [$ip, $userAgent, $id]);
    }

    public function invalidateSession($id) {
        return $this->execute('UPDATE users SET session_invalidated_at = NOW() WHERE id = ?', [$id]);
    }

    public function clearSessionInvalidation($id) {
        return $this->execute('UPDATE users SET session_invalidated_at = NULL WHERE id = ?', [$id]);
    }

    public function getSessionInvalidation($id) {
        $row = $this->row('SELECT session_invalidated_at FROM users WHERE id = ?', [$id]);
        return $row ? $row->session_invalidated_at : null;
    }

    // ── Password ──

    public function verifyPassword($id, $password) {
        $row = $this->row('SELECT password FROM users WHERE id = ?', [$id]);
        if (!$row) return false;
        return password_verify($password, $row->password);
    }

    public function updatePassword($id, $hashed) {
        return $this->execute('UPDATE users SET password = ? WHERE id = ?', [$hashed, $id]);
    }

    // ── Two-factor ──

    public function hasTwoFactorColumns() {
        $required = [
            'twofa_enabled', 'twofa_secret', 'twofa_confirmed_at',
            'twofa_remember_token_hash', 'twofa_remember_expires_at'
        ];
        foreach ($required as $column) {
            $stmt = $this->query('SHOW COLUMNS FROM users LIKE ?');
            $stmt->execute([$column]);
            if (!$stmt->fetch()) return false;
        }
        return true;
    }

    public function saveTwoFactorSecret($id, $secret) {
        return $this->execute(
            'UPDATE users SET twofa_secret = ?, twofa_enabled = 0, twofa_confirmed_at = NULL WHERE id = ?',
            [$secret, $id]
        );
    }

    public function enableTwoFactor($id) {
        return $this->execute(
            'UPDATE users SET twofa_enabled = 1, twofa_confirmed_at = NOW(), twofa_remember_token_hash = NULL, twofa_remember_expires_at = NULL WHERE id = ?',
            [$id]
        );
    }

    public function setRememberToken($id, $hash, $expiresAt) {
        return $this->execute(
            'UPDATE users SET twofa_remember_token_hash = ?, twofa_remember_expires_at = ? WHERE id = ?',
            [$hash, $expiresAt, $id]
        );
    }

    public function clearRememberToken($id) {
        return $this->execute(
            'UPDATE users SET twofa_remember_token_hash = NULL, twofa_remember_expires_at = NULL WHERE id = ?',
            [$id]
        );
    }

    public function resetTwoFactor($userId) {
        $this->execute(
            'UPDATE users SET twofa_secret = NULL, twofa_enabled = 0, twofa_confirmed_at = NULL, twofa_remember_token_hash = NULL, twofa_remember_expires_at = NULL WHERE id = ?',
            [$userId]
        );
        $this->execute('DELETE FROM recovery_codes WHERE user_id = ?', [$userId]);
    }

    public function isAdmin($username) {
        $row = $this->row('SELECT role FROM users WHERE username = ? LIMIT 1', [trim((string) $username)]);
        return $row && in_array($row->role, ['admin', 'owner']);
    }

    // ── Recovery codes ──

    public function getRecoveryCodes($userId) {
        return $this->rows(
            'SELECT id, code_hash FROM recovery_codes WHERE user_id = ? AND used_at IS NULL ORDER BY created_at ASC',
            [$userId]
        );
    }

    public function markRecoveryCodeUsed($codeId) {
        return $this->execute('UPDATE recovery_codes SET used_at = NOW() WHERE id = ?', [$codeId]);
    }

    public function insertRecoveryCode($userId, $hash) {
        return $this->insertGetId(
            'INSERT INTO recovery_codes (user_id, code_hash, created_at) VALUES (?, ?, NOW())',
            [$userId, $hash]
        );
    }

    public function deleteUnusedRecoveryCodes($userId) {
        return $this->execute(
            'DELETE FROM recovery_codes WHERE user_id = ? AND used_at IS NULL',
            [$userId]
        );
    }

    public function countRecoveryCodes($userId) {
        return (int) $this->value(
            'SELECT COUNT(*) as cnt FROM recovery_codes WHERE user_id = ? AND used_at IS NULL',
            [$userId]
        );
    }

    // ── Login attempts ──

    public function logAttempt($username, $ipAddress) {
        return $this->execute(
            'INSERT INTO login_attempts (username, ip_address, timestamp) VALUES (?, ?, NOW())',
            [$username, $ipAddress]
        );
    }

    public function clearAttempts($username) {
        return $this->execute('DELETE FROM login_attempts WHERE username = ?', [$username]);
    }

    public function purgeExpiredAttempts($minutes) {
        return $this->execute(
            'DELETE FROM login_attempts WHERE `timestamp` < DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [(int) $minutes]
        );
    }

    public function countAttemptsByUsername($username, $minutes) {
        return (int) $this->value(
            'SELECT COUNT(*) FROM login_attempts WHERE username = ? AND `timestamp` > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$username, (int) $minutes]
        );
    }

    public function countAttemptsByIp($ipAddress, $minutes) {
        return (int) $this->value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND `timestamp` > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$ipAddress, (int) $minutes]
        );
    }

    // ── Stats ──

    public function countUsers() {
        return (int) $this->value('SELECT COUNT(*) as cnt FROM users');
    }

    public function getLatestUsers($limit = 5) {
        $limitInt = (int) $limit;
        return $this->rows(
            "SELECT id, username, email, created_at FROM users ORDER BY id DESC LIMIT $limitInt",
            []
        );
    }

    public function getProfileData($id) {
        return $this->row('SELECT twofa_enabled, role, created_at FROM users WHERE id = ?', [$id]);
    }

    // ── Admin user management ──

    public function search($search = '', $roleFilter = '', $statusFilter = '') {
        $sql = 'SELECT id, username, email, first_name, last_name, twofa_enabled, status, role, (role = \'admin\' OR role = \'owner\') AS is_admin, created_at, last_activity_at FROM users WHERE 1=1';
        $params = [];

        if ($search) {
            $sql .= ' AND (username LIKE ? OR email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $validRoles = ['owner', 'admin', 'logistics', 'sales', 'products', 'user'];
        if ($roleFilter && in_array($roleFilter, $validRoles)) {
            $sql .= ' AND role = ?';
            $params[] = $roleFilter;
        }

        if ($statusFilter) {
            $sql .= ' AND status = ?';
            $params[] = $statusFilter;
        }

        $sql .= ' ORDER BY created_at DESC';
        return $this->rows($sql, $params);
    }

    public function findByUsernameOrEmail($username, $email) {
        return $this->row('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email]);
    }

    public function findByEmailExcept($email, $exceptId) {
        return $this->row('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $exceptId]);
    }

    public function getEditData($id) {
        return $this->row(
            'SELECT id, username, email, first_name, last_name, twofa_enabled, status, role, (role = \'admin\' OR role = \'owner\') AS is_admin, created_at, last_login_at, last_activity_at, last_ip FROM users WHERE id = ?',
            [$id]
        );
    }

    public function getBasicInfo($id) {
        return $this->row('SELECT id, username, status, role, (role = \'admin\' OR role = \'owner\') AS is_admin FROM users WHERE id = ?', [$id]);
    }

    public function createUser($username, $email, $hashedPassword, $role, $firstName = '', $lastName = '') {
        return $this->insertGetId(
            'INSERT INTO users (username, email, password, role, first_name, last_name, twofa_enabled, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, \'active\', NOW(), NOW())',
            [$username, $email, $hashedPassword, $role, $firstName, $lastName]
        );
    }

    public function updateUserWithPassword($id, $email, $role, $status, $hashedPassword, $firstName = '', $lastName = '') {
        return $this->execute(
            'UPDATE users SET email = ?, password = ?, role = ?, status = ?, first_name = ?, last_name = ?, updated_at = NOW() WHERE id = ?',
            [$email, $hashedPassword, $role, $status, $firstName, $lastName, $id]
        );
    }

    public function updateUserWithoutPassword($id, $email, $role, $status, $firstName = '', $lastName = '') {
        return $this->execute(
            'UPDATE users SET email = ?, role = ?, status = ?, first_name = ?, last_name = ?, updated_at = NOW() WHERE id = ?',
            [$email, $role, $status, $firstName, $lastName, $id]
        );
    }

    public function updateStatus($id, $status) {
        return $this->execute('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function deactivateSession($id) {
        return $this->execute(
            'UPDATE users SET twofa_remember_token_hash = NULL, twofa_remember_expires_at = NULL, session_invalidated_at = NOW() WHERE id = ?',
            [$id]
        );
    }

    // ── Password reset ──

    public function findByEmail($email) {
        return $this->row('SELECT id, username, email, first_name, status FROM users WHERE email = ? LIMIT 1', [$email]);
    }

    public function insertResetToken($userId, $token, $expiresAt) {
        return $this->execute(
            'INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$userId, $token, $expiresAt]
        );
    }

    public function findResetToken($token) {
        return $this->row(
            'SELECT id, user_id, token, expires_at FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1',
            [$token]
        );
    }

    public function markResetTokenUsed($id) {
        return $this->execute('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    // ── Delete ──

    public function delete($id) {
        return $this->execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    public function countByRole($role) {
        return (int) $this->value('SELECT COUNT(*) FROM users WHERE role = ?', [$role]);
    }
}
