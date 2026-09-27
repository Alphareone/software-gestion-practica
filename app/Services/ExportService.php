<?php


class ExportService {
    private $api;

    public function __construct($db = null) {
        $this->api = new MercadoLibreApiService($db);
    }

    public function throttledReadfile($filepath, $mbPerSecond = 2.5) {
        $chunkSize = 262144;
        $bytesPerSecond = $mbPerSecond * 1024 * 1024;
        $sleepPerChunk = (int) (($chunkSize / $bytesPerSecond) * 1000000);

        $handle = fopen($filepath, 'rb');
        if (!$handle) return;

        while (!feof($handle)) {
            $start = microtime(true);
            echo fread($handle, $chunkSize);
            flush();

            $elapsed = (microtime(true) - $start) * 1000000;
            $remaining = $sleepPerChunk - (int) $elapsed;
            if ($remaining > 0) {
                usleep($remaining);
            }
        }
        fclose($handle);
    }

    public function extractSku($item) {
        return MlApiHelper::extractSku($item);
    }

    public function extractField($item, $key) {
        $isArr = is_array($item);
        $get = function($field) use ($item, $isArr) {
            return $isArr ? ($item[$field] ?? '') : ($item->$field ?? '');
        };

        switch ($key) {
            case 'codigo': return $get('id');
            case 'titulo': return $get('title');
            case 'precio': return $get('price') ?: 0;
            case 'stock': return $get('available_quantity') ?: 0;
            case 'sku':
                if ($isArr) return $item['sku'] ?? '';
                return $this->extractSku($item);
            case 'condicion': return $get('condition');
            case 'tipo': return $get('listing_type_id');
            case 'enlace': return $get('permalink');
            case 'categoria':
                $name = $get('category_name');
                $id = $get('category_id');
                return $name ?: ($id ?: '');
            case 'atributos':
                if ($isArr) return '-';
                return $this->extractAttributes($item);
            case 'variaciones':
                if ($isArr) return '-';
                return $this->extractVariations($item);
            case 'garantia': return $get('warranty');
            default: return '';
        }
    }

    public function extractAttributes($item) {
        if (empty($item->attributes)) return '';
        $pairs = [];
        foreach ($item->attributes as $attr) {
            $name = trim($attr->name ?? '');
            $val = trim($attr->value_name ?? '');
            if ($name && $val !== '' && !in_array($attr->id, ['SELLER_SKU', 'SELLER_SKU_', 'SKU'])) {
                $pairs[] = $name . ': ' . $val;
            }
        }
        return implode(' | ', $pairs);
    }

    public function extractVariations($item) {
        if (empty($item->variations)) return '';
        $summaries = [];
        foreach ($item->variations as $v) {
            $parts = [];
            if (!empty($v->attribute_combinations)) {
                foreach ($v->attribute_combinations as $ac) {
                    $parts[] = $ac->value_name ?? '';
                }
            }
            $parts[] = 'Stock: ' . ($v->available_quantity ?? 0);
            $parts[] = '$' . ($v->price ?? 0);
            $summaries[] = implode(' / ', $parts);
        }
        return implode(' | ', $summaries);
    }

