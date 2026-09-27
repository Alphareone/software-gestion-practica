<?php

class CencosudSyncStatusModel extends Model {

    public function get($connectionId) {
        $row = $this->row(
            'SELECT * FROM cencosud_sync_status WHERE cencosud_connection_id = ?',
            [$connectionId]
        );
        if (!$row) {
            $this->execute(
                'INSERT INTO cencosud_sync_status (cencosud_connection_id, status) VALUES (?, ?)',
                [$connectionId, 'idle']
            );
            return $this->get($connectionId);
        }
        return $row;
    }

    public function getAll() {
        return $this->rows(
            'SELECT s.*, c.store_name
             FROM cencosud_sync_status s
             INNER JOIN cencosud_connections c ON c.id = s.cencosud_connection_id
             WHERE c.is_active = 1'
        );
    }

    public function setSyncing($connectionId, $total, $userId = null, $cursor = null) {
        $this->get($connectionId); // asegura que la fila exista
        $this->execute(
            'UPDATE cencosud_sync_status SET status = ?, total_products = ?, synced_products = 0,
             next_cursor = ?, locked_at = NOW(), locked_by = ?, last_error = NULL
             WHERE cencosud_connection_id = ?',
            ['syncing', $total, $cursor, $userId, $connectionId]
        );
    }

    public function updateProgress($connectionId, $synced, $cursor = null) {
        $this->execute(
            'UPDATE cencosud_sync_status SET synced_products = ?, next_cursor = ? WHERE cencosud_connection_id = ?',
            [$synced, $cursor, $connectionId]
        );
    }

    public function setIdle($connectionId, $totalProducts) {
        $this->execute(
            'UPDATE cencosud_sync_status SET status = ?, total_products = ?, synced_products = ?,
             next_cursor = NULL, last_sync_at = NOW(), locked_at = NULL, locked_by = NULL, last_error = NULL
             WHERE cencosud_connection_id = ?',
            ['idle', $totalProducts, $totalProducts, $connectionId]
        );
    }

    public function setError($connectionId, $error) {
        $this->execute(
            'UPDATE cencosud_sync_status SET status = ?, last_error = ?, next_cursor = NULL, locked_at = NULL, locked_by = NULL
             WHERE cencosud_connection_id = ?',
            ['error', $error, $connectionId]
        );
    }

    public function acquireLock($connectionId, $userId) {
        $this->get($connectionId); // asegura que la fila exista
        $this->execute(
            'UPDATE cencosud_sync_status SET locked_at = NOW(), locked_by = ?
             WHERE cencosud_connection_id = ? AND (locked_at IS NULL OR TIMESTAMPDIFF(MINUTE, locked_at, NOW()) > 30)',
            [$userId, $connectionId]
        );
        $row = $this->row(
            'SELECT locked_by FROM cencosud_sync_status WHERE cencosud_connection_id = ?',
            [$connectionId]
        );
        return $row && (int) $row->locked_by === (int) $userId;
    }

    public function releaseLock($connectionId) {
        $this->execute(
            'UPDATE cencosud_sync_status SET locked_at = NULL, locked_by = NULL WHERE cencosud_connection_id = ?',
            [$connectionId]
        );
    }

    public function needsSync($connectionId, $maxAgeMinutes = 60) {
        $row = $this->row(
            'SELECT last_sync_at, status, locked_at FROM cencosud_sync_status WHERE cencosud_connection_id = ?',
            [$connectionId]
        );
        if (!$row) return true;
        if ($row->status === 'syncing') {
            if ($row->locked_at && (time() - strtotime($row->locked_at . ' UTC')) > 1800) {
                $this->execute(
                    'UPDATE cencosud_sync_status SET status = \'idle\', locked_at = NULL, locked_by = NULL WHERE cencosud_connection_id = ?',
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
