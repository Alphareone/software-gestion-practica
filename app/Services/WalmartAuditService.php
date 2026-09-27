<?php

/**
 * Espejo funcional de AuditService.php, adaptado a Walmart.
 * Diferencia clave: compara contra walmart_products_cache (ya sincronizada
 * por WalmartSyncService), no contra la API en vivo — igual patrón que
 * MlController::runCompareAudit usa con ml_products_cache.
 */
class WalmartAuditService {

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

    private function normalizeNumber($raw) {
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
        return $v;
    }

    public function parseEntries($type, $rows, $startRow, $skuCol = 0, $valCol = 1) {
        $entries = [];
        for ($i = $startRow; $i < count($rows); $i++) {
            $sku = trim((string) ($rows[$i][$skuCol] ?? ''));
            $valRaw = trim((string) ($rows[$i][$valCol] ?? ''));
            if ($sku === '' || $valRaw === '') continue;

            $normalized = $this->normalizeNumber($valRaw);
            if ($normalized === '' || !is_numeric($normalized)) continue;

            $value = $type === 'price' ? (float) $normalized : (int) (float) $normalized;
            $entries[] = ['sku' => $sku, 'value' => $value];
        }
        return $entries;
    }

    /** Arma el mapa SKU→dato leyendo walmart_products_cache (rápido, sin llamadas a la API). */
    public function buildSkuMapFromCache($type, array $cacheRows) {
        $map = [];
        foreach ($cacheRows as $row) {
            $map[$row->sku] = [
                'wpid' => $row->wpid,
                'value' => $type === 'price' ? (float) $row->price : (int) $row->available_quantity,
                'status' => $row->published_status,
                'synced_at' => $row->synced_at,
            ];
        }
        return $map;
    }

    /**
     * @param SkuFamilyService|null $familyService Si se entrega junto a $shortcodeIndex,
     *        cuando un SKU subido no matchea exacto se intenta resolver por código corto
     *        (sin marca ni color) antes de darlo por no encontrado.
     */
    public function compare($type, $entries, $skuMap, $familyService = null, $shortcodeIndex = null) {
        $results = [];
        foreach ($entries as $entry) {
            $sku = $entry['sku'];
            $uploaded = $entry['value'];

            if (isset($skuMap[$sku])) {
                $info = $skuMap[$sku];
                $walmartValue = $info['value'];
                $diff = $uploaded - $walmartValue;
                $diffPercent = ($type === 'price' && $walmartValue > 0) ? round(($diff / $walmartValue) * 100, 2) : null;

                $results[] = [
                    'sku' => $sku, 'matched_sku' => $sku, 'match_method' => 'exact',
                    'uploaded_value' => $uploaded, 'walmart_value' => $walmartValue,
                    'wpid' => $info['wpid'], 'status' => $info['status'],
                    'diff_amount' => $diff, 'diff_percent' => $diffPercent,
                    'synced_at' => $info['synced_at'],
                    'result' => ($diff == 0) ? 'Coincide' : 'Diferencia',
                ];
                continue;
            }

            // No hubo match exacto de SKU. Si se entregó el índice de códigos cortos,
            // se intenta resolver el producto sin depender del color explícito.
            if ($familyService !== null && $shortcodeIndex !== null) {
                $match = $familyService->lookupShortcode($sku, $shortcodeIndex);

                if ($match === 'ambiguous') {
                    $results[] = [
                        'sku' => $sku, 'matched_sku' => '-', 'match_method' => 'ambiguous',
                        'uploaded_value' => $uploaded, 'walmart_value' => null,
                        'wpid' => '-', 'status' => '-', 'diff_amount' => null, 'diff_percent' => null,
                        'synced_at' => null, 'result' => 'Ambiguo — revisar manualmente',
                    ];
                    continue;
                }

                if ($match !== null) {
                    $walmartValue = $type === 'price' ? (float) $match->price : (int) $match->available_quantity;
                    $diff = $uploaded - $walmartValue;
                    $diffPercent = ($type === 'price' && $walmartValue > 0) ? round(($diff / $walmartValue) * 100, 2) : null;

                    $results[] = [
                        'sku' => $sku, 'matched_sku' => $match->sku, 'match_method' => 'shortcode',
                        'uploaded_value' => $uploaded, 'walmart_value' => $walmartValue,
                        'wpid' => $match->wpid, 'status' => $match->published_status,
                        'diff_amount' => $diff, 'diff_percent' => $diffPercent,
                        'synced_at' => $match->synced_at,
                        'result' => ($diff == 0) ? 'Coincide' : 'Diferencia',
                    ];
                    continue;
                }
            }

            $results[] = [
                'sku' => $sku, 'matched_sku' => '-', 'match_method' => 'exact',
                'uploaded_value' => $uploaded, 'walmart_value' => null,
                'wpid' => '-', 'status' => '-', 'diff_amount' => null, 'diff_percent' => null,
                'synced_at' => null, 'result' => 'No encontrado',
            ];
        }
        return $results;
    }