    public function exportProductsXlsx($items, $selectedFields, $allFields) {
        $headers = [];
        foreach ($selectedFields as $key) {
            if (isset($allFields[$key])) {
                $headers[] = $allFields[$key];
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $col = 0;
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8E8E8']],
            'borders' => ['bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];
        foreach ($headers as $h) {
            $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($coord, $h);
            $sheet->getStyle($coord)->applyFromArray($headerStyle);
            $col++;
        }

        $rowNum = 2;
        foreach ($items as $item) {
            $col = 0;
            foreach ($selectedFields as $key) {
                if (isset($allFields[$key])) {
                    $val = $this->extractField($item, $key);
                    $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . $rowNum;
                    if (is_numeric($val)) {
                        $sheet->setCellValue($coord, $val);
                    } else {
                        $sheet->setCellValue($coord, (string) $val);
                    }
                    $col++;
                }
            }
            $rowNum++;

            if ($rowNum % 500 === 0) {
                gc_collect_cycles();
            }
        }

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1');

        return $spreadsheet;
    }

    public function outputXlsx($spreadsheet, $filename) {
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $xamppTmp = ini_get('session.save_path') ?: sys_get_temp_dir();
        $filepath = tempnam($xamppTmp, 'ml_') . '.xlsx';
        $writer->save($filepath);

        while (ob_get_level()) ob_end_clean();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        unlink($filepath);
        exit;
    }

    public function exportBatchXlsxData($logs) {
        $statusMap = [
            'active' => 'Activa',
            'paused' => 'Pausada',
            'closed' => 'Finalizada',
            'under_review' => 'Bajo revisión',
        ];
        $headers = ['Fecha', 'Usuario', 'Tienda', 'SKU', 'Item ID', 'Estado ML', 'Precio anterior', 'Precio nuevo', 'Dif. $', 'Var. %', 'Resultado', 'Mensaje'];

        $xlsx = new XlsxWriter();
        $xlsx->setHeaders($headers);

        $fmtPrice = function($v) {
            if ($v === null) return '-';
            return '$' . number_format((float) $v, 0, ',', '.');
        };

        foreach ($logs as $log) {
            $statusLabel = $statusMap[$log->item_status ?? ''] ?? ($log->item_status ?: '-');
            $resultLabel = $log->status === 'success' ? 'Completado' : 'Error';
            $diffAmount = $log->diff_amount;
            $diffPercent = $log->diff_percent;
            $xlsx->addRow([
                date('d/m/y H:i', strtotime($log->created_at . ' UTC')),
                $log->username ?? 'N/A',
                $log->store_name ?? '-',
                $log->sku,
                $log->ml_item_id,
                $statusLabel,
                $fmtPrice($log->old_price),
                $fmtPrice($log->new_price),
                $diffAmount !== null ? (($diffAmount >= 0 ? '+' : '') . '$' . number_format((float) $diffAmount, 0, ',', '.')) : '-',
                $diffPercent !== null ? (($diffPercent >= 0 ? '+' : '') . number_format((float) $diffPercent, 1, ',', '.') . '%') : '-',
                $resultLabel,
                $log->message ?? '',
            ]);
        }

        return $xlsx;
    }

    public function exportPriceResultsXlsx($results) {
        $statusMap = [
            'active' => 'Activa',
            'paused' => 'Pausada',
            'closed' => 'Finalizada',
            'under_review' => 'Bajo revisión',
        ];

        $headers = ['SKU', 'Item ID', 'Estado ML', 'Precio anterior', 'Precio nuevo', 'Dif. $', 'Var. %', 'Resultado', 'Mensaje'];

        $xlsx = new XlsxWriter();
        $xlsx->setHeaders($headers);

        $fmtPrice = function($v) {
            if ($v === null || $v === '-' || $v === '?') return '-';
            return '$' . number_format((float) $v, 0, ',', '.');
        };
        $fmtDiff = function($v) {
            if ($v === null || $v === '-') return '-';
            $v = (float) $v;
            return ($v >= 0 ? '+' : '') . '$' . number_format($v, 0, ',', '.');
        };
        $fmtPct = function($v) {
            if ($v === null || $v === '-') return '-';
            $v = (float) $v;
            return ($v >= 0 ? '+' : '') . number_format($v, 1, ',', '.') . '%';
        };

        foreach ($results as $r) {
            $statusLabel = $statusMap[$r['item_status']] ?? ($r['item_status'] ?: '-');
            $resultLabel = $r['status'] === 'success' ? 'OK' : 'Error';
            $xlsx->addRow([
                $r['sku'],
                $r['item_id'],
                $statusLabel,
                $fmtPrice($r['old_price']),
                $fmtPrice($r['new_price']),
                $fmtDiff($r['diff_amount']),
                $fmtPct($r['diff_percent']),
                $resultLabel,
                $r['message'],
            ]);
        }

        return $xlsx;
    }

    public function exportPriceTemplateXlsx() {
        $xlsx = new XlsxWriter();
        $xlsx->setHeaders(['SKU', 'Precio']);
        $xlsx->addRow(['SKU123', '19990']);
        $xlsx->addRow(['SKU456', '25000']);
        return $xlsx;
    }

    public function getStoreProductsForExport($mlUserId, $token, $searchQuery = '', $maxItems = 1000) {
        $allItems = [];
        $limit = 50;
        $offset = 0;

        while ($offset < $maxItems) {
            $searchUrl = '/users/' . $mlUserId . '/items/search?search_type=scan&limit=' . $limit . '&offset=' . $offset;
            if ($searchQuery) {
                $searchUrl .= '&q=' . urlencode($searchQuery);
            }
            $search = $this->api->call($searchUrl, 'GET', null, $token);
            if ($search['code'] !== 200 || !$search['body']) break;

            $itemIds = $search['body']->results ?? [];
            if (empty($itemIds)) break;

            $allItems = array_merge($allItems, $itemIds);
            $paging = $search['body']->paging ?? null;
            $total = $paging->total ?? 0;
            $offset += $limit;
            if ($offset >= $total) break;
        }

        $items = [];
        foreach (array_chunk($allItems, 20) as $chunk) {
            $detail = $this->api->getItemDetails($chunk, $token);
            if ($detail['code'] === 200 && is_array($detail['body'])) {
                foreach ($detail['body'] as $entry) {
                    $obj = $entry->body ?? $entry;
                    if (isset($obj->id)) $items[] = $obj;
                }
            }
        }

        return $items;
    }
}
