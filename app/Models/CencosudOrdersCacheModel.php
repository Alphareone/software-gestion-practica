<?php

class CencosudOrdersCacheModel extends Model {

    public function upsert($connectionId, $orderId, $data) {
        $existing = $this->row(
            'SELECT id FROM cencosud_orders_cache WHERE cencosud_connection_id = ? AND order_id = ?',
            [$connectionId, $orderId]
        );

        if ($existing) {
            $sets = [];
            $params = [];
            foreach ($data as $col => $val) {
                $sets[] = "`$col` = ?";
                $params[] = $val;
            }
            $sets[] = '`synced_at` = NOW()';
            $params[] = $connectionId;
            $params[] = $orderId;
            $this->execute(
                'UPDATE cencosud_orders_cache SET ' . implode(', ', $sets) . ' WHERE cencosud_connection_id = ? AND order_id = ?',
                $params
            );
            return $existing->id;
        }

        $cols = array_merge(['cencosud_connection_id', 'order_id'], array_keys($data));
        $cols[] = 'synced_at';
        $vals = array_merge([$connectionId, $orderId], array_values($data));
        $vals[] = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colStr = implode(', ', array_map(function($c) { return "`$c`"; }, $cols));

        return $this->insertGetId(
            "INSERT INTO cencosud_orders_cache ($colStr) VALUES ($placeholders)",
            $vals
        );
    }

    public function getOrders($connectionId, $page = 1, $limit = 20, $search = '') {
        $params = [$connectionId];
        $where = 'WHERE cencosud_connection_id = ?';

        if ($search) {
            $where .= ' AND (order_id LIKE ? OR customer_name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $countRow = $this->row("SELECT COUNT(*) as cnt FROM cencosud_orders_cache $where", $params);
        $total = (int) ($countRow->cnt ?? 0);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $limit;

        $items = $this->rows(
            "SELECT id, order_id, status, total_amount, customer_name, shipping_status, created_at_cenco, synced_at, raw_data
             FROM cencosud_orders_cache $where
             ORDER BY created_at_cenco DESC
             LIMIT $limit OFFSET $offset",
            $params
        );

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'order_id' => $item->order_id,
                'status' => $item->status,
                'total_amount' => (float) $item->total_amount,
                'customer_name' => $item->customer_name,
                'shipping_status' => $item->shipping_status,
                'created_at_cenco' => $item->created_at_cenco,
                'synced_at' => $item->synced_at,
                'raw_data' => json_decode($item->raw_data)
            ];
        }

        return [
            'items' => $result,
            'paging' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ]
        ];
    }

    public function getOrder($connectionId, $orderId) {
        $row = $this->row(
            "SELECT id, order_id, status, total_amount, customer_name, shipping_status, created_at_cenco, synced_at, raw_data
             FROM cencosud_orders_cache
             WHERE cencosud_connection_id = ? AND order_id = ?",
            [$connectionId, $orderId]
        );
        if ($row) {
            $row->raw_data = json_decode($row->raw_data);
        }
        return $row;
    }

    public function getCount($connectionId) {
        return (int) $this->value(
            'SELECT COUNT(*) FROM cencosud_orders_cache WHERE cencosud_connection_id = ?',
            [$connectionId]
        );
    }

    public function purgeStore($connectionId) {
        $this->execute('DELETE FROM cencosud_orders_cache WHERE cencosud_connection_id = ?', [$connectionId]);
    }
}
