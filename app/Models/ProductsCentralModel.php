<?php

class ProductsCentralModel extends Model {

    /**
     * SKUs que en la práctica significan "no tengo SKU" (placeholders que
     * algunos vendedores escriben a mano en vez de dejar el campo vacío).
     * Ver Docs/database/PRODUCTS-CENTRAL-LIMPIEZA-CASOS-CONFLICTIVOS.md.
     */
    private const SKU_PLACEHOLDERS = ['-', '--', 'n/a', 'na', 'null', 'none', 's/n', 'sn', '0'];

    /**
     * Prefijos de SKU que indican un pack de varias unidades (ver
     * Docs/database/PRODUCTS-CENTRAL-SKU-SEGMENTACION.md). El orden no
     * importa: ningún prefijo es substring-prefijo de otro.
     */
    private const SKU_PACK_PREFIXES = ['TRIPACK-' => 'TRIPACK', 'PACK-' => 'PACK'];

    /**
     * Fase 1 (con limpieza): espeja ml_products_cache -> products_central
     * para una conexión, normalizando texto y descartando filas basura.
     * Borra + reinserta en transacción. A diferencia de la versión anterior,
     * el conteo resultante YA NO es necesariamente idéntico a
     * ml_products_cache (puede haber menos filas si se descartó basura) —
     * ver verifyAgainstCache() y el .md de casos conflictivos.
     *
     * Importante: la limpieza nunca fusiona filas. Cada `ml_item_id` de
     * ml_products_cache sigue generando como máximo una fila propia en
     * products_central, aunque comparta título exacto con su producto padre
     * o con otras variantes (color/talla) — fusionarlas por título haría
     * desaparecer variantes con stock/SKU distintos. Ver "Deduplicación por
     * título" en el .md de casos conflictivos.
     *
     * No interrumpe el sync si falla: se loguea y se retorna false.
     */
    public function mirrorFromMlCache($connectionId) {
        try {
            $rawRows = $this->rows(
                'SELECT ml_item_id, parent_ml_item_id, sku, title, price, original_price,
                        currency_id, available_quantity, sold_quantity, `condition`, listing_type_id,
                        status, category_id, category_name, thumbnail, permalink
                 FROM ml_products_cache
                 WHERE ml_connection_id = ?',
                [$connectionId]
            );

            $cleanRows = [];
            $discarded = 0;
            foreach ($rawRows as $raw) {
                $clean = $this->cleanCacheRow($connectionId, $raw);
                if ($clean === null) {
                    $discarded++;
                    continue;
                }
                $cleanRows[] = $clean;
            }

            $this->assignSkuPackFields($cleanRows);
            $this->assignFamilyKeys($cleanRows);

            // El delete+reinsert de abajo perdería cualquier `description` ya
            // traída (ver ProductDescriptionService) porque no viene de
            // ml_products_cache. Se guarda aparte por ml_item_id y se
            // reaplica después del insert.
            $existingDescriptions = $this->rows(
                'SELECT ml_item_id, description FROM products_central
                 WHERE ml_connection_id = ? AND description IS NOT NULL',
                [$connectionId]
            );

            $this->db->beginTransaction();

            $this->execute('DELETE FROM products_central WHERE ml_connection_id = ?', [$connectionId]);

            if (!empty($cleanRows)) {
                $this->insertCleanRows($cleanRows);
            }

            foreach ($existingDescriptions as $row) {
                $this->execute(
                    'UPDATE products_central SET description = ? WHERE ml_connection_id = ? AND ml_item_id = ?',
                    [$row->description, $connectionId, $row->ml_item_id]
                );
            }

            $this->db->commit();
            $this->refreshTitleStats();

            if ($discarded > 0) {
                error_log("[PRODUCTS_CENTRAL] Conexión $connectionId: $discarded fila(s) descartada(s) en la limpieza (sin título ni SKU utilizable).");
            }

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[PRODUCTS_CENTRAL] Error al espejar conexión ' . $connectionId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpia y valida una fila cruda de ml_products_cache. Devuelve el array
     * listo para insertar, o null si la fila debe descartarse.
     *
     * Reglas (detalle y justificación en el .md de casos conflictivos):
     *  - title: trim, colapsa espacios/tabs/saltos de línea, decodifica
     *    entidades HTML y quita tags. NO cambia mayúsculas/minúsculas ni
     *    acentos (evita fusionar productos distintos con nombres parecidos).
     *  - sku: trim, colapsa espacios, se pasa a mayúsculas (consistencia
     *    para búsqueda/índice), y placeholders tipo "-", "N/A", "0" se
     *    tratan como ausencia de SKU (NULL).
     *  - Se descarta la fila solo si queda sin título Y sin SKU (no hay
     *    forma de mostrarla ni buscarla). Todo lo demás se conserva.
     *  - price / available_quantity / sold_quantity negativos (datos
     *    corruptos) se llevan a 0 en vez de descartar el producto entero.
     */
    private function cleanCacheRow($connectionId, $raw) {
        $itemId = trim((string) $raw->ml_item_id);
        if ($itemId === '') {
            return null;
        }

        $title = $this->normalizeTitle($raw->title);
        $sku = $this->normalizeSku($raw->sku);

        if ($title === '' && $sku === null) {
            return null;
        }

        $price = (float) $raw->price;
        if ($price < 0) $price = 0.0;

        $originalPrice = $raw->original_price !== null ? (float) $raw->original_price : null;
        if ($originalPrice !== null && $originalPrice < 0) $originalPrice = null;

        $availableQuantity = (int) $raw->available_quantity;
        if ($availableQuantity < 0) $availableQuantity = 0;

        $soldQuantity = (int) $raw->sold_quantity;
        if ($soldQuantity < 0) $soldQuantity = 0;

        $parentItemId = trim((string) ($raw->parent_ml_item_id ?? ''));

        return [
            'ml_connection_id' => (int) $connectionId,
            'ml_item_id' => $itemId,
            'parent_ml_item_id' => $parentItemId !== '' ? $parentItemId : null,
            'sku' => $sku,
            'title' => $title !== '' ? $title : null,
            'price' => $price,
            'original_price' => $originalPrice,
            'currency_id' => trim((string) ($raw->currency_id ?? '')) ?: 'CLP',
            'available_quantity' => $availableQuantity,
            'sold_quantity' => $soldQuantity,
            'condition' => trim((string) ($raw->condition ?? '')),
            'listing_type_id' => trim((string) ($raw->listing_type_id ?? '')),
            'status' => trim((string) ($raw->status ?? '')),
            'category_id' => trim((string) ($raw->category_id ?? '')),
            'category_name' => trim((string) ($raw->category_name ?? '')),
            'thumbnail' => trim((string) ($raw->thumbnail ?? '')),
            'permalink' => trim((string) ($raw->permalink ?? '')),
            'source_channel' => 'mercadolibre',
        ];
    }

    private function normalizeTitle($raw) {
        $title = (string) ($raw ?? '');
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = strip_tags($title);
        $title = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $title) ?? $title;
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        $title = trim($title);
        if (mb_strlen($title, 'UTF-8') > 500) {
            $title = mb_substr($title, 0, 500, 'UTF-8');
        }
        return $title;
    }

    private function normalizeSku($raw) {
        if ($raw === null) return null;
        $sku = trim((string) $raw);
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;
        $sku = trim($sku);
        if ($sku === '') return null;
        if (in_array(mb_strtolower($sku, 'UTF-8'), self::SKU_PLACEHOLDERS, true)) return null;
        $sku = mb_strtoupper($sku, 'UTF-8');
        if (mb_strlen($sku, 'UTF-8') > 100) {
            $sku = mb_substr($sku, 0, 100, 'UTF-8');
        }
        return $sku;
    }

    /**
     * Calcula sku_pack_type / sku_base para un batch completo de filas ya
     * limpias (típicamente todo el catálogo de una conexión). El matching de
     * sku_base se hace SOLO contra SKUs de este mismo batch (misma tienda):
     * un pack armado por un vendedor referencia SKUs de su propio catálogo,
     * no de otras tiendas. Modifica $rows por referencia.
     *
     * Ver Docs/database/PRODUCTS-CENTRAL-SKU-SEGMENTACION.md para el
     * análisis que valida esta heurística (84.7% de match real sobre datos
     * de producción).
     */
    private function assignSkuPackFields(array &$rows) {
        $skuSet = [];
        foreach ($rows as $row) {
            if ($row['sku'] !== null) {
                $skuSet[$row['sku']] = true;
            }
        }

        foreach ($rows as &$row) {
            $packType = $this->detectSkuPackType($row['sku']);
            $row['sku_pack_type'] = $packType;
            $row['sku_base'] = $packType !== null
                ? $this->resolveSkuBase($row['sku'], $packType, $skuSet)
                : null;
        }
        unset($row);
    }

    private function detectSkuPackType($sku) {
        if ($sku === null) return null;
        foreach (self::SKU_PACK_PREFIXES as $prefix => $type) {
            if (strpos($sku, $prefix) === 0) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Quita el prefijo de pack y prueba, de más a menos segmentos (el
     * candidato más específico primero), si algún prefijo del resto
     * matchea un SKU real existente en $skuSet. Devuelve null si no hay
     * match confiable (no se inventa el SKU base).
     *
     * Empezar por el candidato más largo evita falsos positivos: con
     * "menos a más" (orden anterior), un pack como PACK-S-289-NE-NE-M
     * probaba primero el candidato "S" y podía matchear el SKU de un
     * producto totalmente distinto que por coincidencia usa "S" (talla)
     * como SKU literal. Se descartan además candidatos de 1-2 caracteres
     * (típicamente tallas sueltas: S, M, L, XL) por ser demasiado genéricos
     * para identificar un producto específico de forma confiable.
     */
    private function resolveSkuBase($sku, $packType, array $skuSet) {
        $prefix = array_search($packType, self::SKU_PACK_PREFIXES, true);
        if ($prefix === false) return null;

        $rest = substr($sku, strlen($prefix));
        $parts = explode('-', $rest);
        for ($i = count($parts); $i >= 1; $i--) {
            $candidate = implode('-', array_slice($parts, 0, $i));
            if (mb_strlen($candidate, 'UTF-8') < 3) continue;
            if ($candidate !== $sku && isset($skuSet[$candidate])) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Calcula family_key para un batch completo de filas ya limpias (mismo
     * scope que assignSkuPackFields(): una tienda a la vez). Agrupa, dentro
     * de esa tienda, qué filas son "el mismo producto físico":
     *
     *  - Variantes (parent_ml_item_id no nulo) heredan la family_key de su
     *    padre (usan directamente el ml_item_id del padre).
     *  - Productos padre/individuales (sin parent_ml_item_id) son su propia
     *    family_key (su propio ml_item_id).
     *  - Packs/tripacks cuyo sku_base matcheó un SKU real de esta tienda
     *    (ver assignSkuPackFields()) heredan la family_key YA calculada de
     *    la fila dueña de ese SKU — así un pack de una variante también
     *    queda bien agrupado. Si no matchea, quedan con su propia
     *    family_key (fila huérfana, igual que antes de este cambio).
     *
     * Modifica $rows por referencia. No requiere re-sincronizar contra la
     * API de ML: se calcula sobre datos ya limpiados de esta misma tienda.
     */
    private function assignFamilyKeys(array &$rows) {
        $familyByItemId = [];
        foreach ($rows as &$row) {
            $family = $row['parent_ml_item_id'] ?: $row['ml_item_id'];
            $row['family_key'] = $family;
            $familyByItemId[$row['ml_item_id']] = $family;
        }
        unset($row);

        $itemIdBySku = [];
        foreach ($rows as $row) {
            if ($row['sku'] !== null && !isset($itemIdBySku[$row['sku']])) {
                $itemIdBySku[$row['sku']] = $row['ml_item_id'];
            }
        }

        foreach ($rows as &$row) {
            if ($row['sku_pack_type'] !== null && $row['sku_base'] !== null
                && isset($itemIdBySku[$row['sku_base']])) {
                $baseItemId = $itemIdBySku[$row['sku_base']];
                if (isset($familyByItemId[$baseItemId])) {
                    $row['family_key'] = $familyByItemId[$baseItemId];
                }
            }
        }
        unset($row);
    }

    /**
     * Inserta en lotes (evita pasar miles de placeholders en un solo
     * statement). Cada fila mantiene su propio ml_item_id — nunca se
     * combinan dos filas en un INSERT de una sola fila.
     */
    private function insertCleanRows(array $rows) {
        $cols = [
            'ml_connection_id', 'ml_item_id', 'parent_ml_item_id', 'sku', 'sku_pack_type', 'sku_base', 'family_key',
            'title', 'price', 'original_price', 'currency_id', 'available_quantity', 'sold_quantity', 'condition',
            'listing_type_id', 'status', 'category_id', 'category_name', 'thumbnail', 'permalink', 'source_channel',
        ];
        $colSql = implode(', ', array_map(function ($c) { return "`$c`"; }, $cols));
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($cols), '?')) . ')';

        foreach (array_chunk($rows, 200) as $chunk) {
            $placeholders = [];
            $params = [];
            foreach ($chunk as $row) {
                $placeholders[] = $rowPlaceholder;
                foreach ($cols as $c) {
                    $params[] = $row[$c];
                }
            }
            $this->execute(
                "INSERT INTO products_central ($colSql) VALUES " . implode(', ', $placeholders),
                $params
            );
        }
    }

    /**
     * Recalcula products_central_title_stats (cuántas tiendas distintas
     * tiene cada título). Es una agregación cara (GROUP BY sobre toda la
     * tabla) — se corre acá, una vez por sync, en vez de en cada request de
     * getProducts(). No se envuelve en la transacción del mirror porque debe
     * reflejar el estado consolidado de TODAS las conexiones, no solo la que
     * se acaba de sincronizar.
     */
    public function refreshTitleStats() {
        try {
            $this->db->beginTransaction();
            $this->execute('DELETE FROM products_central_title_stats');
            $this->execute(
                'INSERT INTO products_central_title_stats (title, store_count)
                 SELECT title, COUNT(DISTINCT ml_connection_id)
                 FROM products_central
                 GROUP BY title'
            );
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[PRODUCTS_CENTRAL] Error al refrescar title_stats: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Listado paginado agrupado por family_key: cada fila representa un
     * producto físico (padre + variantes de talla/color + packs/tripacks
     * colapsados). $connectionId filtra directamente por tienda.
     *
     * La fila "representante" de cada familia se elige igual que antes
     * priorizaba por título (ver WINNER_SUBQUERY): padre/individual antes
     * que variante, individual antes que pack, con SKU antes que sin SKU,
     * y por último id menor.
     */
    public function getProducts($connectionId = null, $page = 1, $limit = 20, $search = '', $status = '') {
        $params = [];
        $where = 'WHERE 1=1';

        if ($connectionId) {
            $where .= ' AND t.ml_connection_id = ?';
            $params[] = (int) $connectionId;
        }
        if ($search !== '') {
            $where .= ' AND (t.title LIKE ? OR t.sku LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($status !== '' && $status !== 'all') {
            $where .= ' AND t.status = ?';
            $params[] = $status;
        }

        $countRow = $this->row(
            "SELECT COUNT(*) as cnt FROM (
                SELECT DISTINCT t.ml_connection_id, t.family_key FROM products_central t $where
             ) g",
            $params
        );
        $total = (int) ($countRow->cnt ?? 0);
        $limit = max(1, (int) $limit);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = max(1, min((int) $page, $totalPages));
        $offset = ($page - 1) * $limit;

        $items = $this->rows(
            "SELECT * FROM (
                SELECT t.id, t.ml_connection_id, t.ml_item_id, t.parent_ml_item_id, t.sku,
                       t.sku_pack_type, t.sku_base, t.family_key, t.title,
                       t.price, t.original_price, t.currency_id, t.available_quantity, t.sold_quantity,
                       t.`condition`, t.listing_type_id, t.status, t.category_id, t.category_name,
                       t.thumbnail, t.permalink, t.source_channel, t.synced_at,
                       c.store_name, c.ml_nickname,
                       ROW_NUMBER() OVER (
                           PARTITION BY t.ml_connection_id, t.family_key
                           ORDER BY (t.parent_ml_item_id IS NULL) DESC,
                                    (t.sku_pack_type IS NULL) DESC,
                                    (t.sku IS NULL OR t.sku = '') ASC,
                                    t.id ASC
                       ) AS rn,
                       COUNT(*) OVER (PARTITION BY t.ml_connection_id, t.family_key) AS member_count,
                       SUM(t.available_quantity) OVER (PARTITION BY t.ml_connection_id, t.family_key) AS family_stock
                FROM products_central t
                LEFT JOIN ml_connections c ON c.id = t.ml_connection_id
                $where
             ) ranked
             WHERE rn = 1
             ORDER BY title ASC, id ASC
             LIMIT $limit OFFSET $offset",
            $params
        );

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => $item->ml_item_id,
                'parent_ml_item_id' => $item->parent_ml_item_id,
                'is_variation' => !empty($item->parent_ml_item_id),
                'family_key' => $item->family_key,
                'member_count' => (int) $item->member_count,
                'family_stock' => (int) $item->family_stock,
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
                'sku_pack_type' => $item->sku_pack_type,
                'sku_base' => $item->sku_base,
                'status' => $item->status,
                'category_id' => $item->category_id,
                'category_name' => $item->category_name,
                'source_channel' => $item->source_channel,
                'store_name' => $item->store_name ?: ($item->ml_nickname ?: '—'),
                'ml_connection_id' => (int) $item->ml_connection_id,
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

    /**
     * Detalle de una familia (variantes/packs individuales) para expandir
     * una fila colapsada de getProducts().
     */
    public function getFamilyMembers($connectionId, $familyKey) {
        return $this->rows(
            'SELECT id, ml_item_id, parent_ml_item_id, sku, sku_pack_type, sku_base, title,
                    price, available_quantity, sold_quantity, `condition`, status, thumbnail, permalink
             FROM products_central
             WHERE ml_connection_id = ? AND family_key = ?
             ORDER BY (sku_pack_type IS NULL) DESC, id ASC',
            [(int) $connectionId, $familyKey]
        );
    }

    /**
     * Métricas de la pantalla principal (Dashboard), mismo cálculo que
     * MlProductsCacheModel::getMetrics() pero sobre products_central (dato
     * ya limpiado) en vez de ml_products_cache (dato crudo).
     */
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
            FROM products_central WHERE ml_connection_id = ?',
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

    /**
     * Métricas del catálogo agrupadas por family_key (mismo criterio que
     * getProducts()): un producto físico cuenta una vez, con su stock
     * sumado entre variantes/packs y "activo" si alguna fila del grupo lo
     * está. $connectionId filtra directamente por tienda.
     */
    public function getGlobalMetrics($connectionId = null) {
        $params = [];
        $where = 'WHERE 1=1';
        if ($connectionId) {
            $where .= ' AND ml_connection_id = ?';
            $params[] = (int) $connectionId;
        }

        $row = $this->row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_active_family = 1 THEN 1 ELSE 0 END) as active,
                SUM(family_stock) as total_stock,
                SUM(CASE WHEN family_stock <= 0 THEN 1 ELSE 0 END) as stock_0,
                SUM(CASE WHEN family_stock BETWEEN 1 AND 2 THEN 1 ELSE 0 END) as stock_1_2,
                SUM(CASE WHEN family_stock BETWEEN 3 AND 5 THEN 1 ELSE 0 END) as stock_3_5,
                SUM(CASE WHEN family_stock BETWEEN 6 AND 10 THEN 1 ELSE 0 END) as stock_6_10,
                SUM(CASE WHEN family_stock BETWEEN 11 AND 20 THEN 1 ELSE 0 END) as stock_11_20,
                SUM(CASE WHEN family_stock BETWEEN 21 AND 50 THEN 1 ELSE 0 END) as stock_21_50,
                SUM(CASE WHEN family_stock > 50 THEN 1 ELSE 0 END) as stock_51
             FROM (
                SELECT ml_connection_id, family_key,
                       SUM(available_quantity) AS family_stock,
                       MAX(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS is_active_family
                FROM products_central
                $where
                GROUP BY ml_connection_id, family_key
             ) fam",
            $params
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

    public function getCount($connectionId = null) {
        if ($connectionId) {
            return (int) $this->value(
                'SELECT COUNT(*) FROM products_central WHERE ml_connection_id = ?',
                [$connectionId]
            );
        }
        return (int) $this->value('SELECT COUNT(*) FROM products_central');
    }

    /**
     * Compara, por conexión, el conteo de ml_products_cache vs products_central.
     * Desde que el mirror limpia datos, central_count puede ser MENOR que
     * cache_count (filas descartadas por basura) pero NUNCA mayor — eso sí
     * sería un bug real. El llamador decide el margen aceptable; ver
     * MlController::centralVerify() y Docs/database/PRODUCTS-CENTRAL-LIMPIEZA-CASOS-CONFLICTIVOS.md.
     */
    public function verifyAgainstCache() {
        return $this->rows(
            'SELECT
                c.id AS connection_id,
                c.store_name,
                c.is_active,
                (SELECT COUNT(*) FROM ml_products_cache m WHERE m.ml_connection_id = c.id) AS cache_count,
                (SELECT COUNT(*) FROM products_central p WHERE p.ml_connection_id = c.id) AS central_count,
                (SELECT MAX(synced_at) FROM products_central p WHERE p.ml_connection_id = c.id) AS last_mirror_at
             FROM ml_connections c
             ORDER BY c.id'
        );
    }

    /**
     * Subquery común: una fila "ganadora" por family_key, igual al criterio
     * de desempate que usa getProducts() para decidir qué fila representa a
     * cada producto físico agrupado (padre/individual > variante, individual
     * > pack, con SKU > sin SKU, id menor). Solo a esa fila le tiene sentido
     * pedirle la descripción — es la única que se muestra en el Catálogo
     * Central.
     */
    private const WINNER_SUBQUERY = "
        SELECT p.id, p.ml_connection_id, p.ml_item_id, p.description
        FROM products_central p
        WHERE p.id IN (
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY ml_connection_id, family_key
                    ORDER BY (parent_ml_item_id IS NULL) DESC,
                             (sku_pack_type IS NULL) DESC,
                             (sku IS NULL OR sku = '') ASC,
                             id ASC
                ) AS rn
                FROM products_central
            ) ranked WHERE ranked.rn = 1
        )
    ";

    /**
     * Estado de la carga de descripciones para el set deduplicado (~31k
     * filas "ganadoras" de ~154k totales, ver .md de descripciones).
     * `blocked_inactive` son ganadoras cuya tienda está desconectada — no se
     * les puede pedir descripción (sin token válido) hasta que se reactive.
     */
    public function getDescriptionStats() {
        $row = $this->row(
            'SELECT
                COUNT(*) AS total_winners,
                SUM(CASE WHEN w.description IS NOT NULL THEN 1 ELSE 0 END) AS done,
                SUM(CASE WHEN w.description IS NULL AND c.is_active = 1 THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN w.description IS NULL AND c.is_active = 0 THEN 1 ELSE 0 END) AS blocked_inactive
             FROM (' . self::WINNER_SUBQUERY . ') w
             JOIN ml_connections c ON c.id = w.ml_connection_id'
        );

        return [
            'total' => (int) ($row->total_winners ?? 0),
            'done' => (int) ($row->done ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'blocked_inactive' => (int) ($row->blocked_inactive ?? 0),
        ];
    }

    /**
     * Siguiente tanda de filas ganadoras sin descripción y con tienda
     * activa. El token se obtiene aparte vía MercadoLibreAuthService (que
     * sabe refrescarlo si está por expirar) — acá solo se identifica qué
     * conexión le corresponde a cada fila.
     */
    public function getNextDescriptionCandidates($limit = 20) {
        $limit = max(1, min(100, (int) $limit));
        return $this->rows(
            'SELECT w.id, w.ml_connection_id, w.ml_item_id
             FROM (' . self::WINNER_SUBQUERY . ') w
             JOIN ml_connections c ON c.id = w.ml_connection_id
             WHERE w.description IS NULL AND c.is_active = 1
             ORDER BY w.id
             LIMIT ' . $limit
        );
    }

    /**
     * Guarda el resultado de intentar traer la descripción de una fila.
     * $text puede ser '' (ML no tiene descripción para ese ítem) — se
     * guarda igual para no reintentarlo en cada corrida (ver semántica de
     * la columna en la migración).
     */
    public function saveDescription($id, $text) {
        $this->execute('UPDATE products_central SET description = ? WHERE id = ?', [$text, $id]);
    }
}
