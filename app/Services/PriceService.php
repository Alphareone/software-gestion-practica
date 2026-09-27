<?php


class PriceService {
    private $api;
    private $log;
    private $batch;
    private $setting;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->api = new MercadoLibreApiService($db);
        $this->log = new PriceLogModel($db);
        $this->batch = new PriceBatchModel($db);
        $this->setting = new AppSettingModel($db);
    }

    public function parsePrice($raw) {
        $v = trim((string) $raw);
        $v = str_replace('$', '', $v);
        $v = str_replace(' ', '', $v);
        if (strpos($v, ',') !== false) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace('.', '', $v);
        }
        return (float) $v;
    }

    public function readRows($filePath, $ext) {
        $rows = [];
        $maxRows = 5000;

        if ($ext === 'xlsx') {
            $rows = XlsxReader::read($filePath);
        } elseif ($ext === 'csv') {
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new Exception('No se pudo abrir el archivo CSV.');
            }
            $rowCount = 0;
            while (($data = fgetcsv($handle)) !== false && $rowCount < $maxRows) {
                $rows[] = $data;
                $rowCount++;
            }
            fclose($handle);
            if ($rowCount >= $maxRows) {
                throw new Exception('Archivo CSV excede el límite de ' . $maxRows . ' filas.');
            }
        } else {
            throw new Exception('Formato no soportado. Usa .xlsx o .csv.');
        }
        return $rows;
    }

    public function parseEntries($rows) {
        $colSku = 'Columna A';
        $colPrice = 'Columna B';
        $startRow = 0;
        if (!empty($rows) && preg_match('/sku|código|codigo|item|id|precio|price/i', $rows[0][0] ?? '')) {
            $colSku = trim($rows[0][0]);
            $startRow = 1;
        }
        if (!empty($rows) && isset($rows[0][1]) && preg_match('/precio|price|valor|importe|new/i', $rows[0][1])) {
            $colPrice = trim($rows[0][1]);
        }
        $entries = [];
        $validationErrors = [];
        for ($i = $startRow; $i < count($rows); $i++) {
            $sku = trim((string) ($rows[$i][0] ?? ''));
            $priceRaw = trim((string) ($rows[$i][1] ?? ''));
            if ($sku === '' && $priceRaw === '') continue;
            if ($sku === '') {
                $validationErrors[] = 'Fila ' . ($i + 1) . ': SKU vacío.';
                continue;
            }
            if ($priceRaw === '' || !is_numeric(str_replace(['.', ','], ['', '.'], str_replace('$', '', $priceRaw)))) {
                $validationErrors[] = 'Fila ' . ($i + 1) . ': Precio inválido "' . htmlspecialchars($priceRaw) . '".';
                continue;
            }
            $entries[] = ['sku' => $sku, 'price' => $this->parsePrice($priceRaw)];
        }
        return ['entries' => $entries, 'validation_errors' => $validationErrors, 'col_sku' => $colSku, 'col_price' => $colPrice];
    }

    public function processItems(array $entries, array $skuMap, $batchId, $mlConnectionId, $userId, $startIdx, $count, $token, $preDelayUs, $postDelayUs) {
        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $idx = $startIdx + $i;
            $entry = $entries[$idx];
            $itemId = $skuMap[$entry['sku']] ?? null;

            if (!$itemId) {
                $r = ['sku' => $entry['sku'], 'item_id' => '-', 'old_price' => '-', 'new_price' => $entry['price'], 'status' => 'error', 'message' => 'SKU no encontrado en la tienda.', 'diff_amount' => '-', 'diff_percent' => '-', 'item_status' => ''];
                $results[] = $r;
                $this->logChange($userId, $mlConnectionId, $batchId, $r, null);
                continue;
            }

            $itemDetail = $this->api->callWithRetry('/items/' . $itemId, 'GET', null, $token);
            $oldPrice = ($itemDetail['code'] === 200 && $itemDetail['body']) ? ($itemDetail['body']->price ?? '?') : '?';
            $itemStatus = ($itemDetail['code'] === 200 && $itemDetail['body']) ? ($itemDetail['body']->status ?? '') : '';

            if ($itemDetail['code'] !== 200 || !$itemDetail['body']) {
                $errMsg = 'No se pudo obtener la publicación (' . $itemId . ').';
                $r = ['sku' => $entry['sku'], 'item_id' => $itemId, 'old_price' => '-', 'new_price' => $entry['price'], 'status' => 'error', 'message' => $errMsg, 'diff_amount' => '-', 'diff_percent' => '-', 'item_status' => ''];
                $results[] = $r;
                $this->logChange($userId, $mlConnectionId, $batchId, $r, null);
                continue;
            }

            if ($itemStatus === 'closed') {
                $r = ['sku' => $entry['sku'], 'item_id' => $itemId, 'old_price' => $oldPrice, 'new_price' => $entry['price'], 'status' => 'error', 'message' => 'Publicación finalizada — no se puede actualizar (' . $itemId . ')', 'diff_amount' => '-', 'diff_percent' => '-', 'item_status' => $itemStatus];
                $results[] = $r;
                $this->logChange($userId, $mlConnectionId, $batchId, $r, $oldPrice);
                continue;
            }

            $isUnderReview = $itemStatus === 'under_review';

            usleep($preDelayUs);

            $updateResult = $this->api->callWithRetry('/items/' . $itemId, 'PUT', json_encode(['price' => $entry['price']]), $token);

            if ($updateResult['code'] === 200) {
                $diffAmount = $entry['price'] - (float) ($oldPrice === '-' || $oldPrice === '?' ? 0 : $oldPrice);
                $diffPercent = ($oldPrice !== '-' && $oldPrice !== '?' && (float) $oldPrice > 0) ? round(($diffAmount / (float) $oldPrice) * 100, 2) : 0;
                $msg = $isUnderReview ? 'OK — bajo revisión' : 'OK';
                $r = ['sku' => $entry['sku'], 'item_id' => $itemId, 'old_price' => $oldPrice, 'new_price' => $entry['price'], 'status' => 'success', 'message' => $msg, 'diff_amount' => $diffAmount, 'diff_percent' => $diffPercent, 'item_status' => $itemStatus];
            } else {
                $errMsg = $updateResult['body']->message ?? $updateResult['body']->error ?? 'Error desconocido.';
                if ($isUnderReview) {
                    $errMsg = 'Inactiva — bajo revisión sin permisos de modificación (' . $itemId . ')';
                    $itemStatus = 'inactive_review';
                } elseif (stripos($errMsg, 'closed') !== false || stripos($errMsg, 'finalizada') !== false) {
                    $errMsg = 'No se puede actualizar: publicación finalizada (' . $itemId . ')';
                } elseif (stripos($errMsg, 'paused') !== false || stripos($errMsg, 'pausada') !== false) {
                    $errMsg = 'No se puede actualizar: publicación pausada (' . $itemId . ')';
                }
                $r = ['sku' => $entry['sku'], 'item_id' => $itemId, 'old_price' => $oldPrice, 'new_price' => $entry['price'], 'status' => 'error', 'message' => $errMsg, 'diff_amount' => '-', 'diff_percent' => '-', 'item_status' => $itemStatus];
            }
            $results[] = $r;
            $this->logChange($userId, $mlConnectionId, $batchId, $r, $oldPrice);

            usleep($postDelayUs);
        }
        return $results;
    }

    public function logChange($userId, $mlConnectionId, $batchId, $result, $oldPriceRaw) {
        $oldVal = null;
        if ($oldPriceRaw !== null && $oldPriceRaw !== '-' && $oldPriceRaw !== '?') {
            $oldVal = (float) $oldPriceRaw;
        }
        $newVal = (float) $result['new_price'];
        $diffAmount = null;
        $diffPercent = null;
        if ($oldVal !== null && $result['status'] === 'success') {
            $diffAmount = $newVal - $oldVal;
            $diffPercent = $oldVal > 0 ? round(($diffAmount / $oldVal) * 100, 2) : 0;
        }
        $storeName = '';
        if ($mlConnectionId) {
            $s = $this->db->query('SELECT store_name FROM ml_connections WHERE id = ?')->execute([$mlConnectionId])->fetch();
            $storeName = $s ? $s->store_name : '';
        }
        $this->log->insert(
            $userId, $mlConnectionId, $storeName, $batchId,
            $result['sku'], $result['item_id'], $result['item_status'] ?? null,
            $oldVal, $newVal, $diffAmount, $diffPercent,
            $result['status'], $result['message']
        );
    }

    public function saveBatchToDb($userId, $batch) {
        $this->batch->save($userId, $batch);
    }

    public function restoreBatchFromDb($userId) {
        return $this->batch->findPending($userId);
    }

    public function deleteBatchFromDb($userId) {
        $this->batch->delete($userId);
    }

    public function getSetting($key, $default) {
        return $this->setting->get($key, $default);
    }

    public function fetchHubData($userId, $isAdmin, $activeStoreId) {
        $logWhere = $isAdmin ? '1=1' : 'l.user_id = ?';
        $logParams = $isAdmin ? [] : [$userId];

        $syncedProducts = 0;

        $stats = $this->log->getSummaryStats($logWhere, $logParams);
        $successRate = $stats['total_items'] > 0 ? round(($stats['total_ok'] / $stats['total_items']) * 100) : null;

        $executionsMonth = $this->log->countExecutionsMonth($logWhere, $logParams);
        $recentBatches = $this->log->getRecentBatches($logWhere, $logParams);

        return [
            'metrics' => [
                'synced_products' => $syncedProducts,
                'last_price_update' => $stats['last_update'],
                'success_rate' => $successRate,
                'executions_month' => $executionsMonth,
                'total_batches' => $stats['total_batches'],
                'total_items' => $stats['total_items'],
                'total_ok' => $stats['total_ok'],
            ],
            'recent_batches' => $recentBatches,
            'last_batch' => $recentBatches[0] ?? null,
        ];
    }

    public function fetchHistory($userId, $isAdmin, $page, $perPage, $filterBatch, $filterFrom, $filterTo) {
        $where = $isAdmin ? '1=1' : 'l.user_id = ?';
        $params = $isAdmin ? [] : [$userId];

        if ($filterBatch) {
            $where .= ' AND l.batch_id = ?';
            $params[] = $filterBatch;
        }
        if ($filterFrom) {
            $where .= ' AND l.created_at >= ?';
            $params[] = $filterFrom . ' 00:00:00';
        }
        if ($filterTo) {
            $where .= ' AND l.created_at <= ?';
            $params[] = $filterTo . ' 23:59:59';
        }

        $batchesResult = $this->log->findBatches($where, $params, $page, $perPage);
        $totalBatches = $batchesResult['total_batches'];
        $batchIds = $batchesResult['batch_ids'];

        $allLogs = $this->log->findByBatchIds($batchIds);

        $batches = [];
        foreach ($allLogs as $log) {
            $bid = $log->batch_id;
            if (!isset($batches[$bid])) {
                $batches[$bid] = [
                    'batch_id' => $bid,
                    'ml_connection_id' => $log->ml_connection_id,
                    'store_name' => $log->store_name ?? '',
                    'username' => $log->username ?? '',
                    'started_at' => $log->created_at,
                    'finished_at' => $log->created_at,
                    'total' => 0,
                    'success_count' => 0,
                    'error_count' => 0,
                    'logs' => [],
                ];
            }
            $batches[$bid]['total']++;
            if ($log->status === 'success') {
                $batches[$bid]['success_count']++;
            } else {
                $batches[$bid]['error_count']++;
            }
            $batches[$bid]['logs'][] = $log;
            $batches[$bid]['finished_at'] = $log->created_at;
        }

        $ordered = [];
        foreach ($batchIds as $bid) {
            if (isset($batches[$bid])) {
                $ordered[] = $batches[$bid];
            }
        }

        $stats = $this->log->findBatchStats($where, $params);

        return [
            'batches' => $ordered,
            'stats' => $stats,
            'total_batches' => $totalBatches,
            'per_page' => $perPage,
        ];
    }

    public function fetchBatchLogs($batchId, $userId, $isAdmin) {
        return $this->log->findByBatch($batchId, $userId, $isAdmin);
    }
}
