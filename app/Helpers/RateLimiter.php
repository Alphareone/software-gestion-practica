<?php

class RateLimiter {
    private $rateLimit;

    public function __construct() {
        $this->rateLimit = new RateLimitModel();
    }

    public function attempt($identifier, $action, $userId = null) {
        $this->rateLimit->cleanupExpired();

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $limitConfig = $this->getLimitConfig($action);
        $record = $this->rateLimit->findActive($action, $userId, $ipAddress, $limitConfig['window']);

        if ($record) {
            if ($record->blocked_until && strtotime($record->blocked_until . ' UTC') > time()) {
                return [
                    'allowed' => false,
                    'blocked' => true,
                    'retry_after' => strtotime($record->blocked_until . ' UTC') - time(),
                    'attempts' => $record->attempts,
                ];
            }

            $newAttempts = $record->attempts + 1;
            $blockedUntil = null;

            if ($newAttempts >= $limitConfig['max']) {
                $blockedUntil = (new DateTime('now', new DateTimeZone('UTC')))->setTimestamp(time() + ($limitConfig['window'] * 60))->format('Y-m-d H:i:s');
                $this->rateLimit->incrementAttempts($record->id, $blockedUntil);
            } else {
                $this->rateLimit->incrementAttempts($record->id);
            }

            return [
                'allowed' => $newAttempts < $limitConfig['max'],
                'blocked' => $blockedUntil !== null,
                'retry_after' => $blockedUntil ? ($limitConfig['window'] * 60) : null,
                'attempts' => $newAttempts,
                'remaining' => max(0, $limitConfig['max'] - $newAttempts),
            ];
        }

        $this->rateLimit->createAttempt($userId, $ipAddress, $action);

        return [
            'allowed' => true,
            'blocked' => false,
            'retry_after' => null,
            'attempts' => 1,
            'remaining' => $limitConfig['max'] - 1,
        ];
    }

    public function isBlocked($identifier, $action, $userId = null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $limitConfig = $this->getLimitConfig($action);
        $record = $this->rateLimit->findActive($action, $userId, $ipAddress, $limitConfig['window']);

        if (!$record) {
            return ['blocked' => false, 'retry_after' => null];
        }

        if ($record->blocked_until && strtotime($record->blocked_until . ' UTC') > time()) {
            return [
                'blocked' => true,
                'retry_after' => strtotime($record->blocked_until . ' UTC') - time(),
                'attempts' => $record->attempts,
            ];
        }

        return ['blocked' => false, 'retry_after' => null, 'attempts' => $record->attempts];
    }

    public function reset($identifier, $action, $userId = null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->rateLimit->deleteByAction($action, $userId, $ipAddress);
    }

    public function resetAll($userId = null) {
        if ($userId) {
            $this->rateLimit->deleteByUser($userId);
        }
    }

    public function getAttempts($action, $userId = null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $limitConfig = $this->getLimitConfig($action);
        return $this->rateLimit->sumAttempts($action, $userId, $ipAddress, $limitConfig['window']);
    }

    private function getLimitConfig($action) {
        return match($action) {
            'login', 'login_attempt' => ['max' => RATE_LIMIT_LOGIN_MAX, 'window' => RATE_LIMIT_LOGIN_WINDOW],
            '2fa', 'two_factor' => ['max' => RATE_LIMIT_2FA_MAX, 'window' => RATE_LIMIT_2FA_WINDOW],
            'import_prices', 'import' => ['max' => RATE_LIMIT_IMPORT_MAX, 'window' => RATE_LIMIT_IMPORT_WINDOW],
            'export' => ['max' => RATE_LIMIT_EXPORT_MAX, 'window' => RATE_LIMIT_EXPORT_WINDOW],
            'admin_action', 'admin' => ['max' => RATE_LIMIT_ADMIN_MAX, 'window' => RATE_LIMIT_ADMIN_WINDOW],
            'password_reset' => ['max' => RATE_LIMIT_PASSWORD_RESET_MAX, 'window' => RATE_LIMIT_PASSWORD_RESET_WINDOW],
            default => ['max' => 5, 'window' => 15],
        };
    }

    public function getBlockedUsers($limit = 50) {
        return $this->rateLimit->findBlocked($limit);
    }

    public function getRecentAttempts($action = null, $limit = 100) {
        return $this->rateLimit->findRecent($action, $limit);
    }
}
