<?php

/**
 * Servicio de actualización masiva de precios y stock en Cencosud Chile.
 *
 * Flujo:
 *   Archivo Excel/CSV → Parseo y Validación → Creación de lote reanudable (cencosud_update_batches)
 *   → Envío de peticiones en bloques (chunks) con retrasos → Registro de historial (cencosud_update_logs).
 */
class CencosudUpdateService {

    private const MAX_ROWS = 5000;

    private $db;
    private $api;
    private $cache;
    private $batch;
    private $log;
    private $connection;

    public function __construct($db) {
        $this->db = $db;
        $this->api = new CencosudApiService($db);
        $this->cache = new CencosudProductsCacheModel($db);
        $this->batch = new CencosudBatchModel($db);
        $this->log = new CencosudUpdateLogModel($db);
        $this->connection = new CencosudConnectionModel($db);
    }

    // ── Parseo y lectura de archivos ──

    /**
     * Limpia y formatea un string a un número de precio flotante.
     * Ejemplo: "$12.990" o "12,990" → 12990.00
     */
    public function parsePrice($raw) {
        $v = trim((string) $raw);
        $v = str_replace(['$', ' '], '', $v);
        
        // Si tiene punto y coma (ej. 1.234,56 o 1,234.56)
        if (strpos($v, '.') !== false && strpos($v, ',') !== false) {
            $lastDot = strrpos($v, '.');
            $lastComma = strrpos($v, ',');
            if ($lastDot > $lastComma) { // 1,234.56
                $v = str_replace(',', '', $v);
            } else { // 1.234,56
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            }
        } else {
            // Solo tiene uno de los dos. Si está seguido exactamente por 3 dígitos al final, 
            // asumimos que es un separador de miles (ej. 12.990 o 12,990)
            if (preg_match('/[.,]\d{3}$/', $v)) {
                $v = str_replace(['.', ','], '', $v);
            } else {
                // De lo contrario, lo tratamos como decimal
                $v = str_replace(',', '.', $v);
            }
        }
        return (float) $v;
    }

    /**
     * Lee las filas de un archivo XLSX o CSV.
     */
    public function readRows($filePath, $ext) {
        $rows = [];

        if ($ext === 'xlsx') {
            // XlsxReader es un helper global del sistema que carga PhpSpreadsheet de forma óptima
            $rows = XlsxReader::read($filePath);
            if (count($rows) > self::MAX_ROWS) {
                throw new Exception('El archivo excede el límite permitido de ' . self::MAX_ROWS . ' filas.');
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
                throw new Exception('El archivo CSV excede el límite permitido de ' . self::MAX_ROWS . ' filas.');
            }
        } else {
            throw new Exception('Formato de archivo no soportado. Debe usar .xlsx o .csv.');
        }
        return $rows;
    }

    /**
     * Valida y procesa las filas crudas del archivo.
     * Retorna ['entries' => array, 'validation_errors' => array].
     */
    public function parseEntries($rows, $type) {
        $startRow = 0;
        // Detectar si la primera fila es una cabecera para ignorarla
        if (!empty($rows) && preg_match('/sku|código|codigo|item|id|precio|price|stock|cantidad|qty/i', (string) ($rows[0][0] ?? ''))) {
            $startRow = 1;
        }

        $entries = [];
        $seen = [];
        $validationErrors = [];

        for ($i = $startRow; $i < count($rows); $i++) {
            $sku = trim((string) ($rows[$i][0] ?? ''));
            $valueRaw = trim((string) ($rows[$i][1] ?? ''));
            
            if ($sku === '' && $valueRaw === '') continue; // Fila vacía
            
            if ($sku === '') {
                $validationErrors[] = 'Fila ' . ($i + 1) . ': SKU está vacío.';
                continue;
            }
            if ($valueRaw === '') {
                $validationErrors[] = 'Fila ' . ($i + 1) . ': el valor está vacío.';
                continue;
            }

            if ($type === 'stock') {
                $clean = str_replace(['.', ',', ' '], '', $valueRaw);
                if (!ctype_digit($clean)) {
                    $validationErrors[] = 'Fila ' . ($i + 1) . ': stock inválido "' . htmlspecialchars($valueRaw) . '" (debe ser entero ≥ 0).';
                    continue;
                }
                $value = (int) $clean;
            } else {
                // Validación de precios
                if (!is_numeric(str_replace(['.', ','], ['', '.'], str_replace(['$', ' '], '', $valueRaw)))) {
                    $validationErrors[] = 'Fila ' . ($i + 1) . ': precio inválido "' . htmlspecialchars($valueRaw) . '".';
                    continue;
                }
                $value = $this->parsePrice($valueRaw);
                if ($value <= 0) {
                    $validationErrors[] = 'Fila ' . ($i + 1) . ': el precio debe ser mayor que 0.';
                    continue;
                }
                if ($value < 500) {
                    $validationErrors[] = 'Fila ' . ($i + 1) . ': el precio ($' . number_format($value, 0, ',', '.') . ' CLP) es inferior al piso mínimo de seguridad ($500 CLP).';
                    continue;
                }
            }

            // Manejo de SKUs duplicados en el mismo archivo (prevalece el último)
            if (isset($seen[$sku])) {
                $validationErrors[] = 'Fila ' . ($i + 1) . ': SKU duplicado "' . htmlspecialchars($sku) . '" — se utilizará el último valor ingresado.';
                $entries[$seen[$sku]]['value'] = $value;
                continue;
            }

            $seen[$sku] = count($entries);
            $entries[] = ['sku' => $sku, 'value' => $value];
        }

        return ['entries' => $entries, 'validation_errors' => $validationErrors];
    }

