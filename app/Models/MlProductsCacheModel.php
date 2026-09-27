<?php

class MlProductsCacheModel extends Model {

    public function upsert($connectionId, $itemId, $data) {
        $existing = $this->row(
            'SELECT id FROM ml_products_cache WHERE ml_connection_id = ? AND ml_item_id = ?',
            [$connectionId, $itemId]
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
            $params[] = $itemId;
            $this->execute(
                'UPDATE ml_products_cache SET ' . implode(', ', $sets) . ' WHERE ml_connection_id = ? AND ml_item_id = ?',
                $params
            );
            return $existing->id;
        }

        $cols = array_merge(['ml_connection_id', 'ml_item_id'], array_keys($data));
        $cols[] = 'synced_at';
        $vals = array_merge([$connectionId, $itemId], array_values($data));
        $vals[] = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colStr = implode(', ', array_map(function($c) { return "`$c`"; }, $cols));

        return $this->insertGetId(
            "INSERT INTO ml_products_cache ($colStr) VALUES ($placeholders)",
            $vals
        );
    }

    public function upsertFromMlItem($connectionId, $item) {
        $sku = MlApiHelper::extractSku($item);

        // Convert ISO start_time to MySQL datetime
        $startTime = null;
        if (!empty($item->start_time)) {
            try {
                $dt = new DateTime($item->start_time);
                $startTime = $dt->format('Y-m-d H:i:s');
            } catch (\Exception $e) {}
        }

        // Build extra_data JSON
        $extra = [];
        if (!empty($item->pictures[0]->secure_url)) {
            $extra['picture_hd'] = $item->pictures[0]->secure_url;
        }
        if (!empty($item->shipping)) {
            $extra['shipping'] = [
                'free_shipping' => !empty($item->shipping->free_shipping),
                'mode' => $item->shipping->mode ?? null,
            ];
        }
        if (!empty($item->warranty)) $extra['warranty'] = $item->warranty;
        if (!empty($item->buying_mode)) $extra['buying_mode'] = $item->buying_mode;
        if (!empty($item->listing_source)) $extra['listing_source'] = $item->listing_source;
        if (!empty($item->video_id)) $extra['video_id'] = $item->video_id;
        if (!empty($item->catalog_product_id)) $extra['catalog_product_id'] = $item->catalog_product_id;

        // ── sold_quantity diff since last sync ──
        $newSold = (int) ($item->sold_quantity ?? 0);
        $prevSold = (int) $this->value(
            'SELECT sold_quantity FROM ml_products_cache WHERE ml_connection_id = ? AND ml_item_id = ?',
            [$connectionId, $item->id]
        );
        $soldDiff = $newSold - $prevSold;
        if ($soldDiff > 0) {
            $extra['sold_since_sync'] = $soldDiff;
        }

        $data = [
            'sku' => $sku ?: null,
            'title' => $item->title ?? '',
            'price' => (float) ($item->price ?? 0),
            'original_price' => !empty($item->original_price) ? (float) $item->original_price : null,
            'currency_id' => $item->currency_id ?? 'CLP',
            'available_quantity' => (int) ($item->available_quantity ?? 0),
            'sold_quantity' => $newSold,
            'condition' => $item->condition ?? '',
            'listing_type_id' => $item->listing_type_id ?? '',
            'status' => $item->status ?? '',
            'category_id' => $item->category_id ?? '',
            'thumbnail' => $item->thumbnail ?? '',
            'permalink' => $item->permalink ?? '',
            'start_time' => $startTime,
            'extra_data' => !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
        ];
        $this->upsert($connectionId, $item->id, $data);

        if (!empty($item->variations)) {
            foreach ($item->variations as $variation) {
                $vSku = '';
                $vAttrs = $variation->attributes ?? $variation->attribute_combinations ?? [];
                foreach ($vAttrs as $attr) {
                    if (in_array($attr->id ?? '', ['SELLER_SKU', 'SELLER_SKU_', 'SELLER_SKU_OTH', 'SKU', 'MODEL'])) {
                        $v = trim($attr->value_name ?? $attr->name ?? '');
                        if ($v) { $vSku = $v; break; }
                    }
                }

                $vData = array_merge($data, [
                    'parent_ml_item_id' => $item->id,
                    'sku' => $vSku ?: null,
                    'price' => (float) ($variation->price ?? $item->price ?? 0),
                    'available_quantity' => (int) ($variation->available_quantity ?? $item->available_quantity ?? 0),
                ]);
                // Variations inherit parent's sold_quantity, start_time, extra_data
                $vData['sold_quantity'] = (int) ($variation->sold_quantity ?? $item->sold_quantity ?? 0);

                $this->upsert($connectionId, (string) $variation->id, $vData);
            }
        }

        return $item->id;
    }

