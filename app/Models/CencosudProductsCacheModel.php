<?php

class CencosudProductsCacheModel extends Model {

    public function upsert($connectionId, $sku, $data) {
        $existing = $this->row(
            'SELECT id FROM cencosud_products_cache WHERE cencosud_connection_id = ? AND sku = ?',
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
                'UPDATE cencosud_products_cache SET ' . implode(', ', $sets) . ' WHERE cencosud_connection_id = ? AND sku = ?',
                $params
            );
            return $existing->id;
        }

        $cols = array_merge(['cencosud_connection_id', 'sku'], array_keys($data));
        $vals = array_merge([$connectionId, $sku], array_values($data));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colStr = implode(', ', array_map(function ($c) { return "`$c`"; }, $cols));

        return $this->insertGetId(
            "INSERT INTO cencosud_products_cache ($colStr) VALUES ($placeholders)",
            $vals
        );
    }

    /** Upsert desde un item de la API de Cencosud. */
    public function upsertFromCencosudItem($connectionId, $item) {
        $sku = trim((string) ($item->sku ?? ''));
        if ($sku === '') return null;

        // Forma real de cada elemento de GET /v2/stock:
        //   sku, sku_seller, title, warehouseName, isFulfillment, size,
        //   availableStock, securityStock, quantity, active, updatedAt
        // El endpoint no entrega precio; este se completa al actualizarlo o
        // consultarlo aparte, por eso aquí no se toca la columna price.
        $extra = [];
        foreach (['sku_seller', 'warehouseName', 'isFulfillment', 'size',
                  'securityStock', 'intangible_type', 'updatedAt'] as $field) {
            if (isset($item->$field) && $item->$field !== null && $item->$field !== '') {
                $extra[$field] = $item->$field;
            }
        }

        // «active» indica si la publicación está vigente en el marketplace.
        // Se normaliza a PUBLISHED/UNPUBLISHED, que es la convención que ya
        // usan las métricas y los filtros del módulo (igual que en Walmart).
        $activo = $item->active ?? null;

        $data = [
            'title' => (string) ($item->title ?? ''),
            'available_quantity' => (int) ($item->availableStock ?? $item->quantity ?? 0),
            'currency' => 'CLP',
            'published_status' => $activo === null ? null : ($activo ? 'PUBLISHED' : 'UNPUBLISHED'),
            'extra_data' => !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
        ];

        return $this->upsert($connectionId, $sku, $data);
    }

    public function findBySku($connectionId, $sku) {
        return $this->row(
            'SELECT * FROM cencosud_products_cache WHERE cencosud_connection_id = ? AND sku = ?',
            [$connectionId, $sku]
        );
    }

    public function updateLocalPrice($connectionId, $sku, $price) {
        $this->execute(
            'UPDATE cencosud_products_cache SET price = ? WHERE cencosud_connection_id = ? AND sku = ?',
            [$price, $connectionId, $sku]
        );
    }

    public function updateLocalStock($connectionId, $sku, $quantity) {
        $this->execute(
            'UPDATE cencosud_products_cache SET available_quantity = ? WHERE cencosud_connection_id = ? AND sku = ?',
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
            FROM cencosud_products_cache WHERE cencosud_connection_id = ?',
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
        $where = 'WHERE c.cencosud_connection_id = ?';

        if ($search) {
            $clean = preg_replace('/^(TRIPACK|PACK)-/i', '', trim($search));
            $baseSku = null;
            if (preg_match('/^([A-Za-z0-9]+-[0-9]+)/', $clean, $matches)) {
                $baseSku = $matches[1];
            }

            if ($baseSku !== null) {
                $where .= ' AND (c.sku REGEXP ?)';
                $params[] = '^(TRIPACK-|PACK-)?' . $baseSku . '(-|$)';
            } else {
                $where .= ' AND (c.title LIKE ? OR c.sku LIKE ?)';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }
        }

        if ($status !== '' && $status !== 'all') {
            $where .= ' AND c.published_status = ?';
            $params[] = $status;
        }

        $total = (int) $this->value("SELECT COUNT(*) FROM cencosud_products_cache c $where", $params);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $limit;

        $items = $this->rows(
            "SELECT c.id, c.sku, c.cencosud_product_id, c.gtin, c.upc, c.title, c.product_type, c.price, c.currency,
                    c.available_quantity, c.published_status, c.lifecycle_status, c.extra_data, c.synced_at,
                    m.brand AS master_brand, m.category AS master_category, m.description AS master_description,
                    m.color AS master_color, m.size AS master_size, m.gender AS master_gender,
                    ml.thumbnail
             FROM cencosud_products_cache c
             LEFT JOIN products_master m ON c.sku COLLATE utf8mb4_unicode_ci = m.sku
             LEFT JOIN (
                 SELECT sku, MIN(thumbnail) AS thumbnail 
                 FROM ml_products_cache 
                 WHERE sku IS NOT NULL AND thumbnail != '' 
                 GROUP BY sku
             ) ml ON c.sku COLLATE utf8mb4_unicode_ci = ml.sku
             $where
             ORDER BY c.title ASC
             LIMIT $limit OFFSET $offset",
            $params
        );

        // Cargar medidas en memoria para cruzar
        $medidasList = [];
        try {
            $medQuery = $this->query("SELECT categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante FROM product_medidas ORDER BY LENGTH(categoria) DESC, id ASC");
            $medidasList = $medQuery->fetchAll();
        } catch (\Exception $e) {}

        $result = [];
        foreach ($items as $item) {
            $sku = strtoupper($item->sku);
            
            // Buscar medidas
            $peso = null;
            $alto = null;
            $ancho = null;
            $largo = null;

            // 1. Coincidencia directa por variante/SKU
            foreach ($medidasList as $m) {
                if (!empty($m->variante) && strtoupper(trim($m->variante)) === $sku) {
                    $peso = $m->peso_kg;
                    $alto = $m->alto_cm;
                    $ancho = $m->ancho_cm;
                    $largo = $m->grueso_cm;
                    break;
                }
            }

            // 2. Coincidencia por categoría/nombre
            if ($peso === null) {
                $nombre = $item->title;
                $categoria = $item->master_category ?? '';
                $texto = mb_strtolower($nombre . ' ' . $categoria);
                $bestLen = 0;
                foreach ($medidasList as $m) {
                    if (empty($m->variante) && !empty($m->categoria)) {
                        $catLower = mb_strtolower(trim($m->categoria));
                        if (strpos($texto, $catLower) !== false) {
                            $len = mb_strlen($catLower);
                            if ($len > $bestLen) {
                                $bestLen = $len;
                                $peso = $m->peso_kg;
                                $alto = $m->alto_cm;
                                $ancho = $m->ancho_cm;
                                $largo = $m->grueso_cm;
                            }
                        }
                    }
                }
            }

            $result[] = [
                'sku' => $item->sku,
                'cencosud_product_id' => $item->cencosud_product_id,
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
                // Nuevos campos
                'master_brand' => $item->master_brand ?: '—',
                'master_category' => $item->master_category ?: '—',
                'master_description' => $item->master_description ?: '',
                'master_color' => $item->master_color ?: '',
                'master_size' => $item->master_size ?: '',
                'master_gender' => $item->master_gender ?: '',
                'thumbnail' => $item->thumbnail ?: '',
                'peso' => $peso,
                'alto' => $alto,
                'ancho' => $ancho,
                'largo' => $largo,
                'profundidad' => $largo,
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

    /** Lista de SKUs en orden estable (por id). */
    public function getSkusPage($connectionId, $offset, $limit) {
        $offset = (int) $offset;
        $limit = (int) $limit;
        $rows = $this->rows(
            "SELECT sku FROM cencosud_products_cache WHERE cencosud_connection_id = ? ORDER BY id ASC LIMIT $limit OFFSET $offset",
            [$connectionId]
        );
        return array_map(function ($r) { return $r->sku; }, $rows);
    }

    public function getLowStock($connectionId, $threshold = 5) {
        return $this->rows(
            'SELECT sku, title, available_quantity FROM cencosud_products_cache
             WHERE cencosud_connection_id = ? AND available_quantity IS NOT NULL AND available_quantity <= ?
             ORDER BY available_quantity ASC',
            [$connectionId, $threshold]
        );
    }

    public function getCount($connectionId) {
        return (int) $this->value(
            'SELECT COUNT(*) FROM cencosud_products_cache WHERE cencosud_connection_id = ?',
            [$connectionId]
        );
    }

    public function purgeStore($connectionId) {
        $this->execute('DELETE FROM cencosud_products_cache WHERE cencosud_connection_id = ?', [$connectionId]);
    }
}
