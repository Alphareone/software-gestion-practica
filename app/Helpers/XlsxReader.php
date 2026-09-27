<?php

class XlsxReader {
    const MAX_FILE_SIZE = 10485760; // 10 MB
    const MAX_ROWS = 100000;
    const MAX_COLS = 50;
    const MAX_XML_DEPTH = 10;

    public static function read($filename) {
        if (!file_exists($filename)) {
            throw new RuntimeException('Archivo no encontrado.');
        }

        $fileSize = filesize($filename);
        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Archivo demasiado grande (máx. 10 MB).');
        }

        libxml_use_internal_errors(true);

        $zip = new ZipArchive();
        $result = $zip->open($filename);
        if ($result !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX.');
        }

        $sharedStrings = [];
        $ssContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssContent !== false) {
            if (strlen($ssContent) > self::MAX_FILE_SIZE / 2) {
                $zip->close();
                throw new RuntimeException('Archivo interno demasiado grande.');
            }
            $ssXml = simplexml_load_string($ssContent);
            if ($ssXml) {
                $count = 0;
                foreach ($ssXml->si as $si) {
                    if ($count >= self::MAX_ROWS) break;
                    $sharedStrings[] = (string) $si->t;
                    $count++;
                }
            }
        }

        $sheetContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetContent === false) {
            throw new RuntimeException('No se encontró la hoja de cálculo en el XLSX.');
        }

        if (strlen($sheetContent) > self::MAX_FILE_SIZE / 2) {
            throw new RuntimeException('Hoja de cálculo demasiado grande.');
        }

        $sheetXml = simplexml_load_string($sheetContent);
        if (!$sheetXml) {
            throw new RuntimeException('Error al leer la hoja de cálculo.');
        }

        $rows = [];
        $rowCount = 0;
        foreach ($sheetXml->sheetData->row as $row) {
            if ($rowCount >= self::MAX_ROWS) {
                throw new RuntimeException('El archivo supera el límite de ' . self::MAX_ROWS . ' filas con datos. Divide tu archivo en partes más pequeñas.');
            }

            $cells = [];
            $colCount = 0;
            foreach ($row->c as $cell) {
                if ($colCount >= self::MAX_COLS) break;
                $type = (string) $cell['t'];
                $value = (string) $cell->v;
                if ($type === 's') {
                    $idx = (int) $value;
                    $cells[] = $sharedStrings[$idx] ?? '';
                } else {
                    $cells[] = $value;
                }
                $colCount++;
            }
            
            $trimmed = array_filter($cells, function($v) { return trim((string) $v) !== ''; });
            if (!empty($trimmed)) {
                $rows[] = $cells;
                $rowCount++;
            }
        }

        return $rows;
    }
}