    // ── Validaciones y Reglas de Negocio ──

    /**
     * Valida reglas de negocio para los precios (Piso mínimo y Riesgo SERNAC).
     * @throws InvalidArgumentException
     */
    public function validatePrice($sku, $newValue, $masterPrice = null) {
        if ($newValue < 500) {
            throw new InvalidArgumentException("El precio (\$" . number_format($newValue, 0, ',', '.') . " CLP) está por debajo del límite mínimo permitido (\$500 CLP).");
        }
        if ($masterPrice !== null && $masterPrice > 0) {
            if ($newValue < ($masterPrice * 0.30)) {
                throw new InvalidArgumentException("El precio representa más del 70% de descuento sobre el precio maestro.");
            }
        }
        return true;
    }

    /**
     * Aplica la regla de stock crítico. Si el stock es <= 5, devuelve 0.
     */
    public function calculateEffectiveStock($stock) {
        $qty = (int) $stock;
        if ($qty <= 5 && $qty > 0) {
            return 0;
        }
        return $qty;
    }

    // ── Gestión de Lotes (Batches) ──

    public function createBatch($userId, $connectionId, $type, array $entries) {
        $batch = [
            'batch_id' => bin2hex(random_bytes(8)),
            'cencosud_connection_id' => (int) $connectionId,
            'update_type' => $type,
            'entries' => $entries,
            'total' => count($entries),
            'processed' => 0,
            'ok' => 0,
            'errors' => 0,
        ];
        $this->batch->delete($userId); // Limpiar lote pendiente anterior del usuario
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
     * Procesa los siguientes $count productos del lote pendiente contra la API de Cencosud.
     */
    public function processNextChunk($userId, array $batch, $count = 10, $preDelayUs = 200000, $postDelayUs = 300000) {
        $connectionId = (int) $batch['cencosud_connection_id'];
        $type = $batch['update_type'];
        $conn = $this->connection->findById($connectionId);
        
        if (!$conn || !$conn->is_active) {
            return [
                'done' => true,
                'error' => 'La conexión a Cencosud no existe o está inactiva.',
                'processed' => $batch['processed'],
                'total' => $batch['total'],
                'ok' => $batch['ok'],
                'errors' => $batch['errors'],
                'results' => []
            ];
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
            $r = [
                'sku' => $sku,
                'old_value' => null,
                'new_value' => $newValue,
                'status' => 'error',
                'message' => 'SKU no encontrado en caché local. Sincroniza el catálogo primero.'
            ];
            $this->logResult($userId, $connectionId, $storeName, $batchId, $type, $r);
            return $r;
        }

        $oldValue = $type === 'price'
            ? (float) $cached->price
            : ($cached->available_quantity !== null ? (int) $cached->available_quantity : null);

        usleep($preDelayUs); // Pausa previa (antishock de rate limits)

        if ($type === 'price') {
            // 1. Piso Mínimo de Seguridad ($500 CLP)
            if ($newValue < 500) {
                try {
                    $notif = new NotificationService($this->db);
                    $notif->createNotification(
                        $userId,
                        'price_alert',
                        'Precio menor al piso mínimo — ' . $storeName . ' (SKU: ' . $sku . ')',
                        "Se rechazó la actualización del precio ($" . number_format($newValue, 0, ',', '.') . " CLP) por estar debajo del piso mínimo de seguridad ($500 CLP)."
                    );
                } catch (\Exception $e) {}

                $r = [
                    'sku' => $sku,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'status' => 'error',
                    'message' => 'Rechazado por Seguridad: El precio ($' . number_format($newValue, 0, ',', '.') . ' CLP) está por debajo del límite mínimo permitido ($500 CLP).'
                ];
                $this->logResult($userId, $connectionId, $storeName, $batchId, $type, $r);
                return $r;
            }

            // 2. Detección de Caída Drástica (> 70% Descuento respecto a Precio Maestro)
            try {
                $masterStmt = $this->db->prepare("SELECT price FROM products_master WHERE sku = ?");
                $masterStmt->execute([$sku]);
                $mProduct = $masterStmt->fetch(PDO::FETCH_ASSOC);
                if ($mProduct && !empty($mProduct['price']) && (float)$mProduct['price'] > 0) {
                    $masterPrice = (float)$mProduct['price'];
                    if ($newValue < ($masterPrice * 0.30)) { // Descuento superior al 70%
                        $pctDrop = round((1 - ($newValue / $masterPrice)) * 100);
                        try {
                            $notif = new NotificationService($this->db);
                            $notif->createNotification(
                                $userId,
                                'price_warning',
                                'Caída drástica de precio (' . $pctDrop . '% desc.) — ' . $storeName . ' (SKU: ' . $sku . ')',
                                "Se bloqueó la actualización a $" . number_format($newValue, 0, ',', '.') . " CLP por representar un {$pctDrop}% de descuento respecto al precio maestro ($" . number_format($masterPrice, 0, ',', '.') . " CLP) — Riesgo SERNAC."
                            );
                        } catch (\Exception $e) {}

                        $r = [
                            'sku' => $sku,
                            'old_value' => $oldValue,
                            'new_value' => $newValue,
                            'status' => 'error',
                            'message' => 'Bloqueado por Riesgo SERNAC: El precio ($' . number_format($newValue, 0, ',', '.') . ' CLP) representa más del 70% de descuento sobre el precio maestro ($' . number_format($masterPrice, 0, ',', '.') . ' CLP).'
                        ];
                        $this->logResult($userId, $connectionId, $storeName, $batchId, $type, $r);
                        return $r;
                    }
                }
            } catch (\Exception $e) {}

            $resp = $this->api->updatePrice($connectionId, $sku, $newValue, $cached->currency ?: 'CLP');
        } else {
            $qty = (int) $newValue;
            $originalQty = $qty;
            if ($qty <= 5 && $qty > 0) {
                $qty = 0;
                $newValue = 0;
            }
            $resp = $this->api->updateInventory($connectionId, $sku, $qty);

            if ($originalQty <= 5 && $originalQty > 0 && $resp['code'] === 200) {
                try {
                    $notif = new NotificationService($this->db);
                    $notif->createNotification(
                        $userId,
                        'low_stock',
                        'No queda stock — ' . $storeName . ' (SKU: ' . $sku . ')',
                        "Subiste un stock de {$originalQty} unidades. Se ha establecido automáticamente a 0 para evitar ventas sin stock (límite de seguridad ≤ 5)."
                    );
                } catch (Exception $e) {}
            }
        }

        usleep($postDelayUs); // Pausa posterior

        if ($resp['code'] === 200) {
            // Actualizar el caché local en caso de éxito
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
            $r = [
                'sku' => $sku,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'status' => 'error',
                'message' => is_string($errMsg) ? $errMsg : json_encode($errMsg)
            ];
        }

        $this->logResult($userId, $connectionId, $storeName, $batchId, $type, $r);
        return $r;
    }

    private function logResult($userId, $connectionId, $storeName, $batchId, $type, array $r) {
        $old = $r['old_value'];
        $new = (float) $r['new_value'];
        $diffAmount = null;
        $diffPercent = null;
        
        // Calcular diferencias numéricas y porcentajes de cambio (solo para actualizaciones exitosas)
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

    // ── Historial de Consultas ──

    public function fetchHistory($userId, $isAdmin, $page = 1, $perPage = 10, $filterType = '') {
        return $this->log->findBatches($userId, $isAdmin, $page, $perPage, $filterType);
    }

    public function fetchBatchLogs($batchId, $userId, $isAdmin) {
        return $this->log->findByBatch($batchId, $userId, $isAdmin);
    }
}
