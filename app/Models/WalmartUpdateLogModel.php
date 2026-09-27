<?php

class WalmartUpdateLogModel extends Model {

    public function insert($userId, $connectionId, $storeName, $batchId, $updateType, $sku, $oldValue, $newValue, $diffAmount, $diffPercent, $status, $message) {
        return $this->execute(
            'INSERT INTO walmart_update_logs (user_id, walmart_connection_id, store_name, batch_id, update_type, sku, old_value, new_value, diff_amount, diff_percent, status, message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $connectionId, $storeName, $batchId, $updateType, $sku, $oldValue, $newValue, $diffAmount, $diffPercent, $status, $message]
        );
    }

    /**
     * Batches agrupados y paginados para el historial.
     * Retorna ['batches' => [...], 'total_batches' => int].
     */
    public function findBatches($userId, $isAdmin, $page = 1, $perPage = 10, $filterType = '') {
        $where = $isAdmin ? '1=1' : 'l.user_id = ?';
        $params = $isAdmin ? [] : [$userId];

        if ($filterType === 'price' || $filterType === 'stock') {
            $where .= ' AND l.update_type = ?';
            $params[] = $filterType;
        }

        $totalBatches = (int) $this->value(
            "SELECT COUNT(DISTINCT l.batch_id) FROM walmart_update_logs l WHERE $where",
            $params
        );

        $perPage = max(1, (int) $perPage);
        $totalPages = max(1, (int) ceil($totalBatches / $perPage));
        $page = max(1, min((int) $page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $batches = $this->rows(
            "SELECT l.batch_id,
                    MIN(l.update_type) as update_type,
                    MIN(l.store_name) as store_name,
                    MIN(l.created_at) as started_at,
                    MAX(l.created_at) as finished_at,
                    COUNT(*) as total,
                    SUM(CASE WHEN l.status = 'success' THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN l.status != 'success' THEN 1 ELSE 0 END) as error_count,
                    MIN(u.username) as username
             FROM walmart_update_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE $where
             GROUP BY l.batch_id
             ORDER BY started_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return [
            'batches' => $batches,
            'total_batches' => $totalBatches,
            'page' => $page,
            'total_pages' => $totalPages,
        ];
    }

    public function findByBatch($batchId, $userId, $isAdmin) {
        $where = 'batch_id = ?';
        $params = [$batchId];
        if (!$isAdmin) {
            $where .= ' AND user_id = ?';
            $params[] = $userId;
        }
        return $this->rows(
            "SELECT * FROM walmart_update_logs WHERE $where ORDER BY id ASC",
            $params
        );
    }

    public function purgeOlderThan($days) {
        return $this->execute(
            'DELETE FROM walmart_update_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [(int) $days]
        );
    }
}
