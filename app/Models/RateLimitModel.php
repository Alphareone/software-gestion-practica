<?php

class RateLimitModel extends Model {

    public function cleanupExpired() {
        return $this->execute(
            'DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 120 MINUTE)'
        );
    }

    public function findActive($action, $userId, $ipAddress, $windowMinutes) {
        return $this->row(
            'SELECT id, attempts, blocked_until, created_at 
             FROM rate_limits 
             WHERE action = ? AND (user_id = ? OR ip_address = ?)
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY id DESC LIMIT 1',
            [$action, $userId, $ipAddress, $windowMinutes]
        );
    }

    public function createAttempt($userId, $ipAddress, $action) {
        return $this->execute(
            'INSERT INTO rate_limits (user_id, ip_address, action, attempts, created_at, updated_at) 
             VALUES (?, ?, ?, 1, NOW(), NOW())',
            [$userId, $ipAddress, $action]
        );
    }

    public function incrementAttempts($id, $blockedUntil = null) {
        if ($blockedUntil) {
            return $this->execute(
                'UPDATE rate_limits SET attempts = attempts + 1, blocked_until = ?, updated_at = NOW() WHERE id = ?',
                [$blockedUntil, $id]
            );
        }
        return $this->execute(
            'UPDATE rate_limits SET attempts = attempts + 1, updated_at = NOW() WHERE id = ?',
            [$id]
        );
    }

    public function deleteByAction($action, $userId, $ipAddress) {
        return $this->execute(
            'DELETE FROM rate_limits WHERE action = ? AND (user_id = ? OR ip_address = ?)',
            [$action, $userId, $ipAddress]
        );
    }

    public function deleteByUser($userId) {
        return $this->execute('DELETE FROM rate_limits WHERE user_id = ?', [$userId]);
    }

    public function sumAttempts($action, $userId, $ipAddress, $windowMinutes) {
        return (int) $this->value(
            'SELECT SUM(attempts) as total 
             FROM rate_limits 
             WHERE action = ? AND (user_id = ? OR ip_address = ?)
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$action, $userId, $ipAddress, $windowMinutes]
        );
    }

    public function findBlocked($limit = 50) {
        $limitInt = (int) $limit;
        return $this->rows(
            "SELECT rl.user_id, rl.ip_address, rl.action, rl.attempts, rl.blocked_until, rl.created_at,
                    u.username, u.email
             FROM rate_limits rl
             LEFT JOIN users u ON rl.user_id = u.id
             WHERE rl.blocked_until IS NOT NULL AND rl.blocked_until > NOW()
             ORDER BY rl.blocked_until DESC
             LIMIT $limitInt",
            []
        );
    }

    public function findRecent($action = null, $limit = 100) {
        $sql = 'SELECT rl.*, u.username, u.email
                FROM rate_limits rl
                LEFT JOIN users u ON rl.user_id = u.id
                WHERE rl.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)';
        $params = [];

        if ($action) {
            $sql .= ' AND rl.action = ?';
            $params[] = $action;
        }

        $limitInt = (int) $limit;
        $sql .= " ORDER BY rl.created_at DESC LIMIT $limitInt";
        return $this->rows($sql, $params);
    }

    public function findBlockedUntil($id) {
        return $this->value('SELECT blocked_until FROM rate_limits WHERE id = ?', [$id]);
    }
}