    /** Verificación puntual en vivo — se usa solo sobre las filas con diferencia, bajo demanda. */
    public function verifyLive($connectionId, $sku, $type, WalmartApiService $api) {
        if ($type === 'price') {
            $res = $api->getItem($connectionId, $sku);
            if ($res['code'] !== 200 || !$res['body']) {
                error_log('[WALMART-AUDIT-LIVE] getItem code=' . $res['code'] . ' sku=' . $sku . ' body=' . json_encode($res['body'] ?? null));
                return null;
            }
            $body = $res['body'];

            // La forma real de /v3/items/{sku} varía entre cuentas Walmart: a veces
            // devuelve el item directo, a veces envuelto en ItemResponse[0].
            $item = $body;
            if (isset($body->ItemResponse) && is_array($body->ItemResponse) && count($body->ItemResponse) > 0) {
                $item = $body->ItemResponse[0];
            } elseif (isset($body->itemResponse) && is_array($body->itemResponse) && count($body->itemResponse) > 0) {
                $item = $body->itemResponse[0];
            }

            $amount = $item->price->amount
                ?? $item->pricing->amount
                ?? (isset($item->pricing[0]->pricing) ? ($item->pricing[0]->pricing->amount ?? null) : null)
                ?? (is_numeric($item->price ?? null) ? $item->price : null);

            if ($amount === null) {
                error_log('[WALMART-AUDIT-LIVE] No se pudo ubicar el precio en la respuesta. sku=' . $sku . ' body=' . json_encode($body));
                return null;
            }
            return (float) $amount;
        }

        $res = $api->getInventory($connectionId, $sku);
        if ($res['code'] !== 200 || !$res['body']) {
            error_log('[WALMART-AUDIT-LIVE] getInventory code=' . $res['code'] . ' sku=' . $sku . ' body=' . json_encode($res['body'] ?? null));
            return null;
        }
        $body = $res['body'];
        $amount = $body->quantity->amount ?? (is_numeric($body->quantity ?? null) ? $body->quantity : null);

        if ($amount === null) {
            error_log('[WALMART-AUDIT-LIVE] No se pudo ubicar el stock en la respuesta. sku=' . $sku . ' body=' . json_encode($body));
            return null;
        }
        return (int) $amount;
    }

    public function fillXlsx($type, $xlsx, $results) {
        $fmt = function ($v) use ($type) {
            if ($v === null || $v === '') return '-';
            return $type === 'price' ? ('$' . number_format((float) $v, 0, ',', '.')) : (string) $v;
        };
        $methodLabels = ['exact' => 'Exacto', 'shortcode' => 'Código corto', 'ambiguous' => 'Ambiguo'];
        foreach ($results as $r) {
            $row = [
                $r['sku'], $r['matched_sku'] ?? '-', $r['wpid'], $fmt($r['uploaded_value']), $fmt($r['walmart_value']),
                $r['diff_amount'] !== null
                    ? (($r['diff_amount'] >= 0 ? '+' : '') . $fmt(abs((float) $r['diff_amount'])))
                    : '-',
            ];
            if ($type === 'price') {
                $row[] = $r['diff_percent'] !== null
                    ? (($r['diff_percent'] >= 0 ? '+' : '') . number_format((float) $r['diff_percent'], 1, ',', '.') . '%')
                    : '-';
            }
            $row[] = $r['result'];
            $row[] = $methodLabels[$r['match_method'] ?? 'exact'] ?? $r['match_method'];
            $xlsx->addRow($row);
        }
    }
}
