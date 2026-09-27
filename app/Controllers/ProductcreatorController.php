<?php
require_once __DIR__ . '/../Helpers/ColumnMapper.php';
require_once __DIR__ . '/../Helpers/IconHelper.php';

class ProductcreatorController extends Controller {

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function index() {
        $this->requireAuth();

        $db = $this->db();
        $medStmt = $db->query("SELECT id, categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante FROM product_medidas ORDER BY categoria ASC");
        $medStmt->execute();
        $medidas = $medStmt->fetchAll();

        $prodCount = 0;
        $prodCatStmt = $db->query("SELECT COUNT(*) as cnt, category FROM products_master GROUP BY category ORDER BY category");
        $prodCatStmt->execute();
        $prodCategories = $prodCatStmt->fetchAll();
        foreach ($prodCategories as $pc) {
            $prodCount += $pc->cnt;
        }

        $page_title = 'Creación de Productos';
        $current_nav = 'productcreator';
        $data = [
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'preview' => $_SESSION['pc_preview'] ?? [],
            'detectedCols' => $_SESSION['pc_detected'] ?? [],
            'templateMapping' => $_SESSION['pc_template_mapping'] ?? [],
            'medidas' => $medidas,
            'prodCount' => $prodCount,
            'prodCategories' => $prodCategories,
        ];
        $view_content = __DIR__ . '/../Views/productcreator/content-index.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function uploadSource() {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['source_file']) || $_FILES['source_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['pc_error'] = 'Error al subir el archivo.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['source_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            $_SESSION['pc_error'] = 'Formato no válido. Solo .xlsx y .xls.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        try {
            ini_set('memory_limit', '1024M');
            set_time_limit(300);

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
            $reader->setReadDataOnly(true);

            $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                public function readCell(string $column, int $row, string $worksheetName = ''): bool {
                    return $row <= 5001;
                }
            });

            $spreadsheet = $reader->load($_FILES['source_file']['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = min($worksheet->getHighestRow(), 5001);

            if ($highestRow < 2) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                throw new \Exception('El archivo no tiene datos.');
            }

            $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
            $allRows = [];
            for ($r = 1; $r <= $highestRow; $r++) {
                $row = [];
                $hasData = false;
                for ($c = 1; $c <= $highestCol; $c++) {
                    $val = $worksheet->getCell([$c, $r])->getValue();
                    if ($val !== null) {
                        $s = trim((string)$val);
                        if ($s !== '') $hasData = true;
                        $row[] = $val;
                    } else {
                        $row[] = '';
                    }
                }
                if ($hasData) {
                    $allRows[] = $row;
                }
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet);

            if (count($allRows) < 2) {
                throw new \Exception('El archivo no tiene datos después de filtrar.');
            }

            $headerRow = ColumnMapper::detectHeaderRow($allRows);
            $headers = $allRows[$headerRow];

            $synonyms = ColumnMapper::defaultTemplateSynonyms();
            $expected = ['sku', 'name', 'description', 'price', 'stock', 'category', 'brand', 'gender', 'color', 'size', 'ean'];

            $scores = [];
            foreach ($headers as $colIdx => $header) {
                if ($header === null || trim((string)$header) === '') continue;
                $hn = ColumnMapper::normalizeHeader((string)$header);
                $bestField = null;
                $bestScore = 0;
                foreach ($expected as $field) {
                    $aliases = $synonyms[$field] ?? [$field];
                    foreach ($aliases as $alias) {
                        $an = ColumnMapper::normalizeHeader($alias);
                        if ($hn === $an) { $bestField = $field; $bestScore = 100; break 2; }
                        if (strpos($hn, $an) !== false || strpos($an, $hn) !== false) {
                            if (90 > $bestScore) { $bestField = $field; $bestScore = 90; }
                        }
                        similar_text($hn, $an, $pct);
                        if ($pct > $bestScore) { $bestField = $field; $bestScore = $pct; }
                    }
                }
                if ($bestField && $bestScore >= 50) {
                    $scores[] = ['field' => $bestField, 'colIdx' => $colIdx, 'header' => $header, 'score' => $bestScore];
                }
            }

            usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
            $detected = [];
            $assignedFields = [];
            $assignedCols = [];
            foreach ($scores as $s) {
                if (in_array($s['field'], $assignedFields)) continue;
                if (in_array($s['colIdx'], $assignedCols)) continue;
                $detected[$s['field']] = ['index' => $s['colIdx'], 'header' => $s['header'], 'score' => round($s['score'])];
                $assignedFields[] = $s['field'];
                $assignedCols[] = $s['colIdx'];
            }
            foreach ($expected as $field) {
                if (!isset($detected[$field])) $detected[$field] = null;
            }

            $dataRows = array_slice($allRows, $headerRow + 1);

            $preview = [];
            foreach (array_slice($dataRows, 0, 20) as $row) {
                $entry = [];
                foreach ($detected as $field => $info) {
                    if ($info !== null && isset($row[$info['index']])) {
                        $entry[$field] = $row[$info['index']];
                    } else {
                        $entry[$field] = '';
                    }
                }
                $preview[] = $entry;
            }

            $missingCritical = [];
            if (!$detected['sku']) $missingCritical[] = 'SKU';
            if (!$detected['name'] && !$detected['description']) $missingCritical[] = 'Nombre/Descripción';
            if (!$detected['price']) $missingCritical[] = 'Precio';
            if (!$detected['stock']) $missingCritical[] = 'Stock';

            $_SESSION['pc_preview'] = $preview;
            $_SESSION['pc_data_rows'] = $dataRows;
            $_SESSION['pc_detected'] = $detected;

            if (!empty($missingCritical)) {
                $_SESSION['pc_warning'] = 'Columnas no encontradas: ' . implode(', ', $missingCritical) . '. Se importarán con valores por defecto.';
            }
            $_SESSION['pc_success'] = 'Archivo procesado: ' . (count($dataRows)) . ' filas, ' . count(array_filter($detected)) . ' columnas detectadas.';

        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al procesar: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }

    public function importData() {
        $this->requireAuth();

        $dataRows = $_SESSION['pc_data_rows'] ?? [];
        $detected = $_SESSION['pc_detected'] ?? [];

        if (empty($dataRows) || empty($detected)) {
            $_SESSION['pc_error'] = 'No hay datos para importar. Subí el archivo primero.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['pc_error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        try {
            $db = $this->db();
            $inserted = 0;
            $updated = 0;
            $errors = [];
            $stmt = $db->query(
                "INSERT INTO products_master (sku, title, description, price, category, brand, gender, color, size, stock)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 title = VALUES(title), description = VALUES(description), price = VALUES(price),
                 category = VALUES(category), brand = VALUES(brand), gender = VALUES(gender),
                 color = VALUES(color), size = VALUES(size), stock = VALUES(stock)"
            );

            foreach ($dataRows as $row) {
                $cell = fn($field) => isset($detected[$field], $row[$detected[$field]['index']]) ? $row[$detected[$field]['index']] : null;

                $sku = trim((string)($cell('sku') ?? ''));
                if (empty($sku)) continue;

                $title = trim((string)($cell('name') ?? ''));
                $description = trim((string)($cell('description') ?? ($cell('name') ?? '')));
                $priceRaw = trim((string)($cell('price') ?? '0'));
                $price = (int)preg_replace('/[^0-9]/', '', $priceRaw);
                if ($price === 0 && preg_match('/[1-9]/', $priceRaw)) $price = (int)$priceRaw;
                $category = trim((string)($cell('category') ?? ''));
                $brand = trim((string)($cell('brand') ?? ''));
                $gender = trim((string)($cell('gender') ?? ''));
                $color = trim((string)($cell('color') ?? ''));
                $size = mb_strtolower(trim((string)($cell('size') ?? ($cell('ean') ?? ''))));
                $stock = (int)trim((string)($cell('stock') ?? '0'));

                try {
                    $stmt->execute([$sku, $title, $description, $price, $category, $brand, $gender, $color, $size, $stock]);
                    if ($stmt->rowCount() === 1) $inserted++; else $updated++;
                } catch (\Exception $e) {
                    $errors[] = "SKU $sku: " . $e->getMessage();
                }
            }

            $msg = "$inserted insertado(s), $updated actualizado(s).";
            if (!empty($errors)) $msg .= ' Errores: ' . implode(' | ', $errors);
            $_SESSION['pc_success'] = $msg;

            unset($_SESSION['pc_preview'], $_SESSION['pc_data_rows'], $_SESSION['pc_detected']);

        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al importar: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }

    public function uploadTemplate() {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['pc_error'] = 'Error al subir la plantilla.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['template_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            $_SESSION['pc_error'] = 'Formato no válido. Solo .xlsx y .xls.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        try {
            ini_set('memory_limit', '512M');
            $tmpPath = $_FILES['template_file']['tmp_name'];
            $origName = $_FILES['template_file']['name'];
            $destPath = __DIR__ . '/../temp/ml_template.' . $ext;
            $tempDir = dirname($destPath);
            if (!is_dir($tempDir)) mkdir($tempDir, 0775, true);
            move_uploaded_file($tmpPath, $destPath);

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($destPath);

            $mapping = [];
            $guideLookup = [];
            $keywords = ['sku', 'titulo', 'precio', 'stock', 'descripci', 'marca', 'genero', 'modelo', 'ean', 'codigo', 'guia', 'fabricante', 'condicion', 'moneda', 'publicaci', 'envio', 'retiro', 'garantia', 'ancho', 'alto', 'profundidad', 'peso', 'cuello', 'material', 'composici', 'personalizado', 'talla', 'foto', 'manga'];

            foreach ($spreadsheet->getSheetNames() as $sheetName) {
                $snNorm = ColumnMapper::normalizeHeader($sheetName);
                if (in_array($snNorm, ['ayuda', 'extra info', 'legales', 'guia de tallas'])) continue;

                if (strpos($snNorm, 'guia') !== false && strpos($snNorm, 'talla') !== false) {
                    $gw = $spreadsheet->getSheetByName($sheetName);
                    if ($gw) {
                        $gmaxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($gw->getHighestColumn());
                        $gHeaders = null;
                        $gSizeCol = null; $gGenderCol = null; $gCodeCol = null;
                        for ($r = 1; $r <= 8; $r++) {
                            $rowSizeCol = null; $rowGenderCol = null; $rowCodeCol = null;
                            $found = 0;
                            for ($c = 1; $c <= $gmaxCol; $c++) {
                                $v = $gw->getCell([$c, $r])->getValue();
                                if ($v === null) continue;
                                $gn = ColumnMapper::normalizeHeader((string)$v);
                                $colHit = false;
                                if ($rowSizeCol === null && (strpos($gn, 'talla') !== false || strpos($gn, 'talle') !== false)) { $rowSizeCol = $c; $colHit = true; }
                                if ($rowGenderCol === null && strpos($gn, 'genero') !== false) { $rowGenderCol = $c; $colHit = true; }
                                if ($rowCodeCol === null && (strpos($gn, 'codigo') !== false || strpos($gn, 'guia') !== false)) { $rowCodeCol = $c; $colHit = true; }
                                if ($colHit) $found++;
                            }
                            if ($found >= 2) { $gHeaders = $r; $gSizeCol = $rowSizeCol; $gGenderCol = $rowGenderCol; $gCodeCol = $rowCodeCol; break; }
                        }
                        if ($gHeaders && $gCodeCol) {
                            for ($r = $gHeaders + 1; $r <= $gw->getHighestRow(); $r++) {
                                $sizeVal = $gSizeCol ? trim((string)$gw->getCell([$gSizeCol, $r])->getValue() ?? '') : '';
                                $genderVal = $gGenderCol ? trim((string)$gw->getCell([$gGenderCol, $r])->getValue() ?? '') : '';
                                $codeVal = trim((string)$gw->getCell([$gCodeCol, $r])->getValue() ?? '');
                                if ($codeVal === '') continue;
                                $sizeLk = mb_strtolower($sizeVal);
                                if ($gGenderCol) {
                                    $guideLookup[mb_strtolower($genderVal) . '|' . $sizeLk] = $codeVal;
                                }
                                $guideLookup[$sizeLk] = $codeVal;
                            }
                        }
                    }
                    continue;
                }

                $ws = $spreadsheet->getSheetByName($sheetName);
                if (!$ws) continue;

                $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestColumn());

                $bestRow = null;
                $bestScore = 0;
                $bestHeaders = [];
                for ($r = 1; $r <= 8; $r++) {
                    $score = 0;
                    $rowHeaders = [];
                    for ($c = 1; $c <= $maxCol; $c++) {
                        $v = $ws->getCell([$c, $r])->getValue();
                        if ($v === null) continue;
                        $vn = ColumnMapper::normalizeHeader((string)$v);
                        $rowHeaders[$c] = $v;
                        foreach ($keywords as $kw) {
                            $kn = ColumnMapper::normalizeHeader($kw);
                            if ($vn === $kn || strpos($vn, $kn) !== false) { $score++; break; }
                        }
                    }
                    if ($score > $bestScore) { $bestScore = $score; $bestRow = $r; $bestHeaders = $rowHeaders; }
                }

                if (!$bestRow || $bestScore < 3) continue;
                $headerRow = $bestRow;
                $headers = $bestHeaders;

                $sheetMapping = [];
                foreach ($headers as $colIdx => $header) {
                    $hn = ColumnMapper::normalizeHeader((string)$header);
                    $matched = null;
                    $hns = preg_replace('/\s+/', '', $hn); // compact for multi-word matches
                    if (strpos($hn, 'sku') !== false) $matched = 'sku';
                    elseif (strpos($hn, 'titulo') !== false) $matched = 'title';
                    elseif (strpos($hns, 'codigouniversal') !== false || strpos($hn, 'ean') !== false || (strpos($hn, 'codigo') !== false && strpos($hn, 'barras') !== false)) $matched = 'ean';
                    elseif ((strpos($hn, 'guia') !== false) && (strpos($hn, 'talla') !== false || strpos($hn, 'id') !== false || strpos($hn, 'codigo') !== false)) $matched = 'guia_id';
                    elseif (strpos($hn, 'precio') !== false) $matched = 'price';
                    elseif (strpos($hn, 'stock') !== false) $matched = 'stock';
                    elseif (strpos($hn, 'descripci') !== false) $matched = 'description';
                    elseif (strpos($hn, 'marca') !== false) $matched = 'brand';
                    elseif (strpos($hn, 'genero') !== false) $matched = 'gender';
                    elseif (strpos($hn, 'modelo') !== false) $matched = 'model';
                    elseif (strpos($hn, 'condicion') !== false) $matched = 'condition';
                    elseif (strpos($hns, 'tipodepublicaci') !== false) $matched = 'tipo_pub';
                    elseif (strpos($hns, 'tipo') !== false && strpos($hn, 'publicaci') !== false) $matched = 'tipo_pub';
                    elseif (strpos($hns, 'formadeenvio') !== false) $matched = 'forma_envio';
                    elseif (strpos($hns, 'costodeenvio') !== false) $matched = 'costo_envio';
                    elseif (strpos($hns, 'retiroenpersona') !== false) $matched = 'retiro';
                    elseif (strpos($hns, 'fabricante') !== false) $matched = 'fabricante';
                    elseif (strpos($hn, 'ancho') !== false) $matched = 'width';
                    elseif (strpos($hn, 'alto') !== false) $matched = 'height';
                    elseif (strpos($hn, 'profundidad') !== false || strpos($hn, 'largo') !== false) $matched = 'depth';
                    elseif (strpos($hns, 'pesofisico') !== false || strpos($hn, 'peso') !== false) $matched = 'weight';
                    elseif (strpos($hn, 'moneda') !== false) $matched = 'currency';
                    elseif (strpos($hns, 'tipodegarantia') !== false) $matched = 'warranty_type';
                    elseif (strpos($hns, 'unidaddetiempo') !== false) $matched = 'warranty_unit';
                    elseif (strpos($hns, 'tiempodegarantia') !== false) $matched = 'warranty_time';
                    elseif (strpos($hn, 'personalizado') !== false) $matched = 'customized';
                    elseif (strpos($hn, 'talla') !== false) $matched = 'size';
                    elseif (strpos($hn, 'nombre comercial') !== false) $matched = 'color';
                    if ($matched && !isset($sheetMapping[$matched])) {
                        $sheetMapping[$matched] = ['col' => $colIdx, 'header' => trim((string)$header)];
                    }
                }

                $dataStartRow = null;
                $dataCandidates = [];
                for ($r = $headerRow + 1; $r <= $headerRow + 10 && $r <= 20; $r++) {
                    $firstVal = $ws->getCell([1, $r])->getValue();
                    if ($firstVal !== null) {
                        $s = trim((string)$firstVal);
                        if ($s !== '' && !str_contains($s, 'Obligatorio') && !str_contains($s, 'Máximo') && !str_contains($s, 'Cómo') && !str_contains($s, 'Escribe') && !str_contains($s, 'Seleccionar') && !str_contains($s, 'Necesito')) {
                            $dataCandidates[] = $r;
                        }
                    }
                }
                $dataStartRow = !empty($dataCandidates) ? min($dataCandidates) : ($headerRow + 5);

                $mapping[$sheetName] = [
                    'cols' => $sheetMapping,
                    'dataStartRow' => $dataStartRow,
                    'headerRow' => $headerRow,
                    'name' => $sheetName,
                ];
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $_SESSION['pc_template_mapping'] = $mapping;
            $_SESSION['pc_guide_lookup'] = $guideLookup;
            $_SESSION['pc_orig_filename'] = $origName;
            $sheetCount = count($mapping);
            $diag = "Guías de tallas: " . count($guideLookup) . " registros.";
            $msg = $sheetCount > 0
                ? "Plantilla analizada: $sheetCount pestaña(s) con datos. $diag"
                : 'No se encontraron pestañas con datos. Asegurate de usar una plantilla de MercadoLibre válida.';
            $_SESSION[$sheetCount > 0 ? 'pc_success' : 'pc_error'] = $msg;

        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al procesar plantilla: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }

    public function fillTemplate() {
        $this->requireAuth();

        $mapping = $_SESSION['pc_template_mapping'] ?? [];
        $templateFile = __DIR__ . '/../temp/ml_template.xlsx';

        if (empty($mapping) || !file_exists($templateFile)) {
            $_SESSION['pc_error'] = 'Primero subí una plantilla de MercadoLibre.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['pc_error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        try {
            ini_set('memory_limit', '1024M');
            set_time_limit(300);

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($templateFile);

            $db = $this->db();
            $catsStmt = $db->query("SELECT DISTINCT category FROM products_master ORDER BY category");
            $catsStmt->execute();
            $categories = $catsStmt->fetchAll(\PDO::FETCH_COLUMN);

            $guideLookup = $_SESSION['pc_guide_lookup'] ?? [];

            $medidasList = [];
            $medStmt = $db->query("SELECT * FROM product_medidas ORDER BY LENGTH(categoria) DESC");
            $medStmt->execute();
            while ($m = $medStmt->fetch(\PDO::FETCH_OBJ)) {
                $medidasList[] = $m;
            }

            $filledCount = 0;
            $matchedMedCount = 0;
            $matchedGuideCount = 0;

            foreach ($categories as $category) {
                $cn = ColumnMapper::normalizeHeader($category);
                $bestSheet = null;
                $bestPct = 0;
                foreach ($mapping as $sName => $info) {
                    $sn = ColumnMapper::normalizeHeader($sName);
                    similar_text($cn, $sn, $pct);
                    if ($pct > $bestPct && $pct > 50) { $bestPct = $pct; $bestSheet = $sName; }
                    if ($pct === 100.0 || $cn === $sn || strpos($cn, $sn) !== false || strpos($sn, $cn) !== false) {
                        $bestSheet = $sName; break;
                    }
                }

                if (!$bestSheet || !$spreadsheet->sheetNameExists($bestSheet)) continue;

                $ws = $spreadsheet->getSheetByName($bestSheet);
                $info = $mapping[$bestSheet];
                $cols = $info['cols'];
                $dataStartRow = $info['dataStartRow'];

                $prodStmt = $db->query("SELECT * FROM products_master WHERE category = ? ORDER BY sku");
                $prodStmt->execute([$category]);

                $products = $prodStmt->fetchAll(\PDO::FETCH_OBJ);
                $eanCodes = $products ? ColumnMapper::generateEan13Massive(count($products), true) : [];

                $f = $dataStartRow;
                foreach ($products as $i => $p) {
                    if (isset($cols['sku'])) $ws->setCellValue([$cols['sku']['col'], $f], $p->sku);
                    if (isset($cols['title'])) $ws->setCellValue([$cols['title']['col'], $f], $p->title);
                    if (isset($cols['price'])) $ws->setCellValue([$cols['price']['col'], $f], $p->price ?: '');
                    if (isset($cols['stock'])) $ws->setCellValue([$cols['stock']['col'], $f], $p->stock ?: 0);
                    if (isset($cols['description'])) $ws->setCellValue([$cols['description']['col'], $f], $p->description);
                    if (isset($cols['brand'])) $ws->setCellValue([$cols['brand']['col'], $f], $p->brand);

                    if (isset($cols['condition'])) $ws->setCellValue([$cols['condition']['col'], $f], 'Nuevo');
                    if (isset($cols['tipo_pub'])) $ws->setCellValue([$cols['tipo_pub']['col'], $f], 'Clásica');
                    if (isset($cols['forma_envio'])) $ws->setCellValue([$cols['forma_envio']['col'], $f], 'Mercado Envíos | Mercado Envíos Flex');
                    if (isset($cols['costo_envio'])) $ws->setCellValue([$cols['costo_envio']['col'], $f], 'A cargo del comprador');
                    if (isset($cols['retiro'])) $ws->setCellValue([$cols['retiro']['col'], $f], 'No acepto');
                    if (isset($cols['fabricante'])) $ws->setCellValue([$cols['fabricante']['col'], $f], $p->brand ?: '');
                    if (isset($cols['currency'])) $ws->setCellValue([$cols['currency']['col'], $f], '$');
                    if (isset($cols['warranty_type'])) $ws->setCellValue([$cols['warranty_type']['col'], $f], 'Garantía del vendedor');
                    if (isset($cols['warranty_time'])) $ws->setCellValue([$cols['warranty_time']['col'], $f], 6);
                    if (isset($cols['warranty_unit'])) $ws->setCellValue([$cols['warranty_unit']['col'], $f], 'meses');
                    if (isset($cols['customized'])) $ws->setCellValue([$cols['customized']['col'], $f], 'No');

                    if (isset($cols['model'])) {
                        $parts = explode('-', $p->sku);
                        $model = implode('-', array_slice($parts, 0, min(3, count($parts))));
                        $ws->setCellValue([$cols['model']['col'], $f], $model);
                    }

                    if (isset($cols['gender'])) {
                        $ws->setCellValue([$cols['gender']['col'], $f], $p->gender ?: '');
                    }

                    if (isset($cols['color'])) {
                        $ws->setCellValue([$cols['color']['col'], $f], $p->color ?: '');
                    }

                    if (isset($cols['size'])) {
                        $ws->setCellValue([$cols['size']['col'], $f], $p->size ? mb_strtolower(trim($p->size)) : '');
                    }

                    if (isset($cols['ean'])) {
                        $eanCol = $cols['ean']['col'];
                        $ws->setCellValue([$eanCol, $f], $eanCodes[$i] ?? '');
                        $ws->getStyle([$eanCol, $f])->getNumberFormat()->setFormatCode('0000000000000');
                    }

                    if (isset($cols['guia_id'])) {
                        $guideKey = mb_strtolower(trim($p->gender ?? '') . '|' . trim($p->size ?? ''));
                        $guideCode = $guideLookup[$guideKey] ?? '';
                        if ($guideCode === '' && trim($p->size ?? '') !== '') {
                            $guideCode = $guideLookup[mb_strtolower(trim($p->size ?? ''))] ?? '';
                        }
                        $ws->setCellValue([$cols['guia_id']['col'], $f], $guideCode);
                        if ($guideCode !== '') $matchedGuideCount++;
                    }

                    $bestMed = null;
                    $bestLen = 0;
                    $texto = mb_strtolower(trim($p->category ?? '') . ' ' . trim($p->title ?? ''));
                    foreach ($medidasList as $m) {
                        $mc = mb_strtolower(trim($m->categoria));
                        if ($mc !== '' && strpos($texto, $mc) !== false && mb_strlen($mc) > $bestLen) {
                            $bestLen = mb_strlen($mc);
                            $bestMed = $m;
                        }
                    }
                    if ($bestMed) {
                        if (isset($cols['width'])) $ws->setCellValue([$cols['width']['col'], $f], $this->parseMedidaFloat($bestMed->ancho_cm));
                        if (isset($cols['height'])) $ws->setCellValue([$cols['height']['col'], $f], $this->parseMedidaFloat($bestMed->alto_cm));
                        if (isset($cols['depth'])) $ws->setCellValue([$cols['depth']['col'], $f], $this->parseMedidaFloat($bestMed->grueso_cm));
                        if (isset($cols['weight'])) $ws->setCellValue([$cols['weight']['col'], $f], $this->parseMedidaFloat($bestMed->peso_kg));
                        $matchedMedCount++;
                    }

                    $f++;
                    $filledCount++;
                }
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($templateFile);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);

            $_SESSION['pc_success'] = "Plantilla llenada con $filledCount producto(s). Medidas: $matchedMedCount/$filledCount. Guías: $matchedGuideCount/$filledCount.";
            $_SESSION['pc_download_ready'] = true;

        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al llenar plantilla: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }

    public function download() {
        $this->requireAuth();
        $file = __DIR__ . '/../temp/ml_template.xlsx';
        if (!file_exists($file)) {
            $_SESSION['pc_error'] = 'No hay archivo para descargar.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }
        $origName = $_SESSION['pc_orig_filename'] ?? 'plantilla_llenada.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $origName . '"');
        readfile($file);
        unset($_SESSION['pc_download_ready'], $_SESSION['pc_template_mapping'], $_SESSION['pc_orig_filename']);
        $_SESSION['pc_prompt_clean'] = true;
        exit;
    }

    public function downloadVariantsTemplate() {
        $this->requireAuth();

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Productos');

            $headers = ['SKU', 'Título', 'Descripción', 'Stock', 'Género', 'Marca', 'Categoría', 'Colores', 'Tallas', 'Precio Final'];
            $colLetters = range('A', 'J');

            foreach ($headers as $i => $h) {
                $col = $colLetters[$i] . '1';
                $sheet->setCellValue($col, $h);
                $sheet->getStyle($col)->getFont()->setBold(true);
                $sheet->getStyle($col)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FF4472C4');
                $sheet->getStyle($col)->getFont()->getColor()->setRGB('FFFFFF');
            }

            foreach ($colLetters as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $sheet->getSheetView()->setZoomScale(90);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="plantilla_datos.xlsx"');

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
            exit;

        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al generar plantilla: ' . $e->getMessage();
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }
    }

    public function uploadVariants() {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['variants_file']) || $_FILES['variants_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['pc_error'] = 'Error al subir el archivo.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['variants_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            $_SESSION['pc_error'] = 'Formato no válido. Solo .xlsx y .xls.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['pc_error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($_FILES['variants_file']['tmp_name']);
            $sheet = $spreadsheet->getSheetByName('Productos') ?: $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            $colMap = [];
            $headerRow = null;
            $expectedHeaders = ['sku', 'titulo', 'descripcion', 'stock', 'genero', 'marca', 'categoria', 'colores', 'tallas', 'precio'];
            for ($r = 1; $r <= 3; $r++) {
                $matched = 0;
                $map = [];
                for ($c = 1; $c <= 10; $c++) {
                    $v = $sheet->getCell([$c, $r])->getValue();
                    if ($v === null) continue;
                    $vn = ColumnMapper::normalizeHeader((string)$v);
                    foreach ($expectedHeaders as $i => $eh) {
                        if (!isset($map[$i]) && ($vn === $eh || strpos($vn, $eh) !== false || strpos($eh, $vn) !== false)) {
                            $map[$i] = $c;
                            $matched++;
                            break;
                        }
                    }
                }
                if ($matched >= 4) { $headerRow = $r; $colMap = $map; break; }
            }

            if (!$headerRow || count($colMap) < 4) {
                $spreadsheet->disconnectWorksheets(); unset($spreadsheet);
                throw new \Exception('No se detectó la fila de encabezados. Asegurate de usar la plantilla descargada.');
            }

            $colorAbbrs = [
                'negro' => 'NEG', 'blanco' => 'BLA', 'gris' => 'GRI', 'azul' => 'AZU',
                'rojo' => 'ROJ', 'verde' => 'VER', 'amarillo' => 'AMA', 'naranjo' => 'NAR',
                'morado' => 'MOR', 'rosado' => 'ROS', 'celeste' => 'CEL', 'beige' => 'BEI',
                'cafe' => 'CAF', 'marino' => 'MAR', 'petroleo' => 'PET', 'burdeo' => 'BUR',
                'melange' => 'MEL', 'vino' => 'VIN', 'mostaza' => 'MOS', 'coral' => 'COR',
                'lila' => 'LIL', 'turquesa' => 'TUR', 'salmon' => 'SAL', 'fucsia' => 'FUC',
                'dorado' => 'DOR', 'plateado' => 'PLA', 'transparente' => 'TRA',
                'multicolor' => 'MUL', 'estam' => 'EST', 'print' => 'PRI',
            ];

            $getColorAbbr = function(string $color) use ($colorAbbrs): string {
                $c = mb_strtolower(trim($color));
                if (isset($colorAbbrs[$c])) return $colorAbbrs[$c];
                $parts = explode(' ', $c);
                $first = $parts[0] ?? $c;
                if (isset($colorAbbrs[$first])) return $colorAbbrs[$first];
                return strtoupper(mb_substr(preg_replace('/[^a-záéíóúñ]/i', '', strtr($c, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'])), 0, 3));
            };

            $parents = 0;
            $variants = [];

            for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
                $get = function(int $idx) use ($sheet, $r, $colMap) {
                    $c = $colMap[$idx] ?? null;
                    if ($c === null) return '';
                    $v = $sheet->getCell([$c, $r])->getValue();
                    return $v !== null ? trim((string)$v) : '';
                };

                $skuPadre = $get(0);
                $titulo = $get(1);
                $descripcion = $get(2);
                if (empty($skuPadre) && empty($titulo) && empty($descripcion)) continue;

                $stockTotal = (int)preg_replace('/[^0-9]/', '', $get(3));
                $genero = $get(4);
                $marca = $get(5);
                $categoria = $get(6);
                $coloresRaw = $get(7);
                $tallasRaw = $get(8);
                $precioRaw = $get(9);

                $price = (int)preg_replace('/[^0-9]/', '', $precioRaw);

                $tallasRawNorm = trim(mb_strtolower($tallasRaw));
                if ($tallasRawNorm === 't/u' || $tallasRawNorm === 'talla unica' || $tallasRawNorm === 'talle unico') {
                    $tallas = ['T/U'];
                } else {
                    $tallas = array_values(array_filter(array_map('trim', explode(',', str_replace('/', ',', $tallasRaw))), fn($v) => $v !== ''));
                }
                $colores = array_values(array_filter(array_map('trim', explode(',', $coloresRaw)), fn($v) => $v !== ''));

                if (empty($tallas)) $tallas = ['T/U'];
                if (empty($colores)) $colores = ['T/U'];

                $esVariable = count($tallas) > 1 || count($colores) > 1;
                $parents++;

                if ($esVariable) {
                    $numChildren = count($tallas) * count($colores);
                    $childIter = 0;
                    foreach ($colores as $color) {
                        $abbr = $getColorAbbr($color);
                        foreach ($tallas as $talla) {
                            $childIter++;
                            $childSku = $skuPadre . '-' . $abbr . '-' . str_replace('/', '_', $talla);
                            $baseStock = $stockTotal > 0 ? intdiv($stockTotal, $numChildren) : 0;
                            $childStock = ($childIter === $numChildren) ? ($baseStock + ($stockTotal % $numChildren)) : $baseStock;
                            $variants[] = [$childSku, $titulo, $descripcion, $price, $genero, $marca, $categoria, $skuPadre, $color, mb_strtolower($talla), $childStock];
                        }
                    }
                } else {
                    $variants[] = [$skuPadre, $titulo, $descripcion, $price, $genero, $marca, $categoria, $skuPadre, $colores[0], mb_strtolower($tallas[0]), $stockTotal];
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $_SESSION['pc_variants'] = $variants;
            $_SESSION['pc_variants_meta'] = ['parents' => $parents, 'total' => count($variants)];
            $_SESSION['pc_success'] = "Plantilla procesada: $parents producto(s) padre, " . count($variants) . " variante(s) generadas. Revisá la vista previa y confirmá la importación.";

        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al procesar plantilla: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }

    public function confirmVariants() {
        $this->requireAuth();

        $variants = $_SESSION['pc_variants'] ?? [];
        if (empty($variants)) {
            $_SESSION['pc_error'] = 'No hay variantes para importar. Subí la plantilla primero.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['pc_error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        try {
            $db = $this->db();
            $stmt = $db->query(
                "INSERT INTO products_master (sku, title, description, price, category, brand, gender, color, size, stock)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 title = VALUES(title), description = VALUES(description), price = VALUES(price),
                 category = VALUES(category), brand = VALUES(brand), gender = VALUES(gender),
                 color = VALUES(color), size = VALUES(size), stock = VALUES(stock)"
            );

            $inserted = 0;
            $updated = 0;
            $errors = [];

            foreach ($variants as $v) {
                try {
                    $stmt->execute([$v[0], $v[1], $v[2], $v[3], $v[6], $v[5], $v[4], $v[8], $v[9], $v[10]]);
                    if ($stmt->rowCount() === 1) $inserted++; else $updated++;
                } catch (\Exception $e) {
                    $errors[] = "SKU {$v[0]}: " . $e->getMessage();
                }
            }

            $msg = "$inserted insertado(s), $updated actualizado(s).";
            if (!empty($errors)) $msg .= ' Errores: ' . implode(' | ', array_slice($errors, 0, 10));
            $_SESSION['pc_success'] = $msg;

            unset($_SESSION['pc_variants'], $_SESSION['pc_variants_meta']);

        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al importar: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }

    public function downloadMedidasTemplate() {
        $this->requireAuth();
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Medidas');
            $headers = ['categoria', 'peso_kg', 'alto_cm', 'ancho_cm', 'grueso_cm', 'variante'];
            $colLetters = range('A', 'F');
            foreach ($headers as $i => $h) {
                $col = $colLetters[$i] . '1';
                $sheet->setCellValue($col, $h);
                $sheet->getStyle($col)->getFont()->setBold(true);
                $sheet->getStyle($col)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FF4472C4');
                $sheet->getStyle($col)->getFont()->getColor()->setRGB('FFFFFF');
            }
            $sheet->setCellValue('A2', 'poleron')->setCellValue('B2', '0.5')->setCellValue('C2', '10')->setCellValue('D2', '15')->setCellValue('E2', '2')->setCellValue('F2', 'Individual');
            $sheet->setCellValue('A3', 'polera')->setCellValue('B3', '0.3')->setCellValue('C3', '8')->setCellValue('D3', '12')->setCellValue('E3', '1')->setCellValue('F3', 'Individual');
            foreach ($colLetters as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getSheetView()->setZoomScale(90);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="plantilla_medidas.xlsx"');
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            exit;
        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al generar plantilla: ' . $e->getMessage();
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }
    }

    public function uploadMedidas() {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['medidas_file']) || $_FILES['medidas_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['pc_error'] = 'Error al subir el archivo.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['pc_error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }
        $ext = strtolower(pathinfo($_FILES['medidas_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            $_SESSION['pc_error'] = 'Formato no válido. Solo .xlsx o .xls.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($_FILES['medidas_file']['tmp_name']);
            $ws = $spreadsheet->getActiveSheet();
            $rows = $ws->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
            if (count($rows) < 2) {
                $_SESSION['pc_error'] = 'El archivo no tiene datos.';
                header('Location: ' . URLROOT . '/productcreator');
                exit;
            }
            $headerMap = [];
            foreach (($rows[0] ?? []) as $i => $h) {
                $hn = ColumnMapper::normalizeHeader((string)$h);
                if (in_array($hn, ['categoria', 'categoría'])) $headerMap['categoria'] = $i;
                elseif (strpos($hn, 'peso') !== false) $headerMap['peso_kg'] = $i;
                elseif (strpos($hn, 'alto') !== false) $headerMap['alto_cm'] = $i;
                elseif (strpos($hn, 'ancho') !== false) $headerMap['ancho_cm'] = $i;
                elseif (strpos($hn, 'grueso') !== false || strpos($hn, 'grosor') !== false || $hn === 'profundidad') $headerMap['grueso_cm'] = $i;
                elseif (strpos($hn, 'variante') !== false || $hn === 'tipo') $headerMap['variante'] = $i;
            }
            if (!isset($headerMap['categoria'])) {
                $_SESSION['pc_error'] = 'Columna "categoria" no encontrada en el archivo.';
                header('Location: ' . URLROOT . '/productcreator');
                exit;
            }
            $db = $this->db();
            $stmt = $db->query("INSERT INTO product_medidas (categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante) VALUES (?, ?, ?, ?, ?, ?)");
            $inserted = 0; $skipped = 0;
            for ($r = 1; $r < count($rows); $r++) {
                $row = $rows[$r];
                $cat = mb_strtolower(trim((string)($row[$headerMap['categoria']] ?? '')));
                if ($cat === '') { $skipped++; continue; }
                $peso = trim((string)($row[$headerMap['peso_kg']] ?? ''));
                $alto = trim((string)($row[$headerMap['alto_cm']] ?? ''));
                $ancho = trim((string)($row[$headerMap['ancho_cm']] ?? ''));
                $grueso = trim((string)($row[$headerMap['grueso_cm']] ?? ''));
                $variante = trim((string)($row[$headerMap['variante']] ?? 'Individual'));
                try {
                    $stmt->execute([$cat, $peso, $alto, $ancho, $grueso, $variante]);
                    $inserted++;
                } catch (\Exception $e) {
                    $skipped++;
                }
            }
            $_SESSION['pc_success'] = "Medidas importadas: $inserted insertadas, $skipped omitidas.";
        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al procesar archivo: ' . $e->getMessage();
        }
        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }

    private function parseMedidaFloat($val): ?float {
        if ($val === null || $val === '' || $val === 'NULL') return null;
        $v = str_replace(',', '.', trim((string)$val));
        return is_numeric($v) ? (float)$v : null;
    }

    public function cleanProducts() {
        $this->requireAuth();

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['pc_error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }

        try {
            $db = $this->db();
            $stmt = $db->query("TRUNCATE TABLE products_master");
            $stmt->execute();
            $_SESSION['pc_success'] = 'Tabla products_master limpiada correctamente.';
        } catch (\Exception $e) {
            $_SESSION['pc_error'] = 'Error al limpiar: ' . $e->getMessage();
        }
        unset($_SESSION['pc_prompt_clean']);
        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }

    public function creadorMedidas() {
        $this->requireAuth();
        $db = $this->db();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf_token'] ?? '';
            if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
                $_SESSION['pc_error'] = 'Token inválido.';
                header('Location: ' . URLROOT . '/productcreator');
                exit;
            }

            $categoria = mb_strtolower(trim($_POST['categoria'] ?? ''));
            $peso = trim($_POST['peso_kg'] ?? '');
            $alto = trim($_POST['alto_cm'] ?? '');
            $ancho = trim($_POST['ancho_cm'] ?? '');
            $grueso = trim($_POST['grueso_cm'] ?? '');
            $variante = trim($_POST['variante'] ?? 'Individual');

            if (empty($categoria)) {
                $_SESSION['pc_error'] = 'La categoría es requerida.';
            } else {
                $stmt = $db->query("INSERT INTO product_medidas (categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoria, $peso, $alto, $ancho, $grueso, $variante]);
                $_SESSION['pc_success'] = "Medida para '$categoria' agregada correctamente.";
            }

            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }
    }

    public function creadorDeleteMedida($id) {
        $this->requireAuth();
        if (empty($id)) {
            $_SESSION['pc_error'] = 'ID no válido.';
            header('Location: ' . URLROOT . '/productcreator');
            exit;
        }
        $db = $this->db();
        $stmt = $db->query("DELETE FROM product_medidas WHERE id = ?");
        $stmt->execute([(int)$id]);
        $_SESSION['pc_info'] = 'Medida eliminada.';
        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }

    public function clearSession() {
        $this->requireAuth();
        $keys = ['pc_preview', 'pc_data_rows', 'pc_detected', 'pc_template_mapping', 'pc_download_ready', 'pc_orig_filename', 'pc_variants', 'pc_variants_meta', 'pc_prompt_clean', 'pc_guide_lookup'];
        foreach ($keys as $k) unset($_SESSION[$k]);
        $_SESSION['pc_success'] = 'Sesión limpiada.';
        header('Location: ' . URLROOT . '/productcreator');
        exit;
    }
}
