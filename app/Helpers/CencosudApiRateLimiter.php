<?php

/**
 * Rate Limiter para llamadas a la API de Cencosud.
 * Utiliza la tabla de base de datos rate_limits (RateLimitModel)
 * para llevar la cuenta de llamadas por minuto y por hora.
 */
class CencosudApiRateLimiter {
    private $rateLimit;
    private const MAX_CALLS_PER_MINUTE = 120; // Límite de ejemplo: 120 llamadas/min
    private const MAX_CALLS_PER_HOUR = 6000;   // Límite de ejemplo: 6000 llamadas/hora
    private const ACTION = 'cencosud_api_call';

    public function __construct($db) {
        $this->rateLimit = new RateLimitModel($db);
    }

    /**
     * Revisa si se ha excedido el límite. Si es así, calcula y retorna el tiempo
     * que se debe esperar (en segundos). Si no se excede, registra la llamada.
     */
    public function checkAndWait() {
        // Limpiar registros antiguos
        $this->rateLimit->cleanupExpired();

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = $_SESSION['user_id'] ?? null;

        // Validar límite por minuto
        $minuteLimit = $this->checkLimit(self::ACTION, $userId, $ipAddress, 1);
        // Validar límite por hora
        $hourLimit = $this->checkLimit(self::ACTION . '_hour', $userId, $ipAddress, 60);

        if ($minuteLimit['exceeded']) {
            return $minuteLimit['wait_seconds'];
        }
        if ($hourLimit['exceeded']) {
            return $hourLimit['wait_seconds'];
        }

        // Registrar intento de llamada
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

        $actualCalls = $record->attempts;
        if ($actualCalls >= $maxCalls) {
            $waitSeconds = ceil($windowSeconds - $elapsed);
            return ['exceeded' => true, 'wait_seconds' => min($waitSeconds, $windowSeconds)];
        }

        // Si estamos cerca del límite (90%), forzar un retraso pequeño para suavizar el flujo
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
}
