<?php

class MlSyncStatusModel extends Model {

    public function get($connectionId) {
        $row = $this->row(
            'SELECT * FROM ml_sync_status WHERE ml_connection_id = ?',
            [$connectionId]
        );
        if (!$row) {
            $this->execute(
                'INSERT INTO ml_sync_status (ml_connection_id, status) VALUES (?, ?)',
                [$connectionId, 'idle']
            );
            return $this->get($connectionId);
        }
        return $row;
    }

    public function getAll() {
        return $this->rows(
            'SELECT s.*, c.store_name
             FROM ml_sync_status s
             INNER JOIN ml_connections c ON c.id = s.ml_connection_id
             WHERE c.is_active = 1'
        );
    }

    public function setSyncing($connectionId, $total, $userId = null) {
        $existing = $this->row(
            'SELECT id FROM ml_sync_status WHERE ml_connection_id = ?',
            [$connectionId]
        );
        if ($existing) {
            $this->execute(
                'UPDATE ml_sync_status SET status = ?, total_products = ?, synced_products = 0,
                 locked_at = NOW(), locked_by = ?, last_error = NULL
                 WHERE ml_connection_id = ?',
                ['syncing', $total, $userId, $connectionId]
            );
        } else {
            $this->execute(
                'INSERT INTO ml_sync_status (ml_connection_id, status, total_products, locked_at, locked_by)
                 VALUES (?, ?, ?, NOW(), ?)',
                [$connectionId, 'syncing', $total, $userId]
            );
        }
    }

    public function updateProgress($connectionId, $synced) {
        $this->execute(
            'UPDATE ml_sync_status SET synced_products = ? WHERE ml_connection_id = ?',
            [$synced, $connectionId]
        );
    }

    public function setIdle($connectionId, $totalProducts) {
        $this->execute(
            'UPDATE ml_sync_status SET status = ?, total_products = ?, synced_products = ?,
             last_sync_at = NOW(), locked_at = NULL, locked_by = NULL, last_error = NULL
             WHERE ml_connection_id = ?',
            ['idle', $totalProducts, $totalProducts, $connectionId]
        );
    }

    public function setError($connectionId, $error) {
        $this->execute(
            'UPDATE ml_sync_status SET status = ?, last_error = ?, locked_at = NULL, locked_by = NULL
             WHERE ml_connection_id = ?',
            ['error', $error, $connectionId]
        );
    }

    public function acquireLock($connectionId, $userId) {
        $this->execute(
            'UPDATE ml_sync_status SET locked_at = NOW(), locked_by = ?
             WHERE ml_connection_id = ? AND (locked_at IS NULL OR TIMESTAMPDIFF(MINUTE, locked_at, NOW()) > 30)',
            [$userId, $connectionId]
        );
        $row = $this->row(
            'SELECT locked_by FROM ml_sync_status WHERE ml_connection_id = ?',
            [$connectionId]
        );
        return $row && (int) $row->locked_by === (int) $userId;
    }

    public function releaseLock($connectionId) {
        $this->execute(
            'UPDATE ml_sync_status SET locked_at = NULL, locked_by = NULL
             WHERE ml_connection_id = ?',
            [$connectionId]
        );
    }

    public function needsSync($connectionId, $maxAgeMinutes = 30) {
        $row = $this->row(
            'SELECT last_sync_at, status, locked_at FROM ml_sync_status WHERE ml_connection_id = ?',
            [$connectionId]
        );
        if (!$row) return true;
        if ($row->status === 'syncing') {
            if ($row->locked_at && (time() - strtotime($row->locked_at . ' UTC')) > 1800) {
                $this->execute(
                    'UPDATE ml_sync_status SET status = \'idle\', locked_at = NULL, locked_by = NULL WHERE ml_connection_id = ?',
                    [$connectionId]
                );
                return true;
            }
            return false;
        }
        if ($row->status === 'error') return true;
        if (!$row->last_sync_at) return true;
        return (time() - strtotime($row->last_sync_at . ' UTC')) > ($maxAgeMinutes * 60);
    }
}
