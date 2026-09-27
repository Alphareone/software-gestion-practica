<?php

class WalmartProductsCacheModel extends Model {

    /** Trae un producto puntual por SKU exacto, para la ficha de detalle. */
    public function getProduct($connectionId, $sku) {
        return $this->row(
            'SELECT id, sku, wpid, gtin, upc, title, product_type, price, currency, available_quantity,
                    published_status, lifecycle_status, extra_data, synced_at
             FROM walmart_products_cache
             WHERE walmart_connection_id = ? AND sku = ?',
            [$connectionId, $sku]
        );
    }

    /** Otras publicaciones con el mismo título exacto (mismo producto, otra talla/color) — para la ficha de detalle. */
    public function getSiblingsByTitle($connectionId, $title, $excludeSku) {
        return $this->rows(
            'SELECT sku, wpid, price, available_quantity, published_status, synced_at
             FROM walmart_products_cache
             WHERE walmart_connection_id = ? AND title = ? AND sku != ?
             ORDER BY sku ASC',
            [$connectionId, $title, $excludeSku]
        );
    }

    public function upsert($connectionId, $sku, $data) {
        $existing = $this->row(
            'SELECT id FROM walmart_products_cache WHERE walmart_connection_id = ? AND sku = ?',
            [$connectionId, $sku]
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
            $params[] = $sku;
            $this->execute(
                'UPDATE walmart_products_cache SET ' . implode(', ', $sets) . ' WHERE walmart_connection_id = ? AND sku = ?',
                $params
            );
            return $existing->id;
        }

        $cols = array_merge(['walmart_connection_id', 'sku'], array_keys($data));
        $vals = array_merge([$connectionId, $sku], array_values($data));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colStr = implode(', ', array_map(function ($c) { return "`$c`"; }, $cols));

        return $this->insertGetId(
            "INSERT INTO walmart_products_cache ($colStr) VALUES ($placeholders)",
            $vals
        );
    }

    /** Upsert desde un item de GET /v3/items (Items API de Walmart). */
    public function upsertFromWalmartItem($connectionId, $item) {
        $sku = trim((string) ($item->sku ?? ''));
        if ($sku === '') return null;

        $extra = [];
        foreach (['mart', 'shelf', 'variantGroupId', 'variantGroupInfo', 'unpublishedReasons'] as $field) {
            if (!empty($item->$field)) {
                $extra[$field] = $item->$field;
            }
        }

        $data = [
            'wpid' => $item->wpid ?? null,
            'gtin' => $item->gtin ?? null,
            'upc' => $item->upc ?? null,
            'title' => $item->productName ?? '',
            'product_type' => $item->productType ?? null,
            'price' => (float) ($item->price->amount ?? 0),
            'currency' => $item->price->currency ?? 'CLP',
            'published_status' => $item->publishedStatus ?? null,
            'lifecycle_status' => $item->lifecycleStatus ?? null,
            'extra_data' => !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
        ];

        return $this->upsert($connectionId, $sku, $data);
    }

    public function findBySku($connectionId, $sku) {
        return $this->row(
            'SELECT * FROM walmart_products_cache WHERE walmart_connection_id = ? AND sku = ?',
            [$connectionId, $sku]
        );
    }

    public function updateLocalPrice($connectionId, $sku, $price) {
        $this->execute(
            'UPDATE walmart_products_cache SET price = ? WHERE walmart_connection_id = ? AND sku = ?',
            [$price, $connectionId, $sku]
        );
    }

    public function updateLocalStock($connectionId, $sku, $quantity) {
        $this->execute(
            'UPDATE walmart_products_cache SET available_quantity = ? WHERE walmart_connection_id = ? AND sku = ?',
            [$quantity, $connectionId, $sku]
        );
    }

