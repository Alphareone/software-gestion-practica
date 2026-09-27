<?php

class CencosudBatchModel extends Model {

    public function findPending($userId) {
        $row = $this->row(
            "SELECT data FROM cencosud_update_batches WHERE user_id = ? AND status IN ('pending','processing') LIMIT 1",
            [$userId]
        );
        if (!$row) return null;
        $data = json_decode($row->data, true);
        return is_array($data) ? $data : null;
    }

    public function hasPending($userId) {
        return (bool) $this->row(
            "SELECT id FROM cencosud_update_batches WHERE user_id = ? AND status IN ('pending','processing') LIMIT 1",
            [$userId]
        );
    }

    public function save($userId, $batch) {
        $existing = $this->row(
            "SELECT id FROM cencosud_update_batches WHERE user_id = ? AND status IN ('pending','processing')",
            [$userId]
        );
        $data = json_encode($batch, JSON_UNESCAPED_UNICODE);
        $status = $batch['processed'] >= $batch['total'] ? 'done' : 'processing';

        if ($existing) {
            return $this->execute(
                'UPDATE cencosud_update_batches SET data = ?, total = ?, processed = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [$data, $batch['total'], $batch['processed'], $status, $existing->id]
            );
        }
        return $this->execute(
            'INSERT INTO cencosud_update_batches (user_id, cencosud_connection_id, batch_id, update_type, data, total, processed, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $batch['cencosud_connection_id'], $batch['batch_id'], $batch['update_type'], $data, $batch['total'], $batch['processed'], 'pending']
        );
    }

    public function delete($userId) {
        return $this->execute(
            "DELETE FROM cencosud_update_batches WHERE user_id = ? AND status IN ('pending','processing')",
            [$userId]
        );
    }
}
