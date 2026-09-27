<?php


class AuditService {
    private $api;

    public function __construct($db = null) {
        $this->api = new MercadoLibreApiService($db);
    }

    public function readFile($tmpFile, $ext) {
        $rows = [];
        if ($ext === 'xlsx') {
            $rows = XlsxReader::read($tmpFile);
        } elseif ($ext === 'csv') {
            $handle = fopen($tmpFile, 'r');
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        } else {
            throw new Exception('Formato no soportado. Usa .xlsx o .csv.');
        }
        return $rows;
    }

    public function detectHeaderRow($rows, $regex) {
        if (!empty($rows) && preg_match($regex, $rows[0][0] ?? '')) {
            return 1;
        }
        return 0;
    }

    public function parseEntries($type, $rows, $startRow, $skuCol = 0, $valCol = 1, $mlIdCol = null) {
        $entries = [];

        if ($type === 'prices') {
            $parsePrice = function($raw) {
                $v = trim((string) $raw);
                $v = str_replace('$', '', $v);
                $v = str_replace(' ', '', $v);
                if (strpos($v, ',') !== false) {
                    $v = str_replace('.', '', $v);
                    $v = str_replace(',', '.', $v);
                } elseif (substr_count($v, '.') > 1) {
                    $v = str_replace('.', '', $v);
                } elseif (substr_count($v, '.') === 1) {
                    if (preg_match('/\.\d{3}$/', $v)) {
                        $v = str_replace('.', '', $v);
                    }
                }
                return (float) $v;
            };
            for ($i = $startRow; $i < count($rows); $i++) {
                $sku = trim((string) ($rows[$i][$skuCol] ?? ''));
                $priceRaw = trim((string) ($rows[$i][$valCol] ?? ''));
                $mlId = $mlIdCol !== null ? trim((string) ($rows[$i][$mlIdCol] ?? '')) : '';
                if ($sku === '' && $priceRaw === '') continue;
                if ($sku === '') continue;
                if ($priceRaw === '') continue;
                $testVal = str_replace('$', '', str_replace(' ', '', trim($priceRaw)));
                if ($testVal === '') continue;
                $numericCheck = $testVal;
                if (strpos($numericCheck, ',') !== false) {
                    $numericCheck = str_replace('.', '', $numericCheck);
                    $numericCheck = str_replace(',', '.', $numericCheck);
                } elseif (substr_count($numericCheck, '.') > 1) {
                    $numericCheck = str_replace('.', '', $numericCheck);
                }
                if (!is_numeric($numericCheck)) continue;
                $entries[] = ['sku' => $sku, 'price' => $parsePrice($priceRaw), 'ml_id' => $mlId];
            }
        } elseif ($type === 'stock') {
            $parseStock = function($raw) {
                $v = trim((string) $raw);
                $v = str_replace('$', '', $v);
                $v = str_replace(' ', '', $v);
                if (strpos($v, ',') !== false) {
                    $v = str_replace('.', '', $v);
                    $v = str_replace(',', '.', $v);
                } elseif (substr_count($v, '.') > 1) {
                    $v = str_replace('.', '', $v);
                } elseif (substr_count($v, '.') === 1) {
                    if (preg_match('/\.\d{3}$/', $v)) {
                        $v = str_replace('.', '', $v);
                    }
                }
                return (int) (float) $v;
            };
            for ($i = $startRow; $i < count($rows); $i++) {
                $sku = trim((string) ($rows[$i][$skuCol] ?? ''));
                $stockRaw = trim((string) ($rows[$i][$valCol] ?? ''));
                $mlId = $mlIdCol !== null ? trim((string) ($rows[$i][$mlIdCol] ?? '')) : '';
                if ($sku === '' && $stockRaw === '') continue;
                if ($sku === '') continue;
                if ($stockRaw === '') continue;
                $testVal = str_replace('$', '', str_replace(' ', '', trim($stockRaw)));
                if ($testVal === '') continue;
                $numericCheck = $testVal;
                if (strpos($numericCheck, ',') !== false) {
                    $numericCheck = str_replace('.', '', $numericCheck);
                    $numericCheck = str_replace(',', '.', $numericCheck);
                } elseif (substr_count($numericCheck, '.') > 1) {
                    $numericCheck = str_replace('.', '', $numericCheck);
                }
                if (!is_numeric($numericCheck)) continue;
                $entries[] = ['sku' => $sku, 'stock' => $parseStock($stockRaw), 'ml_id' => $mlId];
            }
        }

        return $entries;
    }

