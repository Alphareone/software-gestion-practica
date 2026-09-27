<?php

class PriceLogModel extends Model {

    public function insert($userId, $mlConnectionId, $storeName, $batchId, $sku, $mlItemId, $itemStatus, $oldPrice, $newPrice, $diffAmount, $diffPercent, $status, $message) {
        return $this->execute(
            'INSERT INTO price_change_logs (user_id, ml_connection_id, store_name, batch_id, sku, ml_item_id, item_status, old_price, new_price, diff_amount, diff_percent, status, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $mlConnectionId, $storeName, $batchId, $sku, $mlItemId, $itemStatus, $oldPrice, $newPrice, $diffAmount, $diffPercent, $status, $message]
        );
    }

    public function findBatches($where, $params, $page, $perPage) {
        $offset = ($page - 1) * $perPage;

        $totalBatches = (int) $this->value(
            "SELECT COUNT(DISTINCT l.batch_id) as cnt FROM price_change_logs l WHERE $where",
            $params
        );

        $perPageInt = (int) $perPage;
        $offsetInt = (int) $offset;
        $batchStmt = $this->query(
            "SELECT l.batch_id, MIN(l.created_at) as first_ts
             FROM price_change_logs l WHERE $where
             GROUP BY l.batch_id
             ORDER BY first_ts DESC
             LIMIT $perPageInt OFFSET $offsetInt"
        );
        $bParams = $params;
        $bParamIdx = 1;
        foreach ($bParams as $p) {
            $batchStmt->bindValue($bParamIdx++, $p);
        }
        $batchStmt->execute();
        $batchRows = $batchStmt->fetchAll();
        $batchIds = array_map(function($r) { return $r->batch_id; }, $batchRows);

        return ['total_batches' => $totalBatches, 'batch_ids' => $batchIds];
    }

    public function findByBatchIds($batchIds) {
        if (empty($batchIds)) return [];
        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        return $this->rows(
            "SELECT l.*, u.username, l.store_name
             FROM price_change_logs l
             LEFT JOIN users u ON l.user_id = u.id
             WHERE l.batch_id IN ($placeholders)
             ORDER BY l.created_at ASC",
            $batchIds
        );
    }

    public function findBatchStats($where, $params) {
        $row = $this->row(
            "SELECT COUNT(DISTINCT l.batch_id) as total_batches,
                    COUNT(*) as total_items,
                    SUM(CASE WHEN l.status = 'success' THEN 1 ELSE 0 END) as total_ok,
                    SUM(CASE WHEN l.status = 'error' THEN 1 ELSE 0 END) as total_err,
                    MIN(l.created_at) as first_date,
                    MAX(l.created_at) as last_date
             FROM price_change_logs l WHERE $where",
            $params
        );
        return [
            'total_batches' => (int) ($row->total_batches ?? 0),
            'total_items' => (int) ($row->total_items ?? 0),
            'total_ok' => (int) ($row->total_ok ?? 0),
            'total_err' => (int) ($row->total_err ?? 0),
            'first_date' => $row->first_date ?? null,
            'last_date' => $row->last_date ?? null,
        ];
    }

    public function findByBatch($batchId, $userId, $isAdmin) {
        $where = $isAdmin ? 'batch_id = ?' : 'batch_id = ? AND l.user_id = ?';
        $params = $isAdmin ? [$batchId] : [$batchId, $userId];
        return $this->rows(
            "SELECT l.*, u.username
             FROM price_change_logs l
             LEFT JOIN users u ON l.user_id = u.id
             WHERE $where ORDER BY l.created_at ASC",
            $params
        );
    }

    public function getLastBatchId($userId) {
        return $this->value(
            'SELECT MAX(batch_id) FROM price_change_logs WHERE user_id = ?',
            [$userId]
        );
    }

    public function getSummaryStats($where, $params) {
        $row = $this->row(
            "SELECT COUNT(DISTINCT l.batch_id) AS total_batches,
                    COUNT(*) AS total_items,
                    SUM(CASE WHEN l.status = 'success' THEN 1 ELSE 0 END) AS total_ok,
                    MAX(l.created_at) AS last_update
             FROM price_change_logs l WHERE $where",
            $params
        );
        return [
            'total_items' => (int) ($row->total_items ?? 0),
            'total_ok' => (int) ($row->total_ok ?? 0),
            'total_batches' => (int) ($row->total_batches ?? 0),
            'last_update' => $row->last_update ?? null,
        ];
    }

    public function countExecutionsMonth($where, $params) {
        return (int) $this->value(
            "SELECT COUNT(DISTINCT l.batch_id) AS cnt
             FROM price_change_logs l
             WHERE $where AND l.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            $params
        );
    }

    public function getRecentBatches($where, $params) {
        $rows = $this->rows(
            "SELECT l.batch_id,
                    MIN(l.created_at) AS started_at,
                    COUNT(*) AS total,
                    SUM(CASE WHEN l.status = 'success' THEN 1 ELSE 0 END) AS success_count,
                    SUM(CASE WHEN l.status != 'success' THEN 1 ELSE 0 END) AS error_count
             FROM price_change_logs l WHERE $where
             GROUP BY l.batch_id
             ORDER BY started_at DESC
             LIMIT 5",
            $params
        );
        $recent = [];
        foreach ($rows as $row) {
            $total = (int) $row->total;
            $ok = (int) $row->success_count;
            $err = (int) $row->error_count;
            $pct = $total > 0 ? round(($ok / $total) * 100) : 0;
            $bid = (string) $row->batch_id;
            $recent[] = [
                'batch_id' => $bid,
                'batch_id_short' => strlen($bid) > 14 ? substr($bid, 0, 14) . '…' : $bid,
                'started_at' => $row->started_at,
                'total' => $total,
                'success_count' => $ok,
                'error_count' => $err,
                'success_rate' => $pct,
                'result_label' => $err === 0 ? 'Completado' : $ok . '/' . $total . ' · ' . $pct . '%',
            ];
        }
        return $recent;
    }

    public function purgeOld($retentionMonths) {
        return $this->execute(
            'DELETE FROM price_change_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MONTH)',
            [$retentionMonths]
        );
    }
}