    public function getMetrics($connectionId) {
        $row = $this->row(
            'SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) as active,
                SUM(available_quantity) as total_stock,
                SUM(CASE WHEN available_quantity <= 0 THEN 1 ELSE 0 END) as stock_0,
                SUM(CASE WHEN available_quantity BETWEEN 1 AND 2 THEN 1 ELSE 0 END) as stock_1_2,
                SUM(CASE WHEN available_quantity BETWEEN 3 AND 5 THEN 1 ELSE 0 END) as stock_3_5,
                SUM(CASE WHEN available_quantity BETWEEN 6 AND 10 THEN 1 ELSE 0 END) as stock_6_10,
                SUM(CASE WHEN available_quantity BETWEEN 11 AND 20 THEN 1 ELSE 0 END) as stock_11_20,
                SUM(CASE WHEN available_quantity BETWEEN 21 AND 50 THEN 1 ELSE 0 END) as stock_21_50,
                SUM(CASE WHEN available_quantity > 50 THEN 1 ELSE 0 END) as stock_51
            FROM ml_products_cache WHERE ml_connection_id = ?',
            [$connectionId]
        );

        if (!$row) {
            return [
                'total' => 0, 'active' => 0, 'low_stock' => 0, 'total_stock' => 0,
                'stock_ranges' => ['empty' => 0, 'low' => 0, 'medium' => 0, 'mid' => 0, 'high' => 0, 'vhigh' => 0, 'max' => 0]
            ];
        }

        $lowStock = (int) ($row->stock_0 ?? 0) + (int) ($row->stock_1_2 ?? 0) + (int) ($row->stock_3_5 ?? 0);

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'low_stock' => $lowStock,
            'total_stock' => (int) ($row->total_stock ?? 0),
            'stock_ranges' => [
                'empty' => (int) ($row->stock_0 ?? 0),
                'low'   => (int) ($row->stock_1_2 ?? 0),
                'medium'=> (int) ($row->stock_3_5 ?? 0),
                'mid'   => (int) ($row->stock_6_10 ?? 0),
                'high'  => (int) ($row->stock_11_20 ?? 0),
                'vhigh' => (int) ($row->stock_21_50 ?? 0),
                'max'   => (int) ($row->stock_51 ?? 0),
            ],
        ];
    }

    public function getProducts($connectionId, $page = 1, $limit = 20, $search = '', $status = '') {
        $params = [$connectionId];
        $where = "WHERE ml_connection_id = ? AND (parent_ml_item_id IS NULL OR parent_ml_item_id = '')";

        if ($search) {
            $where .= ' AND (title LIKE ? OR sku LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        if ($status !== '' && $status !== 'all') {
            $where .= ' AND status = ?';
            $params[] = $status;
        }

        $countRow = $this->row("SELECT COUNT(*) as cnt FROM ml_products_cache $where", $params);
        $total = (int) ($countRow->cnt ?? 0);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $limit;

        $items = $this->rows(
            "SELECT id, ml_item_id, parent_ml_item_id, sku, title, price, original_price, currency_id, available_quantity, sold_quantity,
                    `condition`, listing_type_id, status, category_id, category_name, thumbnail, permalink, start_time, extra_data, synced_at
             FROM ml_products_cache $where
             ORDER BY title ASC
             LIMIT $limit OFFSET $offset",
            $params
        );

        // ── Batch-fetch variations for all parent products ──
        $parentIds = [];
        $parentLookup = [];
        foreach ($items as $item) {
            if ($item->parent_ml_item_id) {
                $parentLookup[$item->ml_item_id] = $item->parent_ml_item_id;
            } else {
                $parentIds[] = $item->ml_item_id;
            }
        }

        $variationsByParent = [];
        if (!empty($parentIds)) {
            $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
            $varRows = $this->rows(
                "SELECT ml_item_id, parent_ml_item_id, sku, title, price, original_price, currency_id, available_quantity, sold_quantity,
                        `condition`, listing_type_id, status, thumbnail
                 FROM ml_products_cache
                 WHERE ml_connection_id = ? AND parent_ml_item_id IN ($placeholders)
                 ORDER BY title ASC",
                array_merge([$connectionId], $parentIds)
            );
            foreach ($varRows as $vr) {
                $pid = $vr->parent_ml_item_id;
                if (!isset($variationsByParent[$pid])) $variationsByParent[$pid] = [];
                $variationsByParent[$pid][] = [
                    'id' => $vr->ml_item_id,
                    'title' => $vr->title,
                    'price' => (float) $vr->price,
                    'original_price' => $vr->original_price ? (float) $vr->original_price : null,
                    'currency_id' => $vr->currency_id,
                    'available_quantity' => (int) $vr->available_quantity,
                    'sold_quantity' => (int) ($vr->sold_quantity ?? 0),
                    'condition' => $vr->condition,
                    'listing_type_id' => $vr->listing_type_id,
                    'thumbnail' => $vr->thumbnail,
                    'sku' => $vr->sku,
                    'status' => $vr->status,
                ];
            }
        }

        $result = [];
        foreach ($items as $item) {
            $isVariation = !empty($item->parent_ml_item_id);
            $entry = [
                'id' => $item->ml_item_id,
                'parent_ml_item_id' => $item->parent_ml_item_id,
                'title' => $item->title,
                'price' => (float) $item->price,
                'original_price' => $item->original_price ? (float) $item->original_price : null,
                'currency_id' => $item->currency_id,
                'available_quantity' => (int) $item->available_quantity,
                'sold_quantity' => (int) ($item->sold_quantity ?? 0),
                'condition' => $item->condition,
                'listing_type_id' => $item->listing_type_id,
                'thumbnail' => $item->thumbnail,
                'permalink' => $item->permalink,
                'sku' => $item->sku,
                'status' => $item->status,
                'category_id' => $item->category_id,
                'category_name' => $item->category_name,
                'is_variation' => $isVariation,
                'has_variations' => !$isVariation && !empty($variationsByParent[$item->ml_item_id]),
                'variations' => $variationsByParent[$item->ml_item_id] ?? [],
            ];
            $result[] = $entry;
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

    public function getProduct($connectionId, $mlItemId) {
        return $this->row(
            "SELECT id, ml_item_id, parent_ml_item_id, sku, title, price, original_price, currency_id, available_quantity, sold_quantity,
                    `condition`, listing_type_id, status, category_id, category_name, thumbnail, permalink, start_time, extra_data, synced_at
             FROM ml_products_cache
             WHERE ml_connection_id = ? AND ml_item_id = ?",
            [$connectionId, $mlItemId]
        );
    }

    public function getVariations($connectionId, $parentItemId) {
        return $this->rows(
            "SELECT ml_item_id, parent_ml_item_id, sku, title, price, original_price, currency_id, available_quantity, sold_quantity,
                    `condition`, listing_type_id, status, thumbnail, start_time, extra_data, synced_at
             FROM ml_products_cache
             WHERE ml_connection_id = ? AND parent_ml_item_id = ?
             ORDER BY title ASC",
            [$connectionId, $parentItemId]
        );
    }

    public function getAllForExport($connectionId, $search = '', $status = '', $maxItems = 20000) {
        $params = [$connectionId];
        $where = 'WHERE ml_connection_id = ?';

        if ($search) {
            $where .= ' AND (title LIKE ? OR sku LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        if ($status) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }

        $total = (int) $this->value(
            "SELECT COUNT(*) FROM ml_products_cache $where",
            $params
        );

        $limit = min($maxItems, $total);
        $items = $this->rows(
            "SELECT id, ml_item_id, sku, title, price, original_price, currency_id, available_quantity, sold_quantity,
                    `condition`, listing_type_id, status, category_id, category_name,
                    thumbnail, permalink, synced_at
             FROM ml_products_cache $where
             ORDER BY title ASC
             LIMIT $limit",
            $params
        );

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => $item->ml_item_id,
                'title' => $item->title,
                'price' => (float) $item->price,
                'original_price' => $item->original_price ? (float) $item->original_price : null,
                'currency_id' => $item->currency_id,
                'available_quantity' => (int) $item->available_quantity,
                'sold_quantity' => (int) ($item->sold_quantity ?? 0),
                'condition' => $item->condition,
                'listing_type_id' => $item->listing_type_id,
                'thumbnail' => $item->thumbnail,
                'permalink' => $item->permalink,
                'sku' => $item->sku,
                'status' => $item->status,
                'category_id' => $item->category_id,
                'category_name' => $item->category_name,
            ];
        }

        return ['items' => $result, 'total' => $total];
    }

    public function resolveCategoryNames($connectionId) {
        $rows = $this->rows(
            "SELECT DISTINCT category_id FROM ml_products_cache
             WHERE ml_connection_id = ? AND category_id IS NOT NULL AND category_id != ''
             AND (category_name IS NULL OR category_name = '')",
            [$connectionId]
        );

        if (empty($rows)) return 0;

        $categoryIds = array_map(function($r) { return $r->category_id; }, $rows);
        $names = [];

        $store = $this->db->query("SELECT access_token FROM ml_connections WHERE id = ? AND is_active = 1");
        $store->execute([$connectionId]);
        $storeRow = $store->fetch();
        if (!$storeRow) return 0;

        $api = new MercadoLibreApiService($this->db);
        $token = MercadoLibreAuthService::decrypt($storeRow->access_token);
        if (!$token) return 0;

        foreach ($categoryIds as $catId) {
            $resp = $api->callWithRetry('/categories/' . urlencode($catId), 'GET', null, $token);
            if ($resp['code'] === 200 && !empty($resp['body']->name)) {
                $names[$catId] = $resp['body']->name;
            }
        }

        $updated = 0;
        foreach ($names as $catId => $catName) {
            $stmt = $this->db->query(
                "UPDATE ml_products_cache SET category_name = ? WHERE ml_connection_id = ? AND category_id = ? AND (category_name IS NULL OR category_name = '')"
            );
            $stmt->execute([$catName, $connectionId, $catId]);
            $updated += $stmt->rowCount();
        }

        return $updated;
    }

    public function getCount($connectionId) {
        return (int) $this->value(
            'SELECT COUNT(*) FROM ml_products_cache WHERE ml_connection_id = ?',
            [$connectionId]
        );
    }

    public function getLastSyncAt($connectionId) {
        return $this->value(
            'SELECT MAX(synced_at) FROM ml_products_cache WHERE ml_connection_id = ?',
            [$connectionId]
        );
    }

    public function purgeStore($connectionId) {
        $this->execute('DELETE FROM ml_products_cache WHERE ml_connection_id = ?', [$connectionId]);
    }

    public function getAllItemIds($connectionId) {
        $rows = $this->rows(
            'SELECT ml_item_id FROM ml_products_cache WHERE ml_connection_id = ?',
            [$connectionId]
        );
        return array_map(function($r) { return $r->ml_item_id; }, $rows);
    }

    public function getCategoriesStats($connectionId) {
        $rows = $this->rows(
            'SELECT category_id, category_name, COUNT(*) as cnt
             FROM ml_products_cache
             WHERE ml_connection_id = ? AND category_name IS NOT NULL AND category_name != \'\'
             GROUP BY category_id, category_name
             ORDER BY cnt DESC',
            [$connectionId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row->category_name] = (int) $row->cnt;
        }
        return $result;
    }

    public function getListingTypesStats($connectionId) {
        $rows = $this->rows(
            'SELECT listing_type_id, COUNT(*) as cnt
             FROM ml_products_cache
             WHERE ml_connection_id = ?
             GROUP BY listing_type_id
             ORDER BY cnt DESC',
            [$connectionId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row->listing_type_id ?? 'unknown'] = (int) $row->cnt;
        }
        return $result;
    }
}
