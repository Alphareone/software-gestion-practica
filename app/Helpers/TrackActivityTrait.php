<?php

trait TrackActivityTrait {
    protected function trackActivity() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $now = time();
        $lastDbUpdate = $_SESSION['last_activity_db_update'] ?? 0;

        if (($now - $lastDbUpdate) < 300) {
            return;
        }

        try {
            $db = @new Database();
            if ($db) {
                $stmt = $db->query('UPDATE users SET last_activity_at = NOW() WHERE id = :id');
                $stmt->bindValue(':id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->execute();
            }

            $_SESSION['last_activity_db_update'] = $now;
        } catch (Exception $e) {
            error_log('Activity tracking failed: ' . $e->getMessage());
        }
    }
}