    public function buildSkuMap($type, $scannedIds, $token) {
        $skuMap = [];
        foreach (array_chunk($scannedIds, 20) as $chunk) {
            $detail = $this->api->getItemDetails($chunk, $token);
            if ($detail['code'] === 200 && is_array($detail['body'])) {
                foreach ($detail['body'] as $entry) {
                    $obj = $entry->body ?? $entry;
                    if (!isset($obj->id)) continue;

                    $skus = [];
                    $parentSku = MlApiHelper::extractSku($obj);
                    if ($parentSku) $skus[] = $parentSku;

                    if (!empty($obj->variations)) {
                        foreach ($obj->variations as $variation) {
                            $vAttrs = $variation->attributes ?? $variation->attribute_combinations ?? [];
                            foreach ($vAttrs as $attr) {
                                if (in_array($attr->id ?? '', ['SELLER_SKU', 'SELLER_SKU_', 'SKU', 'SELLER_SKU_OTH', 'MODEL'])) {
                                    $vSku = trim($attr->value_name ?? $attr->name ?? '');
                                    if ($vSku && !in_array($vSku, $skus)) {
                                        $skus[] = $vSku;
                                    }
                                }
                            }
                        }
                    }

                    $entryData = $this->buildEntry($type, $obj);
                    foreach ($skus as $sku) {
                        $skuMap[$sku] = $entryData;
                    }
                }
            }
        }
        return $skuMap;
    }

    public function buildEntry($type, $obj) {
        switch ($type) {
            case 'prices':
                return [
                    'item_id' => $obj->id,
                    'ml_price' => $obj->price ?? 0,
                    'status' => $obj->status ?? '',
                ];
            case 'stock':
                return [
                    'item_id' => $obj->id,
                    'ml_stock' => $obj->available_quantity ?? 0,
                    'status' => $obj->status ?? '',
                ];
        }
    }

    public function compare($type, $entries, $skuMap) {
        $results = [];

        if ($type === 'prices') {
            foreach ($entries as $entry) {
                $sku = $entry['sku'];
                $uploadedPrice = $entry['price'];
                if (!isset($skuMap[$sku])) {
                    $results[] = ['sku' => $sku, 'uploaded_price' => $uploadedPrice, 'ml_price' => null, 'item_id' => '-', 'item_status' => '-', 'diff_amount' => null, 'diff_percent' => null, 'result' => 'Diferencia'];
                    continue;
                }
                $info = $skuMap[$sku];
                $mlPrice = (float) $info['ml_price'];
                $diff = $uploadedPrice - $mlPrice;
                $diffPercent = ($mlPrice > 0) ? round(($diff / $mlPrice) * 100, 2) : 0;
                $results[] = [
                    'sku' => $sku, 'uploaded_price' => $uploadedPrice, 'ml_price' => $mlPrice,
                    'item_id' => $info['item_id'], 'item_status' => $info['status'],
                    'diff_amount' => $diff, 'diff_percent' => $diffPercent,
                    'result' => ($diff == 0) ? 'Coincide' : 'Diferencia',
                ];
            }
        } elseif ($type === 'stock') {
            foreach ($entries as $entry) {
                $sku = $entry['sku'];
                $uploadedStock = $entry['stock'];
                if (!isset($skuMap[$sku])) {
                    $results[] = ['sku' => $sku, 'uploaded_stock' => $uploadedStock, 'ml_stock' => null, 'item_id' => '-', 'item_status' => '-', 'diff_amount' => null, 'result' => 'Diferencia'];
                    continue;
                }
                $info = $skuMap[$sku];
                $mlStock = (int) $info['ml_stock'];
                $diff = $uploadedStock - $mlStock;
                $results[] = [
                    'sku' => $sku, 'uploaded_stock' => $uploadedStock, 'ml_stock' => $mlStock,
                    'item_id' => $info['item_id'], 'item_status' => $info['status'],
                    'diff_amount' => $diff, 'result' => ($diff === 0) ? 'Coincide' : 'Diferencia',
                ];
            }
        }

        return $results;
    }

    public function fillXlsx($type, $xlsx, $results) {
        if ($type === 'prices') {
            $fmtPrice = function($v) {
                if ($v === null || $v === '') return '-';
                return '$' . number_format((float) $v, 0, ',', '.');
            };
            foreach ($results as $r) {
                $xlsx->addRow([
                    $r['sku'],
                    $r['item_id'],
                    $fmtPrice($r['uploaded_price']),
                    $fmtPrice($r['ml_price']),
                    $r['diff_amount'] !== null ? (($r['diff_amount'] >= 0 ? '+' : '') . '$' . number_format(abs((float) $r['diff_amount']), 0, ',', '.')) : '-',
                    $r['diff_percent'] !== null ? (($r['diff_percent'] >= 0 ? '+' : '') . number_format((float) $r['diff_percent'], 1, ',', '.') . '%') : '-',
                    $r['result'],
                ]);
            }
        } elseif ($type === 'stock') {
            foreach ($results as $r) {
                $xlsx->addRow([
                    $r['sku'], $r['item_id'],
                    $r['uploaded_stock'], $r['ml_stock'] !== null ? $r['ml_stock'] : '-',
                    $r['diff_amount'] !== null ? $r['diff_amount'] : '-',
                    $r['result'],
                ]);
            }
        }
    }
}
