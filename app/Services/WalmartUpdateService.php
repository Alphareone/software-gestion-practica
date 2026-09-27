<?php

/**
 * Actualización masiva de precios y stock en Walmart Chile desde XLSX/CSV.
 *
 * Pipeline (mismo patrón que PriceService de ML, pero con escrituras
 * habilitadas): archivo → parseo/validación → batch persistido en
 * walmart_update_batches (reanudable) → PUT por SKU en chunks con delays →
 * historial en walmart_update_logs.
 *
 * El archivo trae 2 columnas: SKU + valor (precio en CLP o stock entero).
 */
class WalmartUpdateService {

    private const MAX_ROWS = 5000;

    private $db;
    private $api;
    private $cache;
    private $batch;
    private $log;
    private $connection;

    public function __construct($db) {
        $this->db = $db;
        $this->api = new WalmartApiService($db);
        $this->cache = new WalmartProductsCacheModel($db);
        $this->batch = new WalmartBatchModel($db);
        $this->log = new WalmartUpdateLogModel($db);
        $this->connection = new WalmartConnectionModel($db);
    }

    // ── Parseo de archivo ──

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

        if ($ext === 'xlsx') {
            $rows = XlsxReader::read($filePath);
            if (count($rows) > self::MAX_ROWS) {
                throw new Exception('Archivo excede el límite de ' . self::MAX_ROWS . ' filas.');
            }
        } elseif ($ext === 'csv') {
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new Exception('No se pudo abrir el archivo CSV.');
            }
            $rowCount = 0;
            while (($data = fgetcsv($handle)) !== false && $rowCount < self::MAX_ROWS) {
                $rows[] = $data;
                $rowCount++;
            }
            fclose($handle);
            if ($rowCount >= self::MAX_ROWS) {
                throw new Exception('Archivo CSV excede el límite de ' . self::MAX_ROWS . ' filas.');
            }
        } else {
            throw new Exception('Formato no soportado. Usa .xlsx o .csv.');
        }
        return $rows;
    }

    /**
     * $type: 'price' | 'stock'.
     * Retorna ['entries' => [['sku','value'], ...], 'validation_errors' => [...]].
     */
    public function parseEntries($rows, $type) {
        $startRow = 0;
        if (!empty($rows) && preg_match('/sku|código|codigo|item|id|precio|price|stock|cantidad|qty/i', (string) ($rows[0][0] ?? ''))) {
            $startRow = 1;
        }

        $entries = [];
        $seen = [];
        $validationErrors = [];

        for ($i = $startRow; $i < count($rows); $i++) {
            $sku = trim((string) ($rows[$i][0] ?? ''));
            $valueRaw = trim((string) ($rows[$i][1] ?? ''));
            if ($sku === '' && $valueRaw === '') continue;
            if ($sku === '') {
                $validationErrors[] = 'Fila ' . ($i + 1) . ': SKU vacío.';
                continue;
            }
            if ($valueRaw === '') {
                $validationErrors[] = 'Fila ' . ($i + 1) . ': valor vacío.';
                continue;
            }

            if ($type === 'stock') {
                $clean = str_replace(['.', ',', ' '], '', $valueRaw);
                if (!ctype_digit($clean)) {
                    $validationErrors[] = 'Fila ' . ($i + 1) . ': stock inválido "' . htmlspecialchars($valueRaw) . '" (debe ser un entero ≥ 0).';
                    continue;
                }
                $value = (int) $clean;
            } else {
                if (!is_numeric(str_replace(['.', ','], ['', '.'], str_replace(['$', ' '], '', $valueRaw)))) {
                    $validationErrors[] = 'Fila ' . ($i + 1) . ': precio inválido "' . htmlspecialchars($valueRaw) . '".';
                    continue;
                }
                $value = $this->parsePrice($valueRaw);
                if ($value <= 0) {
                    $validationErrors[] = 'Fila ' . ($i + 1) . ': el precio debe ser mayor a 0.';
                    continue;
                }
            }

            if (isset($seen[$sku])) {
                $validationErrors[] = 'Fila ' . ($i + 1) . ': SKU duplicado "' . htmlspecialchars($sku) . '" — se usará este último valor.';
                $entries[$seen[$sku]]['value'] = $value;
                continue;
            }

            $seen[$sku] = count($entries);
            $entries[] = ['sku' => $sku, 'value' => $value];
        }

        return ['entries' => $entries, 'validation_errors' => $validationErrors];
    }

    // ── Batches ──

    public function createBatch($userId, $connectionId, $type, array $entries) {
        $batch = [
            'batch_id' => bin2hex(random_bytes(8)),
            'walmart_connection_id' => (int) $connectionId,
            'update_type' => $type,
            'entries' => $entries,
            'total' => count($entries),
            'processed' => 0,
            'ok' => 0,
            'errors' => 0,
        ];
        $this->batch->delete($userId); // solo un batch pendiente por usuario
        $this->batch->save($userId, $batch);
        return $batch;
    }

    public function restoreBatchFromDb($userId) {
        return $this->batch->findPending($userId);
    }

    public function deleteBatchFromDb($userId) {
        $this->batch->delete($userId);
    }

    /**
     * Procesa los siguientes $count ítems del batch contra la API.
     * Retorna ['done' => bool, 'processed', 'total', 'ok', 'errors', 'results' => [...]].
     */
    public function processNextChunk($userId, array $batch, $count = 10, $preDelayUs = 200000, $postDelayUs = 300000) {
        $connectionId = (int) $batch['walmart_connection_id'];
        $type = $batch['update_type'];
        $conn = $this->connection->findById($connectionId);
        if (!$conn || !$conn->is_active) {
            return ['done' => true, 'error' => 'La conexión Walmart no existe o fue desactivada.', 'processed' => $batch['processed'], 'total' => $batch['total'], 'ok' => $batch['ok'], 'errors' => $batch['errors'], 'results' => []];
        }
        $storeName = $conn->store_name;

        $results = [];
        $startIdx = (int) $batch['processed'];
        $end = min($startIdx + $count, $batch['total']);

        for ($idx = $startIdx; $idx < $end; $idx++) {
            $entry = $batch['entries'][$idx];
            $r = $this->processOne($userId, $connectionId, $storeName, $batch['batch_id'], $type, $entry, $preDelayUs, $postDelayUs);
            $results[] = $r;
            $batch['processed']++;
            if ($r['status'] === 'success') {
                $batch['ok']++;
            } else {
                $batch['errors']++;
            }
        }

        $this->batch->save($userId, $batch);
        $done = $batch['processed'] >= $batch['total'];

        return [
            'done' => $done,
            'processed' => $batch['processed'],
            'total' => $batch['total'],
            'ok' => $batch['ok'],
            'errors' => $batch['errors'],
            'results' => $results,
            'batch' => $batch,
        ];
    }

    private function processOne($userId, $connectionId, $storeName, $batchId, $type, array $entry, $preDelayUs, $postDelayUs) {
        $sku = $entry['sku'];
        $newValue = $entry['value'];

        $cached = $this->cache->findBySku($connectionId, $sku);
        if (!$cached) {
            $r = ['sku' => $sku, 'old_value' => null, 'new_value' => $newValue, 'status' => 'error', 'message' => 'SKU no encontrado en el catálogo sincronizado. Sincroniza la tienda primero.'];
            $this->logResult($userId, $connectionId, $storeName, $batchId, $type, $r);
            return $r;
        }

        $oldValue = $type === 'price'
            ? (float) $cached->price
            : ($cached->available_quantity !== null ? (int) $cached->available_quantity : null);

        usleep($preDelayUs);

        if ($type === 'price') {
            $resp = $this->api->updatePrice($connectionId, $sku, $newValue, $cached->currency ?: 'CLP');
        } else {
            $resp = $this->api->updateInventory($connectionId, $sku, (int) $newValue);
        }

        usleep($postDelayUs);

        if ($resp['code'] === 200) {
            if ($type === 'price') {
                $this->cache->updateLocalPrice($connectionId, $sku, $newValue);
            } else {
                $this->cache->updateLocalStock($connectionId, $sku, (int) $newValue);
            }
            $r = ['sku' => $sku, 'old_value' => $oldValue, 'new_value' => $newValue, 'status' => 'success', 'message' => 'OK'];
        } else {
            $errMsg = $resp['body']->errors->error[0]->description
                ?? $resp['body']->error->description
                ?? $resp['body']->message
                ?? ('Error HTTP ' . $resp['code']);
            $r = ['sku' => $sku, 'old_value' => $oldValue, 'new_value' => $newValue, 'status' => 'error', 'message' => is_string($errMsg) ? $errMsg : json_encode($errMsg)];
        }

        $this->logResult($userId, $connectionId, $storeName, $batchId, $type, $r);
        return $r;
    }

    private function logResult($userId, $connectionId, $storeName, $batchId, $type, array $r) {
        $old = $r['old_value'];
        $new = (float) $r['new_value'];
        $diffAmount = null;
        $diffPercent = null;
        if ($old !== null && $r['status'] === 'success') {
            $diffAmount = $new - (float) $old;
            $diffPercent = (float) $old > 0 ? round(($diffAmount / (float) $old) * 100, 2) : 0;
        }
        $this->log->insert(
            $userId, $connectionId, $storeName, $batchId, $type,
            $r['sku'], $old !== null ? (float) $old : null, $new,
            $diffAmount, $diffPercent, $r['status'], $r['message']
        );
    }

    // ── Historial ──

    public function fetchHistory($userId, $isAdmin, $page = 1, $perPage = 10, $filterType = '') {
        return $this->log->findBatches($userId, $isAdmin, $page, $perPage, $filterType);
    }

    public function fetchBatchLogs($batchId, $userId, $isAdmin) {
        return $this->log->findByBatch($batchId, $userId, $isAdmin);
    }
}