    public function getMetrics($connectionId) {
        $row = $this->row(
            'SELECT
                COUNT(*) as total,
                SUM(CASE WHEN published_status = \'PUBLISHED\' THEN 1 ELSE 0 END) as published,
                SUM(COALESCE(available_quantity, 0)) as total_stock,
                SUM(CASE WHEN available_quantity IS NOT NULL AND available_quantity <= 0 THEN 1 ELSE 0 END) as stock_0,
                SUM(CASE WHEN available_quantity BETWEEN 1 AND 2 THEN 1 ELSE 0 END) as stock_1_2,
                SUM(CASE WHEN available_quantity BETWEEN 3 AND 5 THEN 1 ELSE 0 END) as stock_3_5,
                SUM(CASE WHEN available_quantity BETWEEN 6 AND 10 THEN 1 ELSE 0 END) as stock_6_10,
                SUM(CASE WHEN available_quantity BETWEEN 11 AND 20 THEN 1 ELSE 0 END) as stock_11_20,
                SUM(CASE WHEN available_quantity BETWEEN 21 AND 50 THEN 1 ELSE 0 END) as stock_21_50,
                SUM(CASE WHEN available_quantity > 50 THEN 1 ELSE 0 END) as stock_51
            FROM walmart_products_cache WHERE walmart_connection_id = ?',
            [$connectionId]
        );

        if (!$row) {
            return [
                'total' => 0, 'published' => 0, 'low_stock' => 0, 'total_stock' => 0,
                'stock_ranges' => ['empty' => 0, 'low' => 0, 'medium' => 0, 'mid' => 0, 'high' => 0, 'vhigh' => 0, 'max' => 0],
            ];
        }

        $lowStock = (int) ($row->stock_0 ?? 0) + (int) ($row->stock_1_2 ?? 0) + (int) ($row->stock_3_5 ?? 0);

        return [
            'total' => (int) ($row->total ?? 0),
            'published' => (int) ($row->published ?? 0),
            'low_stock' => $lowStock,
            'total_stock' => (int) ($row->total_stock ?? 0),
            'stock_ranges' => [
                'empty' => (int) ($row->stock_0 ?? 0),
                'low' => (int) ($row->stock_1_2 ?? 0),
                'medium' => (int) ($row->stock_3_5 ?? 0),
                'mid' => (int) ($row->stock_6_10 ?? 0),
                'high' => (int) ($row->stock_11_20 ?? 0),
                'vhigh' => (int) ($row->stock_21_50 ?? 0),
                'max' => (int) ($row->stock_51 ?? 0),
            ],
        ];
    }

    public function getProducts($connectionId, $page = 1, $limit = 20, $search = '', $status = '') {
        $params = [$connectionId];
        $where = 'WHERE walmart_connection_id = ?';

        if ($search) {
            $where .= ' AND (title LIKE ? OR sku LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        if ($status !== '' && $status !== 'all') {
            $where .= ' AND published_status = ?';
            $params[] = $status;
        }

        $total = (int) $this->value("SELECT COUNT(*) FROM walmart_products_cache $where", $params);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $limit;

        $items = $this->rows(
            "SELECT id, sku, wpid, gtin, upc, title, product_type, price, currency,
                    available_quantity, published_status, lifecycle_status, extra_data, synced_at
             FROM walmart_products_cache $where
             ORDER BY title ASC
             LIMIT $limit OFFSET $offset",
            $params
        );

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'sku' => $item->sku,
                'wpid' => $item->wpid,
                'gtin' => $item->gtin,
                'upc' => $item->upc,
                'title' => $item->title,
                'product_type' => $item->product_type,
                'price' => (float) $item->price,
                'currency' => $item->currency,
                'available_quantity' => $item->available_quantity !== null ? (int) $item->available_quantity : null,
                'published_status' => $item->published_status,
                'lifecycle_status' => $item->lifecycle_status,
                'synced_at' => $item->synced_at,
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
            ],
        ];
    }

    /** Lista de SKUs en orden estable (por id), para el fallback de inventario por lotes. */
    public function getSkusPage($connectionId, $offset, $limit) {
        $offset = (int) $offset;
        $limit = (int) $limit;
        $rows = $this->rows(
            "SELECT sku FROM walmart_products_cache WHERE walmart_connection_id = ? ORDER BY id ASC LIMIT $limit OFFSET $offset",
            [$connectionId]
        );
        return array_map(function ($r) { return $r->sku; }, $rows);
    }

    public function getLowStock($connectionId, $threshold = 5) {
        return $this->rows(
            'SELECT sku, title, available_quantity FROM walmart_products_cache
             WHERE walmart_connection_id = ? AND available_quantity IS NOT NULL AND available_quantity <= ?
             ORDER BY available_quantity ASC',
            [$connectionId, $threshold]
        );
    }

    /** Trae todos los productos cacheados de una tienda (para armar el mapa SKU→dato de auditorías). */
    public function getAllForAudit($connectionId) {
        return $this->rows(
            'SELECT sku, wpid, price, available_quantity, published_status, synced_at
             FROM walmart_products_cache
             WHERE walmart_connection_id = ? AND sku IS NOT NULL AND sku != \'\'',
            [$connectionId]
        );
    }

    /** Busca coincidencia aproximada por título/SKU (fallback cuando el SKU exacto no está en caché). */
    public function findFuzzy($connectionId, $needle) {
        return $this->row(
            'SELECT sku, wpid, price, available_quantity, published_status, synced_at
             FROM walmart_products_cache
             WHERE walmart_connection_id = ? AND (title LIKE ? OR sku LIKE ?)
             LIMIT 1',
            [$connectionId, '%' . $needle . '%', '%' . $needle . '%']
        );
    }

    public function getCount($connectionId) {
        return (int) $this->value(
            'SELECT COUNT(*) FROM walmart_products_cache WHERE walmart_connection_id = ?',
            [$connectionId]
        );
    }

    public function purgeStore($connectionId) {
        $this->execute('DELETE FROM walmart_products_cache WHERE walmart_connection_id = ?', [$connectionId]);
    }
}
