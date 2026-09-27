<?php

class MlApiRateLimiter {
    private $rateLimit;
    private const MAX_CALLS_PER_MINUTE = 300;
    private const MAX_CALLS_PER_HOUR = 15000;
    private const ACTION = 'ml_api_call';

    public function __construct($db) {
        $this->rateLimit = new RateLimitModel($db);
    }

    public function checkAndWait() {
        $this->rateLimit->cleanupExpired();
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = $_SESSION['user_id'] ?? null;
        
        $minuteLimit = $this->checkLimit(self::ACTION, $userId, $ipAddress, 1);
        $hourLimit = $this->checkLimit(self::ACTION . '_hour', $userId, $ipAddress, 60);
        
        if ($minuteLimit['exceeded']) {
            return $minuteLimit['wait_seconds'];
        }
        
        if ($hourLimit['exceeded']) {
            return $hourLimit['wait_seconds'];
        }
        
        $this->recordCall();
        return 0;
    }

    private function checkLimit($action, $userId, $ipAddress, $windowMinutes) {
        $maxCalls = $windowMinutes === 1 ? self::MAX_CALLS_PER_MINUTE : self::MAX_CALLS_PER_HOUR;
        
        $record = $this->rateLimit->findActive($action, $userId, $ipAddress, $windowMinutes);
        
        if (!$record) {
            return ['exceeded' => false, 'wait_seconds' => 0];
        }
        
        $elapsed = time() - strtotime($record->created_at . ' UTC');
        $windowSeconds = $windowMinutes * 60;
        
        if ($elapsed >= $windowSeconds) {
            return ['exceeded' => false, 'wait_seconds' => 0];
        }
        
        $expectedCalls = ($elapsed / $windowSeconds) * $maxCalls;
        $actualCalls = $record->attempts;
        
        if ($actualCalls >= $maxCalls) {
            $waitSeconds = ceil($windowSeconds - $elapsed);
            return ['exceeded' => true, 'wait_seconds' => min($waitSeconds, $windowSeconds)];
        }
        
        $remaining = $maxCalls - $actualCalls;
        $safeThreshold = $maxCalls * 0.9;
        
        if ($actualCalls >= $safeThreshold) {
            $waitSeconds = ceil(($windowSeconds / $maxCalls) * 10);
            return ['exceeded' => true, 'wait_seconds' => $waitSeconds];
        }
        
        return ['exceeded' => false, 'wait_seconds' => 0];
    }

    private function recordCall() {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = $_SESSION['user_id'] ?? null;
        
        $minuteRecord = $this->rateLimit->findActive(self::ACTION, $userId, $ipAddress, 1);
        $hourRecord = $this->rateLimit->findActive(self::ACTION . '_hour', $userId, $ipAddress, 60);
        
        if (!$minuteRecord) {
            $this->rateLimit->createAttempt($userId, $ipAddress, self::ACTION);
        } else {
            $this->rateLimit->incrementAttempts($minuteRecord->id);
        }
        
        if (!$hourRecord) {
            $this->rateLimit->createAttempt($userId, $ipAddress, self::ACTION . '_hour');
        } else {
            $this->rateLimit->incrementAttempts($hourRecord->id);
        }
    }

    public function getStats() {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = $_SESSION['user_id'] ?? null;
        
        $minuteRecord = $this->rateLimit->findActive(self::ACTION, $userId, $ipAddress, 1);
        $hourRecord = $this->rateLimit->findActive(self::ACTION . '_hour', $userId, $ipAddress, 60);
        
        return [
            'calls_last_minute' => $minuteRecord ? $minuteRecord->attempts : 0,
            'calls_last_hour' => $hourRecord ? $hourRecord->attempts : 0,
            'limit_per_minute' => self::MAX_CALLS_PER_MINUTE,
            'limit_per_hour' => self::MAX_CALLS_PER_HOUR,
        ];
    }
}
