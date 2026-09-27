<?php


class MercadoLibreApiService {
    private $rateLimiter;

    public function __construct($db = null) {
        if ($db) {
            $this->rateLimiter = new MlApiRateLimiter($db);
        }
    }

    public function call($endpoint, $method = 'GET', $data = null, $token = null) {
        return MlApiHelper::mlApi($endpoint, $method, $data, $token, $this->rateLimiter);
    }

    public function callWithRetry($endpoint, $method = 'GET', $data = null, $token = null, $maxRetries = 3) {
        $retries = 0;
        $delay = 1000000;
        do {
            $result = $this->call($endpoint, $method, $data, $token);
            if ($result['code'] === 429 && $retries < $maxRetries) {
                $retries++;
                usleep($delay);
                $delay *= 2;
            } else {
                return $result;
            }
        } while ($retries <= $maxRetries);
        return $result;
    }

    public function getUserInfo($token) {
        return $this->call('/users/me', 'GET', null, $token);
    }

    public function searchItems($mlUserId, $token, $limit = 50, $offset = 0, $query = '', $searchType = '') {
        $url = '/users/' . $mlUserId . '/items/search?limit=' . $limit . '&offset=' . $offset;
        if ($query) $url .= '&q=' . urlencode($query);
        if ($searchType) $url .= '&search_type=' . $searchType;
        return $this->call($url, 'GET', null, $token);
    }

    public function scanAllItems($mlUserId, $token, $batchSize = 100) {
        $batchSize = min(100, max(1, $batchSize));
        $scannedIds = [];
        $scrollId = null;
        $total = null;
        while (true) {
            $url = '/users/' . $mlUserId . '/items/search?search_type=scan&limit=' . $batchSize;
            if ($scrollId !== null) {
                $url .= '&scroll_id=' . urlencode($scrollId);
            }
            $search = $this->callWithRetry($url, 'GET', null, $token);
            if ($search['code'] !== 200 || !$search['body']) {
                error_log('[AUDIT] scanAllItems (scroll) falló HTTP ' . ($search['code'] ?? 'sin código') . ' para ml_user_id=' . $mlUserId);
                break;
            }
            $ids = $search['body']->results ?? [];
            if (empty($ids)) break;
            $scannedIds = array_merge($scannedIds, $ids);
            $total = $search['body']->paging->total ?? $total;
            $scrollId = $search['body']->scroll_id ?? null;
            if (!$scrollId) break;
        }
        error_log('[AUDIT] scanAllItems (scroll) devolvió ' . count($scannedIds) . '/' . ($total ?? '?') . ' IDs para ml_user_id=' . $mlUserId);
        return $scannedIds;
    }

    public function getItemDetails(array $ids, $token) {
        if (empty($ids)) return [];
        return $this->callWithRetry('/items?ids=' . implode(',', $ids), 'GET', null, $token);
    }

    public function getItemDetail($itemId, $token) {
        return $this->call('/items/' . $itemId, 'GET', null, $token);
    }

    public function searchItemBySku($sku, $mlUserId, $siteId, $token) {
        $search = $this->call('/sites/' . $siteId . '/search?q=' . urlencode($sku) . '&seller_id=' . $mlUserId, 'GET', null, $token);
        if ($search['code'] !== 200 || empty($search['body']->results)) {
            return null;
        }
        foreach ($search['body']->results as $result) {
            $item = $this->getItemDetail($result->id, $token);
            if ($item['code'] === 200 && $item['body']) {
                $extracted = MlApiHelper::extractSku($item['body']);
                if (strcasecmp(trim($extracted), trim($sku)) === 0) {
                    return $item['body'];
                }
            }
        }
        return null;
    }

    public function updateItemPrice($itemId, $price, $token) {
        return ['code' => 503, 'body' => json_decode(json_encode(['error' => 'Las modificaciones de precio están deshabilitadas.'])), 'raw' => ''];
    }

    public function getCategoryName($categoryId, $token) {
        $response = $this->call('/categories/' . $categoryId, 'GET', null, $token);
        if ($response['code'] === 200 && isset($response['body']->name)) {
            return $response['body']->name;
        }
        return $categoryId;
    }

