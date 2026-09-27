<?php

class PriceBatchModel extends Model {

    public function findPending($userId) {
        $row = $this->row(
            "SELECT data FROM price_batch_pending WHERE user_id = ? AND status IN ('pending','processing') LIMIT 1",
            [$userId]
        );
        if (!$row) return null;
        $data = json_decode($row->data, true);
        return is_array($data) ? $data : null;
    }

    public function hasPending($userId) {
        return (bool) $this->row(
            "SELECT id FROM price_batch_pending WHERE user_id = ? AND status IN ('pending', 'processing') LIMIT 1",
            [$userId]
        );
    }

    public function save($userId, $batch) {
        $existing = $this->row(
            "SELECT id FROM price_batch_pending WHERE user_id = ? AND status IN ('pending','processing')",
            [$userId]
        );
        $data = json_encode($batch, JSON_UNESCAPED_UNICODE);
        $status = $batch['processed'] >= $batch['total'] ? 'done' : 'processing';

        if ($existing) {
            return $this->execute(
                'UPDATE price_batch_pending SET data = ?, total = ?, processed = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [$data, $batch['total'], $batch['processed'], $status, $existing->id]
            );
        }
        return $this->execute(
            'INSERT INTO price_batch_pending (user_id, ml_connection_id, batch_id, data, total, processed, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $batch['ml_connection_id'], $batch['batch_id'], $data, $batch['total'], $batch['processed'], 'pending']
        );
    }

    public function delete($userId) {
        return $this->execute(
            "DELETE FROM price_batch_pending WHERE user_id = ? AND status IN ('pending','processing')",
            [$userId]
        );
    }
}