    public function getProductMetrics($mlUserId, $token) {
        $metrics = [
            'total' => 0,
            'active' => 0,
            'low_stock' => 0,
            'stock_ranges' => ['empty' => 0, 'low' => 0, 'medium' => 0, 'high' => 0],
            'categories' => [],
            'listing_types' => [],
        ];

        $search = $this->call('/users/' . $mlUserId . '/items/search?limit=0', 'GET', null, $token);
        if ($search['code'] === 200 && isset($search['body']->paging->total)) {
            $metrics['total'] = (int) $search['body']->paging->total;
        }

        $searchActive = $this->call('/users/' . $mlUserId . '/items/search?status=active&limit=0', 'GET', null, $token);
        if ($searchActive['code'] === 200 && isset($searchActive['body']->paging->total)) {
            $metrics['active'] = (int) $searchActive['body']->paging->total;
        }

        // Paginar todos los items (activos + inactivos, máx 5000) para análisis completo de stock
        $allIds = [];
        $limit = 200;
        $offset = 0;
        $maxItems = 5000;
        while (true) {
            $searchItems = $this->call(
                '/users/' . $mlUserId . '/items/search?search_type=scan&limit=' . $limit . '&offset=' . $offset,
                'GET', null, $token
            );
            if ($searchItems['code'] !== 200 || empty($searchItems['body']->results)) break;
            $allIds = array_merge($allIds, $searchItems['body']->results);
            $offset += $limit;
            // Si la página devuelve menos resultados que el límite, no hay más datos
            if (count($searchItems['body']->results) < $limit || count($allIds) >= $maxItems) break;
        }
        error_log("getProductMetrics: total=" . ($metrics['total'] ?? '?') . ", active=" . ($metrics['active'] ?? '?') . ", paginated_ids=" . count($allIds));

        // Procesar todos los IDs recolectados en chunks de 20
        foreach (array_chunk($allIds, 20) as $chunk) {
            $detail = $this->call('/items?ids=' . implode(',', $chunk), 'GET', null, $token);
            if ($detail['code'] !== 200 || !is_array($detail['body'])) continue;
            foreach ($detail['body'] as $entry) {
                $obj = $entry->body ?? $entry;
                $qty = (int) ($obj->available_quantity ?? 0);

                if ($qty > 0 && $qty <= 5) {
                    $metrics['low_stock']++;
                }

                if ($qty <= 0) {
                    $metrics['stock_ranges']['empty']++;
                } elseif ($qty <= 5) {
                    $metrics['stock_ranges']['low']++;
                } elseif ($qty <= 20) {
                    $metrics['stock_ranges']['medium']++;
                } else {
                    $metrics['stock_ranges']['high']++;
                }

                $lt = $obj->listing_type_id ?? 'unknown';
                if (!isset($metrics['listing_types'][$lt])) {
                    $metrics['listing_types'][$lt] = 0;
                }
                $metrics['listing_types'][$lt]++;

                $catId = $obj->category_id ?? 'unknown';
                if (!isset($metrics['categories'][$catId])) {
                    $metrics['categories'][$catId] = 0;
                }
                $metrics['categories'][$catId]++;
            }
        }

        // Resolver nombres de categorías
        if (!empty($metrics['categories'])) {
            $catNames = [];
            foreach (array_keys($metrics['categories']) as $catId) {
                $catNames[$catId] = $this->getCategoryName($catId, $token);
            }
            $named = [];
            foreach ($metrics['categories'] as $catId => $count) {
                $name = $catNames[$catId] ?? $catId;
                $named[$name] = $count;
            }
            $metrics['categories'] = $named;
        }

        return $metrics;
    }

    public function buildSkuMap($scannedIds, $token) {
        $skuMap = [];
        foreach (array_chunk($scannedIds, 20) as $chunk) {
            $detail = $this->getItemDetails($chunk, $token);
            if ($detail['code'] === 200 && is_array($detail['body'])) {
                foreach ($detail['body'] as $entry) {
                    $obj = $entry->body ?? $entry;
                    if (isset($obj->id)) {
                        $sku = MlApiHelper::extractSku($obj);
                        if ($sku) {
                            $skuMap[$sku] = $obj->id;
                        }
                    }
                }
            }
        }
        return $skuMap;
    }

    public function fetchPaginatedStoreProducts($mlUserId, $token, $page, $limit, $searchQuery) {
        $offset = ($page - 1) * $limit;
        $search = $this->searchItems($mlUserId, $token, $limit, $offset, $searchQuery);

        if ($search['code'] !== 200 || !$search['body']) {
            $errMsg = $search['body']->message ?? $search['body']->error ?? 'Error al obtener productos.';
            return ['error' => $errMsg];
        }

        $itemIds = $search['body']->results ?? [];
        $total = $search['body']->paging->total ?? 0;
        $items = [];

        if (!empty($itemIds)) {
            $detail = $this->getItemDetails($itemIds, $token);
            if ($detail['code'] === 200 && is_array($detail['body'])) {
                foreach ($detail['body'] as $entry) {
                    $obj = $entry->body ?? $entry;
                    if (isset($obj->id)) {
                        $items[] = $obj;
                    }
                }
            }
        }

        $result = [];
        foreach ($items as $item) {
            $sku = MlApiHelper::extractSku($item);
            $result[] = [
                'id' => $item->id ?? '',
                'title' => $item->title ?? '',
                'price' => (float) ($item->price ?? 0),
                'currency_id' => $item->currency_id ?? '',
                'available_quantity' => (int) ($item->available_quantity ?? 0),
                'condition' => $item->condition ?? '',
                'listing_type_id' => $item->listing_type_id ?? '',
                'thumbnail' => $item->thumbnail ?? '',
                'permalink' => $item->permalink ?? '',
                'sku' => $sku,
                'status' => $item->status ?? '',
            ];
        }

        return [
            'items' => $result,
            'paging' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => max(1, (int) ceil($total / $limit)),
                'offset' => $offset,
            ]
        ];
    }
}
