<?php
require_once __DIR__ . '/../Helpers/IconHelper.php';

class WebproductsController extends Controller {

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        $colMapper = dirname(__DIR__) . '/Helpers/ColumnMapper.php';
        if (file_exists($colMapper)) {
            require_once $colMapper;
        }
        $this->checkSessionValidity();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }
    }

    public function index() {
        $page_title  = 'Creación Web';
        $page_description = 'Genera plantillas WooCommerce a partir de tus datos de productos.';
        $current_nav = 'webproducts';

        if (!empty($_GET['reset'])) {
            $batch = $_SESSION['web_data_batch'] ?? '';
            if (!empty($batch)) {
                $this->db()->query("DELETE FROM web_products_source WHERE upload_batch = ?")->execute([$batch]);
            }
            foreach (array_keys($_SESSION) as $k) {
                if (str_starts_with($k, 'web_')) unset($_SESSION[$k]);
            }
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $dataBatch = $_SESSION['web_data_batch'] ?? '';
        $filledBatch = $_SESSION['web_filled_batch'] ?? '';
        $preview = [];

        if (!empty($dataBatch)) {
            $stmt = $this->db()->query("SELECT COUNT(*) as cnt FROM web_products_source WHERE upload_batch = ?");
            $stmt->execute([$dataBatch]);
            $count = (int)$stmt->fetch()->cnt;

            // Stale session: batch stored in session but no DB records — auto-clean
            if ($count === 0) {
                foreach (array_keys($_SESSION) as $k) {
                    if (str_starts_with($k, 'web_')) unset($_SESSION[$k]);
                }
                $dataBatch = '';
                $filledBatch = '';
                $count = 0;
            } else {
                $stmt2 = $this->db()->query("SELECT id, sku, nombre, categoria, marca, talla, color, stock FROM web_products_source WHERE upload_batch = ? AND parent_sku IS NULL ORDER BY row_index LIMIT 20");
                $stmt2->execute([$dataBatch]);
                $preview = $stmt2->fetchAll();
            }
        } else {
            $count = 0;
        }

        // Validate filled batch: session says file exists but it may have been cleaned
        if (!empty($filledBatch)) {
            $outputDir = dirname(__DIR__, 2) . '/app/temp/webproducts/';
            $xlsxPath = $outputDir . 'filled_' . $filledBatch . '.xlsx';
            if (!file_exists($xlsxPath)) {
                unset($_SESSION['web_filled_batch']);
                $filledBatch = '';
            }
        }

        $uploadIssues = $_SESSION['web_upload_issues'] ?? [];
        $templateWarnings = $_SESSION['web_template_warnings'] ?? [];
        $reviewIssues = $_SESSION['web_review_issues'] ?? [];
        $reviewFilas = $_SESSION['web_review_filas'] ?? [];
        $reviewUploadedBatch = $_SESSION['web_review_uploaded_batch'] ?? '';
        $reviewUploadedIssues = $_SESSION['web_review_uploaded_issues'] ?? [];
        $reviewUploadedFilas = $_SESSION['web_review_uploaded_filas'] ?? [];

        unset($_SESSION['web_upload_issues'], $_SESSION['web_template_warnings']);
        unset($_SESSION['web_review_issues'], $_SESSION['web_review_filas']);
        unset($_SESSION['web_review_uploaded_issues'], $_SESSION['web_review_uploaded_filas']);

        $hasFilled = !empty($filledBatch);
        $hasReview = $hasFilled && !empty($reviewIssues);

        $data = [
            'csrf_token'               => $_SESSION['csrf_token'] ?? '',
            'data_batch'               => $dataBatch,
            'data_count'               => $count,
            'data_preview'             => $preview,
            'filled_batch'             => $filledBatch,
            'upload_issues'            => $uploadIssues,
            'template_warnings'        => $templateWarnings,
            'review_issues'            => $reviewIssues,
            'review_filas'             => $reviewFilas,
            'review_uploaded_batch'    => $reviewUploadedBatch,
            'review_uploaded_issues'   => $reviewUploadedIssues,
            'review_uploaded_filas'    => $reviewUploadedFilas,
        ];
        $view_content = __DIR__ . '/../Views/webproducts/content-index.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function review() {
        $page_title  = 'Revisar plantillas';
        $page_description = 'Sube y revisa plantillas WooCommerce en busca de errores.';
        $current_nav = 'webproducts-review';

        $reviewUploadedBatch = $_SESSION['web_review_uploaded_batch'] ?? '';
        $reviewUploadedIssues = $_SESSION['web_review_uploaded_issues'] ?? [];
        $reviewUploadedFilas = $_SESSION['web_review_uploaded_filas'] ?? [];

        $data = [
            'csrf_token'             => $_SESSION['csrf_token'] ?? '',
            'review_uploaded_batch'  => $reviewUploadedBatch,
            'review_uploaded_issues' => $reviewUploadedIssues,
            'review_uploaded_filas'  => $reviewUploadedFilas,
        ];
        $view_content = __DIR__ . '/../Views/webproducts/content-review.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function uploadData() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['data_file']) || $_FILES['data_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Error al subir el archivo.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        if ($_FILES['data_file']['size'] > 10 * 1024 * 1024) {
            $_SESSION['error'] = 'Archivo demasiado grande. Máximo 10 MB.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['data_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            $_SESSION['error'] = 'Formato no válido. Solo .xlsx y .xls.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        try {
            ini_set('memory_limit', '256M');

            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($_FILES['data_file']['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if (count($rows) < 2) {
                throw new Exception('El archivo no tiene datos.');
            }

            $headers = $rows[0];
            $map = ColumnMapper::buildMapFromRow($headers);
            $headerRow = ColumnMapper::detectHeaderRow($rows);
            $dataStart = $headerRow + 1;

            $db = $this->db();
            $batch = bin2hex(random_bytes(16));
            $count = 0;
            $issues = [];

            for ($i = $dataStart; $i < count($rows); $i++) {
                $row = $rows[$i];
                $rowIssues = [];
                $hasData = false;
                foreach ($row as $c) {
                    if ($c !== null && $c !== '' && $c !== 'NULL') { $hasData = true; break; }
                }
                if (!$hasData) continue;

                $sku    = trim($this->getField($row, $map, ['sku', 'codigo', 'cod', 'id', 'referencia']));
                $nombre = trim($this->getField($row, $map, ['nombre', 'name', 'producto', 'titulo', 'title']));
                $desc   = trim($this->getField($row, $map, ['descripcion', 'description', 'detalle']));
                $marca  = trim($this->getField($row, $map, ['marca', 'brand', 'marcas']));
                $talla  = trim($this->getField($row, $map, ['talla', 'size', 'talle', 'medida', 'medidas']));
                $color  = trim($this->getField($row, $map, ['color', 'colour']));
                $stock  = $this->parseInt($this->getField($row, $map, ['stock', 'inventario', 'inventory', 'cantidad', 'qty']));

                if (empty($sku) && empty($nombre)) continue;

                $skuOriginal = $sku;

                // Uppercase SKU, talla, color, desc (except nombre, categorías, marca)
                $sku    = mb_strtoupper($sku);
                $talla  = mb_strtoupper($talla);
                $color  = mb_strtoupper($color);
                $desc   = mb_strtoupper($desc);

                // Auto-generate descripción corta: first complete sentence, min 30 chars
                $descCorta = '';
                if (!empty($desc)) {
                    $clean = strip_tags($desc);
                    $clean = preg_replace('/\s+/', ' ', $clean);
                    $dotPos = mb_strpos($clean, '.');
                    if ($dotPos !== false) {
                        $candidate = mb_substr($clean, 0, $dotPos + 1);
                        if (mb_strlen($candidate) < 30) {
                            $rest = mb_substr($clean, $dotPos + 1);
                            $nextDot = mb_strpos($rest, '.');
                            while ($nextDot !== false && mb_strlen($candidate . mb_substr($rest, 0, $nextDot + 1)) < 30) {
                                $candidate .= mb_substr($rest, 0, $nextDot + 1);
                                $rest = mb_substr($rest, $nextDot + 1);
                                $nextDot = mb_strpos($rest, '.');
                            }
                            if ($nextDot !== false) {
                                $candidate .= mb_substr($rest, 0, $nextDot + 1);
                            } else {
                                $candidate .= $rest;
                            }
                        }
                        $descCorta = trim($candidate);
                    } else {
                        $descCorta = mb_substr($clean, 0, 200);
                        $lastSpace = mb_strrpos($descCorta, ' ');
                        if ($lastSpace > 60) $descCorta = mb_substr($descCorta, 0, $lastSpace);
                        if (mb_strlen($clean) > 200) $descCorta .= '...';
                    }
                }

                // Track issues
                if (empty($sku)) $rowIssues[] = 'Sin SKU';
                if (empty($nombre)) $rowIssues[] = 'Sin nombre';
                if (empty($desc)) $rowIssues[] = 'Sin descripción';
                if (empty($marca)) $rowIssues[] = 'Sin marca';
                if (empty($talla) || $talla === 'T/U') $rowIssues[] = 'Sin talla';
                if (empty($color)) $rowIssues[] = 'Sin color';
                if (empty($stock) || $stock === 0) $rowIssues[] = 'Sin inventario';

                // Resolve category from DB hierarchy
                $catResuelta = $this->resolveCategory($nombre, $marca);
                if (empty($catResuelta)) $rowIssues[] = 'Categoría no detectada';

                // Detect if this is a parent (multiple sizes or colors) or a child/variation
                $tieneMultiplesTallas = !empty($talla) && (strpos($talla, ',') !== false || strpos($talla, '/') !== false);
                $tieneMultiplesColores = !empty($color) && (strpos($color, ',') !== false);

                // Single size → set talla to T/U
                if (!empty($talla) && !$tieneMultiplesTallas) {
                    $talla = 'T/U';
                }

                $tipo = ($tieneMultiplesTallas || $tieneMultiplesColores) ? 'variable' : 'simple';
                $parentSku = null;

                // Check if SKU looks like a child (has color+size suffix)
                $abbrPattern = '/-[A-Z]{3,4}-[A-Z0-9\/_]+$/i';
                if (preg_match($abbrPattern, $sku)) {
                    $tipo = 'variation';
                }

                if (!empty($rowIssues)) {
                    $issues[] = ['sku' => $skuOriginal, 'nombre' => $nombre, 'problemas' => $rowIssues];
                }

                $raw = json_encode([
                    'sku' => $sku, 'nombre' => $nombre, 'descripcion' => $desc,
                    'categoria' => $catResuelta, 'marca' => $marca, 'talla' => $talla,
                    'color' => $color, 'stock' => $stock,
                ], JSON_UNESCAPED_UNICODE);

                $stmt = $db->query("INSERT INTO web_products_source 
                    (upload_batch, row_index, tipo, sku, parent_sku, nombre, descripcion_corta, descripcion, stock, categoria, marca, color, talla, raw_data)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$batch, $i, $tipo, $sku, $parentSku, $nombre, $descCorta, $desc, $stock, $catResuelta, $marca, $color, $talla, $raw]);
                $count++;
            }

            if ($count === 0) throw new Exception('No se encontraron filas con datos válidos.');

            unset($_SESSION['web_filled_batch']);
            $_SESSION['web_data_batch'] = $batch;
            $_SESSION['web_upload_issues'] = $issues;
            $_SESSION['success'] = "$count productos cargados correctamente.";

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al procesar datos: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/webproducts');
        exit;
    }

    public function uploadTemplate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Error al subir la plantilla.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        if ($_FILES['template_file']['size'] > 10 * 1024 * 1024) {
            $_SESSION['error'] = 'Archivo demasiado grande. Máximo 10 MB.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $dataBatch = $_SESSION['web_data_batch'] ?? '';
        if (empty($dataBatch)) {
            $_SESSION['error'] = 'Primero debes cargar los datos.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['template_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            $_SESSION['error'] = 'Formato no válido. Solo .xlsx.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        try {
            ini_set('memory_limit', '512M');

            // Load data from DB
            $db = $this->db();
            $stmt = $db->query("SELECT * FROM web_products_source WHERE upload_batch = ? ORDER BY row_index");
            $stmt->execute([$dataBatch]);
            $products = $stmt->fetchAll();

            if (empty($products)) throw new Exception('No hay productos cargados.');

            // Load medidas from DB
            $medidasList = $this->getMedidas();

            // Generate color→abbreviation map
            $colorAbbrs = [
                'negro' => 'NEG', 'blanco' => 'BLA', 'gris' => 'GRI', 'azul' => 'AZU',
                'rojo' => 'ROJ', 'verde' => 'VER', 'amarillo' => 'AMA', 'naranjo' => 'NAR',
                'morado' => 'MOR', 'rosado' => 'ROS', 'celeste' => 'CEL', 'beige' => 'BEI',
                'cafe' => 'CAF', 'marino' => 'MAR', 'petroleo' => 'PET', 'burdeo' => 'BUR',
                'melange' => 'MEL', 'vino' => 'VIN', 'mostaza' => 'MOS', ' coral' => 'COR',
                'lila' => 'LIL', 'turquesa' => 'TUR', 'salmon' => 'SAL', 'fucsia' => 'FUC',
                'dorado' => 'DOR', 'plateado' => 'PLA', 'transparente' => 'TRA',
                'multicolor' => 'MUL', 'estam' => 'EST', 'print' => 'PRI',
            ];

            // Load template
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($_FILES['template_file']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2; // Start at row 2 (row 1 is header)
            $warnings = [];

            foreach ($products as $p) {
                $sku = $p->sku ?? '';
                if (empty($sku)) continue;

                $tallas = [];
                $colores = [];

                // Parse sizes
                $rawTallas = $p->talla ?? '';
                if (!empty($rawTallas)) {
                    $tallas = array_map('trim', explode(',', str_replace('/', ',', $rawTallas)));
                }

                // Parse colors
                $rawColores = $p->color ?? '';
                if (!empty($rawColores)) {
                    $colores = array_map('trim', explode(',', $rawColores));
                }

                $esVariable = (count($tallas) > 1 || count($colores) > 1) && $p->tipo !== 'variation';
                $esVariation = $p->tipo === 'variation';

                $medidas = $this->matchMedidas($p->nombre ?? '', $p->categoria ?? '', $medidasList);
                if (empty($medidas['peso']) && empty($medidas['largo']) && empty($medidas['ancho']) && empty($medidas['alto'])) {
                    $warnings[] = ['sku' => $sku, 'tipo' => 'medidas', 'mensaje' => 'Sin medidas asignadas'];
                }
                $catJerarquica = $this->buildCategoryHierarchy($p->categoria ?? '', $p->nombre ?? '');
                if (empty($p->categoria) && empty($catJerarquica)) {
                    $warnings[] = ['sku' => $sku, 'tipo' => 'categoria', 'mensaje' => 'Categoría no detectada'];
                }

                if ($esVariable) {
                    // Parent row
                    $this->writeWooRow($sheet, $rowNum, [
                        'tipo' => 'variable',
                        'sku' => $sku,
                        'nombre' => $p->nombre ?? '',
                        'desc_corta' => $p->descripcion_corta ?? '',
                        'desc' => $p->descripcion ?? '',
                        'stock' => $p->stock,
                        'precio' => '2',
                        'categoria' => $catJerarquica,
                        'marca' => $p->marca ?? '',
                        'tallas' => implode(',', $tallas),
                        'colores' => implode(',', $colores),
                        'parent_sku' => null,
                        'peso' => $medidas['peso'],
                        'largo' => $medidas['largo'],
                        'ancho' => $medidas['ancho'],
                        'alto' => $medidas['alto'],
                    ]);
                    $rowNum++;

                    // Children rows - distribute stock equitably
                    $totalStock = (int)($p->stock ?? 0);
                    $numChildren = count($tallas) * count($colores);
                    $childIter = 0;
                    foreach ($colores as $color) {
                        $abbr = $this->getColorAbbr($color, $colorAbbrs);
                        foreach ($tallas as $talla) {
                            $childIter++;
                            $childSku = $sku . '-' . $abbr . '-' . str_replace('/', '_', $talla);
                            // Distribute: base for all, remainder on last
                            $baseStock = intdiv($totalStock, $numChildren);
                            $childStock = ($childIter === $numChildren) ? ($baseStock + ($totalStock % $numChildren)) : $baseStock;
                            $this->writeWooRow($sheet, $rowNum, [
                                'tipo' => 'variation',
                                'sku' => $childSku,
                                'nombre' => $p->nombre ?? '',
                                'desc_corta' => $p->descripcion_corta ?? '',
                                'desc' => $p->descripcion ?? '',
                                'stock' => $childStock,
                                'precio' => '2',
                                'categoria' => $catJerarquica,
                                'marca' => $p->marca ?? '',
                                'talla' => $talla,
                                'color' => $color,
                                'parent_sku' => $sku,
                                'peso' => $medidas['peso'],
                                'largo' => $medidas['largo'],
                                'ancho' => $medidas['ancho'],
                                'alto' => $medidas['alto'],
                            ]);
                            $rowNum++;
                        }
                    }
                    // Warn if stock is too low per child
                    if ($totalStock > 0 && $numChildren > 1 && $totalStock < $numChildren * 3) {
                        $warnings[] = ['sku' => $sku, 'tipo' => 'stock', 'mensaje' => "Pocas existencias ($totalStock para $numChildren variantes)"];
                    }
                } elseif ($esVariation) {
                    // Already a variation - single row
                    $this->writeWooRow($sheet, $rowNum, [
                        'tipo' => 'variation',
                        'sku' => $sku,
                        'nombre' => $p->nombre ?? '',
                        'desc_corta' => $p->descripcion_corta ?? '',
                        'desc' => $p->descripcion ?? '',
                        'stock' => $p->stock,
                        'precio' => '2',
                        'categoria' => $catJerarquica,
                        'marca' => $p->marca ?? '',
                        'talla' => $p->talla ?? '',
                        'color' => $p->color ?? '',
                        'parent_sku' => $p->parent_sku,
                        'peso' => $medidas['peso'],
                        'largo' => $medidas['largo'],
                        'ancho' => $medidas['ancho'],
                        'alto' => $medidas['alto'],
                    ]);
                    $rowNum++;
                } else {
                    // Simple product
                    $this->writeWooRow($sheet, $rowNum, [
                        'tipo' => 'simple',
                        'sku' => $sku,
                        'nombre' => $p->nombre ?? '',
                        'desc_corta' => $p->descripcion_corta ?? '',
                        'desc' => $p->descripcion ?? '',
                        'stock' => $p->stock,
                        'precio' => '2',
                        'categoria' => $catJerarquica,
                        'marca' => $p->marca ?? '',
                        'tallas' => $p->talla ?? '',
                        'colores' => $p->color ?? '',
                        'parent_sku' => null,
                        'peso' => $medidas['peso'],
                        'largo' => $medidas['largo'],
                        'ancho' => $medidas['ancho'],
                        'alto' => $medidas['alto'],
                    ]);
                    $rowNum++;
                }
            }

            // Remove remaining rows
            $highestRow = $sheet->getHighestRow();
            if ($rowNum <= $highestRow) {
                $sheet->removeRow($rowNum, $highestRow - $rowNum + 1);
            }

            // Save file
            $outputDir = dirname(__DIR__, 2) . '/app/temp/webproducts/';
            if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

            $filledBatch = bin2hex(random_bytes(16));
            $outputPath = $outputDir . 'filled_' . $filledBatch . '.xlsx';

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($outputPath);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);

            $_SESSION['web_filled_batch'] = $filledBatch;
            $_SESSION['web_template_warnings'] = $warnings;
            $totalWarnings = count($warnings);
            $msg = 'Plantilla generada correctamente con ' . ($rowNum - 2) . ' productos.';
            if ($totalWarnings > 0) $msg .= " ($totalWarnings advertencias)";
            $_SESSION['success'] = $msg;

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al generar plantilla: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/webproducts');
        exit;
    }

    public function downloadSkeleton() {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['SKU', 'Nombre', 'Descripción', 'Marcas', 'Tallas', 'Color', 'Inventario'];
            foreach ($headers as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col . '1', $h);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $sheet->getStyle($col . '1')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFF00');
            }

            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="plantilla_datos.xlsx"');

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al generar plantilla: ' . $e->getMessage();
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }
    }

    public function reviewTemplate() {
        $this->requireCsrf();
        $batch = $_SESSION['web_filled_batch'] ?? '';
        if (empty($batch)) {
            $_SESSION['error'] = 'No hay plantilla generada para revisar.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $outputDir = dirname(__DIR__, 2) . '/app/temp/webproducts/';
        $xlsxPath = $outputDir . 'filled_' . $batch . '.xlsx';
        if (!file_exists($xlsxPath)) {
            $_SESSION['error'] = 'El archivo de plantilla no existe.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);

            $result = $this->runReview($rows);
            $issues = $result['issues'];

            $_SESSION['web_review_issues'] = $issues;
            $_SESSION['web_review_filas'] = $result['filas'];
            $total = count($issues);
            $fixable = count(array_filter($issues, fn($i) => $i['arreglable']));
            if ($total > 0) {
                $_SESSION['info'] = "Revisión completada: $total incidencia(s) detectadas, $fixable corregible(s).";
            } else {
                $_SESSION['success'] = 'Revisión completada: sin incidencias.';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al revisar plantilla: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/webproducts');
        exit;
    }

    public function fixTemplate() {
        $this->requireCsrf();
        $batch = $_SESSION['web_filled_batch'] ?? '';
        if (empty($batch)) {
            $_SESSION['error'] = 'No hay plantilla para corregir.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $outputDir = dirname(__DIR__, 2) . '/app/temp/webproducts/';
        $xlsxPath = $outputDir . 'filled_' . $batch . '.xlsx';
        if (!file_exists($xlsxPath)) {
            $_SESSION['error'] = 'El archivo de plantilla no existe.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            $highestRow = $sheet->getHighestRow();

            $fixed = 0;
            $removedRows = [];
            $seenSkus = [];

            // Fix detected issues
            $issues = $_SESSION['web_review_issues'] ?? [];
            $fixed += $this->applyFixableColumns($sheet, $issues);

            // First pass: mark empty rows and duplicate SKUs for removal
            for ($i = 1; $i < count($rows); $i++) {
                $rowNum = $i + 1;
                $row = $rows[$i];

                $tipo = trim((string)($row[1] ?? ''));
                $sku  = trim((string)($row[2] ?? ''));
                $nombre = trim((string)($row[4] ?? ''));

                if (empty($tipo) && empty($sku) && empty($nombre)) {
                    $removedRows[] = $rowNum;
                    $fixed++;
                    continue;
                }

                $skuClean = mb_strtoupper($sku);
                if (!empty($skuClean)) {
                    if (isset($seenSkus[$skuClean])) {
                        $removedRows[] = $rowNum;
                        $fixed++;
                        continue;
                    }
                    $seenSkus[$skuClean] = true;
                }

                if (empty($tipo) && !empty($sku)) {
                    $abbrPattern = '/-[A-Z]{3,4}-[A-Z0-9\/_]+$/i';
                    $sheet->setCellValue('B' . $rowNum, preg_match($abbrPattern, $sku) ? 'variation' : 'simple');
                    $fixed++;
                }

                $precio = trim((string)($row[26] ?? ''));
                if (empty($precio)) {
                    $sheet->setCellValue('AA' . $rowNum, 2);
                    $fixed++;
                }
            }

            rsort($removedRows);
            foreach ($removedRows as $r) {
                $sheet->removeRow($r, 1);
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($xlsxPath);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);

            unset($_SESSION['web_review_issues']);
            $_SESSION['success'] = "Correcciones aplicadas: $fixed incidencia(s) resuelta(s). Revisión actualizada.";
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al corregir plantilla: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/webproducts/review');
        exit;
    }

    public function uploadForReview() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['review_file']) || $_FILES['review_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Error al subir el archivo.';
            header('Location: ' . URLROOT . '/webproducts/review');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/webproducts/review');
            exit;
        }

        if ($_FILES['review_file']['size'] > 10 * 1024 * 1024) {
            $_SESSION['error'] = 'Archivo demasiado grande. Máximo 10 MB.';
            header('Location: ' . URLROOT . '/webproducts/review');
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['review_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            $_SESSION['error'] = 'Formato no válido. Solo .xlsx.';
            header('Location: ' . URLROOT . '/webproducts/review');
            exit;
        }

        try {
            ini_set('memory_limit', '512M');

            $outputDir = dirname(__DIR__, 2) . '/app/temp/webproducts/';
            if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

            $reviewBatch = bin2hex(random_bytes(16));
            $destPath = $outputDir . 'review_' . $reviewBatch . '.xlsx';
            move_uploaded_file($_FILES['review_file']['tmp_name'], $destPath);

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($destPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);

            $result = $this->runReview($rows);
            $issues = $result['issues'];

            unset($_SESSION['web_review_uploaded_issues']);
            unset($_SESSION['web_review_uploaded_filas']);
            $_SESSION['web_review_uploaded_batch'] = $reviewBatch;
            $_SESSION['web_review_uploaded_issues'] = $issues;
            $_SESSION['web_review_uploaded_filas'] = $result['filas'];
            $total = count($issues);
            $fixable = count(array_filter($issues, fn($i) => $i['arreglable']));
            if ($total > 0) {
                $_SESSION['info'] = "Revisión completada: $total incidencia(s) detectadas, $fixable corregible(s).";
            } else {
                $_SESSION['success'] = 'Revisión completada: sin incidencias.';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al revisar plantilla: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/webproducts/review');
        exit;
    }

    public function fixUploadedTemplate() {
        $this->requireCsrf();
        $reviewBatch = $_SESSION['web_review_uploaded_batch'] ?? '';
        if (empty($reviewBatch)) {
            $_SESSION['error'] = 'No hay plantilla para corregir.';
            header('Location: ' . URLROOT . '/webproducts/review');
            exit;
        }

        $outputDir = dirname(__DIR__, 2) . '/app/temp/webproducts/';
        $xlsxPath = $outputDir . 'review_' . $reviewBatch . '.xlsx';
        if (!file_exists($xlsxPath)) {
            $_SESSION['error'] = 'El archivo de plantilla no existe.';
            header('Location: ' . URLROOT . '/webproducts/review');
            exit;
        }

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            $fixed = 0;
            $removedRows = [];
            $seenSkus = [];

            // Fix detected issues
            $issues = $_SESSION['web_review_uploaded_issues'] ?? [];
            $fixed += $this->applyFixableColumns($sheet, $issues);

            for ($i = 1; $i < count($rows); $i++) {
                $rowNum = $i + 1;
                $row = $rows[$i];

                $tipo = trim((string)($row[1] ?? ''));
                $sku  = trim((string)($row[2] ?? ''));
                $nombre = trim((string)($row[4] ?? ''));

                if (empty($tipo) && empty($sku) && empty($nombre)) {
                    $removedRows[] = $rowNum;
                    $fixed++;
                    continue;
                }

                $skuClean = mb_strtoupper($sku);
                if (!empty($skuClean)) {
                    if (isset($seenSkus[$skuClean])) {
                        $removedRows[] = $rowNum;
                        $fixed++;
                        continue;
                    }
                    $seenSkus[$skuClean] = true;
                }

                if (empty($tipo) && !empty($sku)) {
                    $abbrPattern = '/-[A-Z]{3,4}-[A-Z0-9\/_]+$/i';
                    $sheet->setCellValue('B' . $rowNum, preg_match($abbrPattern, $sku) ? 'variation' : 'simple');
                    $fixed++;
                }

                $precio = trim((string)($row[26] ?? ''));
                if (empty($precio)) {
                    $sheet->setCellValue('AA' . $rowNum, 2);
                    $fixed++;
                }
            }

            rsort($removedRows);
            foreach ($removedRows as $r) {
                $sheet->removeRow($r, 1);
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($xlsxPath);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);

            // Re-run review on fixed file
            $reader2 = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $spreadsheet2 = $reader2->load($xlsxPath);
            $sheet2 = $spreadsheet2->getActiveSheet();
            $rows2 = $sheet2->toArray(null, true, true, false);
            $spreadsheet2->disconnectWorksheets();
            unset($spreadsheet2, $reader2);

            $result = $this->runReview($rows2);

            unset($_SESSION['web_review_uploaded_issues']);
            unset($_SESSION['web_review_uploaded_filas']);
            $_SESSION['web_review_uploaded_issues'] = $result['issues'];
            $_SESSION['web_review_uploaded_filas'] = $result['filas'];
            $_SESSION['success'] = "Correcciones aplicadas: $fixed incidencia(s) resuelta(s).";
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al corregir plantilla: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/webproducts');
        exit;
    }

    public function download($format, $batch = '') {
        if (empty($batch)) {
            $batch = $_SESSION['web_filled_batch'] ?? '';
        }
        if (empty($batch)) {
            $_SESSION['error'] = 'No hay plantilla para descargar.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $outputDir = dirname(__DIR__, 2) . '/app/temp/webproducts/';
        $label = 'generacion';
        $xlsxPath = $outputDir . 'filled_' . $batch . '.xlsx';
        if (!file_exists($xlsxPath)) {
            $xlsxPath = $outputDir . 'review_' . $batch . '.xlsx';
            $label = 'revision';
        }

        if (!file_exists($xlsxPath)) {
            $_SESSION['error'] = 'El archivo ya no está disponible.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = "{$label}_{$timestamp}.xlsx";

        if ($format === 'xlsx') {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($xlsxPath));
            readfile($xlsxPath);
            unlink($xlsxPath);
            exit;
        }

        if ($format === 'csv') {
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                $spreadsheet = $reader->load($xlsxPath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                $csvFilename = "{$label}_{$timestamp}.csv";
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $csvFilename . '"');

                $output = fopen('php://output', 'w');
                fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel

                foreach ($rows as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
                exit;
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error al generar CSV: ' . $e->getMessage();
                header('Location: ' . URLROOT . '/webproducts');
                exit;
            }
        }

        $_SESSION['error'] = 'Formato no válido.';
        header('Location: ' . URLROOT . '/webproducts');
        exit;
    }

    public function addManualProduct() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $dataBatch = $_SESSION['web_data_batch'] ?? '';
        if (empty($dataBatch)) {
            $dataBatch = bin2hex(random_bytes(16));
            $_SESSION['web_data_batch'] = $dataBatch;
        }

        $sku    = mb_strtoupper(trim($_POST['sku'] ?? ''));
        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = mb_strtoupper(trim($_POST['descripcion'] ?? ''));
        $marca  = trim($_POST['marca'] ?? '');
        $talla  = mb_strtoupper(trim($_POST['talla'] ?? ''));
        $color  = mb_strtoupper(trim($_POST['color'] ?? ''));
        $stock  = $this->parseInt($_POST['stock'] ?? 0);

        if (empty($sku) && empty($nombre)) {
            $_SESSION['error'] = 'SKU o Nombre son requeridos.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        // Resolve category
        $catResuelta = $this->resolveCategory($nombre, $marca);

        // Detect type
        $tieneMultiplesTallas = strpos($talla, ',') !== false || strpos($talla, '/') !== false;
        $tieneMultiplesColores = strpos($color, ',') !== false;

        if (!empty($talla) && !$tieneMultiplesTallas) {
            $talla = 'T/U';
        }

        $tipo = ($tieneMultiplesTallas || $tieneMultiplesColores) ? 'variable' : 'simple';

        $abbrPattern = '/-[A-Z]{3,4}-[A-Z0-9\/_]+$/i';
        if (preg_match($abbrPattern, $sku)) {
            $tipo = 'variation';
        }

        // Descripción corta: first sentence
        $descCorta = '';
        if (!empty($desc)) {
            $clean = preg_replace('/\s+/', ' ', strip_tags($desc));
            $dotPos = mb_strpos($clean, '.');
            if ($dotPos !== false) {
                $candidate = mb_substr($clean, 0, $dotPos + 1);
                if (mb_strlen($candidate) < 30) {
                    $rest = mb_substr($clean, $dotPos + 1);
                    $nextDot = mb_strpos($rest, '.');
                    while ($nextDot !== false && mb_strlen($candidate . mb_substr($rest, 0, $nextDot + 1)) < 30) {
                        $candidate .= mb_substr($rest, 0, $nextDot + 1);
                        $rest = mb_substr($rest, $nextDot + 1);
                        $nextDot = mb_strpos($rest, '.');
                    }
                    $candidate .= ($nextDot !== false) ? mb_substr($rest, 0, $nextDot + 1) : $rest;
                }
                $descCorta = trim($candidate);
            } else {
                $descCorta = mb_substr($clean, 0, 200);
                $lastSpace = mb_strrpos($descCorta, ' ');
                if ($lastSpace > 60) $descCorta = mb_substr($descCorta, 0, $lastSpace);
                if (mb_strlen($clean) > 200) $descCorta .= '...';
            }
        }

        $raw = json_encode([
            'sku' => $sku, 'nombre' => $nombre, 'descripcion' => $desc,
            'categoria' => $catResuelta, 'marca' => $marca, 'talla' => $talla,
            'color' => $color, 'stock' => $stock,
        ], JSON_UNESCAPED_UNICODE);

        // Get max row_index for this batch
        $stmt = $this->db()->query("SELECT COALESCE(MAX(row_index), 0) + 1 as next_row FROM web_products_source WHERE upload_batch = ?");
        $stmt->execute([$dataBatch]);
        $nextRow = (int)$stmt->fetch()->next_row;

        $stmt2 = $this->db()->query("INSERT INTO web_products_source 
            (upload_batch, row_index, tipo, sku, parent_sku, nombre, descripcion_corta, descripcion, stock, categoria, marca, color, talla, raw_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->execute([$dataBatch, $nextRow, $tipo, $sku, null, $nombre, $descCorta, $desc, $stock, $catResuelta, $marca, $color, $talla, $raw]);

        unset($_SESSION['web_filled_batch']);
        $_SESSION['success'] = "Producto $sku creado correctamente.";
        header('Location: ' . URLROOT . '/webproducts');
        exit;
    }

    public function deleteProduct($id) {
        $this->requireCsrf();
        $dataBatch = $_SESSION['web_data_batch'] ?? '';
        if (empty($dataBatch) || empty($id)) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }

        $stmt = $this->db()->query("DELETE FROM web_products_source WHERE id = ? AND upload_batch = ?");
        $stmt->execute([$id, $dataBatch]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['info'] = 'Producto eliminado.';
        } else {
            $_SESSION['error'] = 'No se pudo eliminar el producto.';
        }

        unset($_SESSION['web_filled_batch']);
        header('Location: ' . URLROOT . '/webproducts');
        exit;
    }

    public function resetReviewUpload() {
        $this->requireCsrf();
        unset($_SESSION['web_review_uploaded_batch']);
        unset($_SESSION['web_review_uploaded_issues']);
        unset($_SESSION['web_review_uploaded_filas']);
        $_SESSION['info'] = 'Revisión reiniciada. Puedes subir otro archivo.';
        header('Location: ' . URLROOT . '/webproducts/review');
        exit;
    }

    public function resetData() {
        $this->requireCsrf();
        $batch = $_SESSION['web_data_batch'] ?? '';
        if (!empty($batch)) {
            $db = $this->db();
            $db->query("DELETE FROM web_products_source WHERE upload_batch = ?")->execute([$batch]);
        }
        unset($_SESSION['web_data_batch']);
        unset($_SESSION['web_filled_batch']);
        unset($_SESSION['web_upload_issues']);
        unset($_SESSION['web_template_warnings']);
        unset($_SESSION['web_review_issues']);
        unset($_SESSION['web_review_filas']);
        unset($_SESSION['web_review_uploaded_batch']);
        unset($_SESSION['web_review_uploaded_issues']);
        unset($_SESSION['web_review_uploaded_filas']);
        $_SESSION['info'] = 'Datos reiniciados.';
        header('Location: ' . URLROOT . '/webproducts');
        exit;
    }

    private function getField(array $row, array $map, array $synonyms): string {
        foreach ($synonyms as $syn) {
            foreach ($map as $header => $idx) {
                $h = mb_strtolower(trim($header));
                $s = mb_strtolower(trim($syn));
                if ($h === $s || strpos($h, $s) !== false || strpos($s, $h) !== false) {
                    return $row[$idx] ?? '';
                }
            }
        }
        return '';
    }

    private function parsePrice($val): ?float {
        if (empty($val)) return null;
        $v = str_replace(['$', '.', ',', ' '], ['', '', '.', ''], trim((string)$val));
        if (is_numeric($v)) return (float) $v;
        return null;
    }

    private function parseInt($val): ?int {
        if (empty($val)) return null;
        if (is_numeric($val)) return (int) $val;
        return null;
    }

    private function parseFloat($val): ?float {
        if (empty($val)) return null;
        $v = str_replace(['$', '.', ',', ' '], ['', '', '.', ''], trim((string)$val));
        if (is_numeric($v)) return (float) $v;
        return null;
    }

    private function writeWooRow($sheet, int $row, array $d): void {
        $col = function($letter) use ($row) { return $letter . $row; };

        // B: Tipo
        $sheet->setCellValue($col('B'), $d['tipo'] ?? 'simple');
        // C: SKU
        $sheet->setCellValue($col('C'), $d['sku'] ?? '');
        // E: Nombre
        $sheet->setCellValue($col('E'), $d['nombre'] ?? '');
        // F: Publicado
        $sheet->setCellValue($col('F'), 1);
        // G: Destacado
        $sheet->setCellValue($col('G'), 0);
        // H: Visibilidad
        $sheet->setCellValue($col('H'), 'visible');
        // I: Descripción corta
        $sheet->setCellValue($col('I'), $d['desc_corta'] ?? '');
        // J: Descripción
        $sheet->setCellValue($col('J'), $d['desc'] ?? '');
        // M: Estado impuesto
        $sheet->setCellValue($col('M'), 'taxable');
        // N: Clase impuesto (solo variations)
        if (($d['tipo'] ?? '') === 'variation') {
            $sheet->setCellValue($col('N'), 'parent');
        }
        // O: Existencias
        $sheet->setCellValue($col('O'), 1);
        // P: Inventario
        $sheet->setCellValue($col('P'), $d['stock'] ?? 0);
        // R: Reservas agotados
        $sheet->setCellValue($col('R'), 0);
        // S: Vendido individualmente
        $sheet->setCellValue($col('S'), 0);
        // T: Peso (kg)
        if (!empty($d['peso'])) {
            $sheet->setCellValue($col('T'), $d['peso']);
        }
        // U: Longitud (cm)
        if (!empty($d['largo'])) {
            $sheet->setCellValue($col('U'), $d['largo']);
        }
        // V: Anchura (cm)
        if (!empty($d['ancho'])) {
            $sheet->setCellValue($col('V'), $d['ancho']);
        }
        // W: Altura (cm)
        if (!empty($d['alto'])) {
            $sheet->setCellValue($col('W'), $d['alto']);
        }
        // X: Valoraciones
        $sheet->setCellValue($col('X'), 0);
        // AA: Precio normal
        $sheet->setCellValue($col('AA'), $d['precio'] ?? '2');
        // AB: Categorías
        $sheet->setCellValue($col('AB'), $d['categoria'] ?? '');
        // AC: Etiquetas
        $tags = $this->generateTags($d);
        if (!empty($tags)) {
            $sheet->setCellValue($col('AC'), $tags);
        }
        // AH: Superior (solo variations)
        if (!empty($d['parent_sku'])) {
            $sheet->setCellValue($col('AH'), $d['parent_sku']);
        }
        // AO: Marcas
        $sheet->setCellValue($col('AO'), $d['marca'] ?? '');

        // AP: Nombre atributo 1 (always)
        $sheet->setCellValue($col('AP'), 'Talla');
        // AR: Atributo visible 1 (always)
        $sheet->setCellValue($col('AR'), 1);
        // AS: Atributo global 1 (always)
        $sheet->setCellValue($col('AS'), 0);
        // AT: Nombre atributo 2 (always)
        $sheet->setCellValue($col('AT'), 'Color');
        // AV: Atributo visible 2 (always)
        $sheet->setCellValue($col('AV'), 1);
        // AW: Atributo global 2 (always)
        $sheet->setCellValue($col('AW'), 0);

        if (($d['tipo'] ?? '') === 'variable') {
            // AQ: Valor(es) atributo 1
            $sheet->setCellValue($col('AQ'), $d['tallas'] ?? '');
            // AU: Valor(es) atributo 2
            $sheet->setCellValue($col('AU'), $d['colores'] ?? '');
        } else {
            // AQ: Valor(es) atributo 1 (talla individual)
            $sheet->setCellValue($col('AQ'), $d['talla'] ?? '');
            // AU: Valor(es) atributo 2 (color individual)
            $sheet->setCellValue($col('AU'), $d['color'] ?? '');
        }
    }

    private function generateTags(array $d): string {
        $tags = [];
        $nombre = trim($d['nombre'] ?? '');
        $cat = $d['categoria'] ?? '';
        $desc = $d['desc'] ?? '';

        // Tag 1: primera palabra del nombre
        if (!empty($nombre)) {
            $parts = explode(' ', $nombre);
            $first = $parts[0];
            if (!in_array($first, $tags)) $tags[] = $first;
        }

        // Tag 2: género del producto
        $textoGenero = mb_strtolower($cat . ' ' . $nombre);
        $genero = '';
        if (strpos($textoGenero, 'mujer') !== false) $genero = 'Mujer';
        elseif (strpos($textoGenero, 'hombre') !== false) $genero = 'Hombre';
        elseif (strpos($textoGenero, 'niño') !== false || strpos($textoGenero, 'niña') !== false) $genero = 'Niños';
        elseif (strpos($textoGenero, 'bebé') !== false || strpos($textoGenero, 'bebe') !== false) $genero = 'Bebés';
        if (!empty($genero) && !in_array($genero, $tags)) $tags[] = $genero;

        // Tag 3: material desde la descripción
        $material = $this->extractMaterial($desc);
        if (!empty($material) && !in_array($material, $tags)) $tags[] = $material;

        // Tag 4+: categorías como tag
        if (!empty($cat)) {
            $catParts = explode('>', $cat);
            foreach ($catParts as $cp) {
                $cp = trim($cp);
                if (!empty($cp) && !in_array($cp, $tags)) {
                    $tags[] = $cp;
                }
            }
        }

        // Tag comercial según género/producto
        $comercial = '';
        if (strpos($textoGenero, 'mujer') !== false) $comercial = 'Cómodo';
        elseif (strpos($textoGenero, 'hombre') !== false) $comercial = 'Cómodo';
        elseif (strpos($textoGenero, 'niño') !== false || strpos($textoGenero, 'niña') !== false) $comercial = 'Suave';
        elseif (strpos($textoGenero, 'bebé') !== false || strpos($textoGenero, 'bebe') !== false) $comercial = 'Suave';
        else $comercial = 'Cómodo';
        if (!empty($comercial) && !in_array($comercial, $tags)) $tags[] = $comercial;

        // Ensure minimum 3 tags
        if (count($tags) < 3) {
            $fallbacks = ['Prenda', 'Ropa', 'Accesorio'];
            foreach ($fallbacks as $fb) {
                if (!in_array($fb, $tags)) $tags[] = $fb;
                if (count($tags) >= 3) break;
            }
        }

        return implode(', ', $tags);
    }

    private function extractMaterial(string $desc): string {
        if (empty($desc)) return '';
        $descLower = mb_strtolower($desc);
        $materiales = [
            'algodón' => 'Algodón', 'poliéster' => 'Poliéster', 'elastano' => 'Elastano',
            'spandex' => 'Spandex', 'nylon' => 'Nylon', 'licra' => 'Licra',
            'microfibra' => 'Microfibra', 'viscosa' => 'Viscosa', 'seda' => 'Seda',
            'lino' => 'Lino', 'lana' => 'Lana', 'acrílico' => 'Acrílico',
            'bambú' => 'Bambú', 'tencel' => 'Tencel', 'modal' => 'Modal',
            'encaje' => 'Encaje', 'tul' => 'Tul', 'satén' => 'Satén',
            'gasa' => 'Gasa', 'mezclilla' => 'Mezclilla', 'cuero' => 'Cuero',
            'polipiel' => 'Polipiel', 'franela' => 'Franela', 'felpa' => 'Felpa',
            'peluche' => 'Peluche', 'jersey' => 'Jersey', 'rib' => 'Rib',
            'piqué' => 'Piqué', 'pana' => 'Pana', 'terciopelo' => 'Terciopelo',
        ];
        $best = '';
        $bestLen = 0;
        foreach ($materiales as $key => $label) {
            if (strpos($descLower, $key) !== false) {
                $len = mb_strlen($key);
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $best = $label;
                }
            }
        }
        return $best;
    }

    private function getColorAbbr(string $color, array $map): string {
        $c = mb_strtolower(trim($color));

        // Direct lookup
        if (isset($map[$c])) return $map[$c];

        // Multi-word colors: take first 3 letters of first word
        $parts = explode(' ', $c);
        $first = $parts[0] ?? $c;
        if (isset($map[$first])) return $map[$first];

        // Fallback: first 3 letters uppercase
        return strtoupper(mb_substr(preg_replace('/[^a-záéíóúñ]/i', '', strtr($c, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'])), 0, 3));
    }

    private function buildCategoryHierarchy(string $cat, string $nombre): string {
        if (empty($cat)) return $cat;
        if (strpos($cat, '>') !== false || strpos($cat, '›') !== false) return $cat;

        $texto = mb_strtolower($cat . ' ' . $nombre);
        $genero = '';
        if (strpos($texto, 'mujer') !== false) $genero = 'Mujer';
        elseif (strpos($texto, 'hombre') !== false) $genero = 'Hombre';
        elseif (strpos($texto, 'niño') !== false || strpos($texto, 'niña') !== false) $genero = 'Niños';
        elseif (strpos($texto, 'bebé') !== false || strpos($texto, 'bebe') !== false) $genero = 'Bebés';
        elseif (strpos($texto, 'hogar') !== false) $genero = 'Hogar';

        if (!empty($genero)) return $genero . ' > ' . $cat;
        return $cat;
    }

    private function resolveCategory(string $nombre, string $marca): string {
        $db = $this->db();
        $texto = mb_strtolower($nombre . ' ' . $marca);

        // Find matching filter (nivel 3) or secondary (nivel 2) category by name match
        $stmt = $db->query("SELECT full_path, nivel, principal, secundaria, filtro FROM categories_hierarchy ORDER BY nivel DESC, id ASC");
        $stmt->execute();
        $categories = $stmt->fetchAll();

        $bestMatch = '';
        $bestScore = 0;

        foreach ($categories as $cat) {
            $score = 0;
            $searchTerms = [];
            if (!empty($cat->filtro)) $searchTerms[] = mb_strtolower($cat->filtro);
            if (!empty($cat->secundaria)) $searchTerms[] = mb_strtolower($cat->secundaria);
            $searchTerms[] = mb_strtolower($cat->principal);

            foreach ($searchTerms as $term) {
                if (strpos($texto, $term) !== false) {
                    $score += 10;
                }
                // Also check word-by-word
                $termWords = explode(' ', $term);
                foreach ($termWords as $tw) {
                    if (strlen($tw) > 3 && strpos($texto, $tw) !== false) {
                        $score += 5;
                    }
                }
            }

            // Give more weight to more specific (nivel 3 > nivel 2 > nivel 1)
            $score *= (int)$cat->nivel;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $cat->full_path;
            }
        }

        if (!empty($bestMatch)) return $bestMatch;

        // Fallback: detect gender from nombre
        if (strpos($texto, 'mujer') !== false) return 'Mujer';
        if (strpos($texto, 'hombre') !== false) return 'Hombre';
        if (strpos($texto, 'niño') !== false || strpos($texto, 'niña') !== false) return 'Niños y Niñas';

        return '';
    }

    private function getMedidas(): array {
        $db = $this->db();
        $stmt = $db->query("SELECT categoria, peso_kg, alto_cm, ancho_cm, grueso_cm FROM product_medidas ORDER BY LENGTH(categoria) DESC, id ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'cat' => mb_strtolower(trim($r->categoria)),
                'peso' => $r->peso_kg,
                'alto' => $r->alto_cm,
                'ancho' => $r->ancho_cm,
                'grueso' => $r->grueso_cm,
            ];
        }
        return $result;
    }

    private function matchMedidas(string $nombre, string $categoria, array $medidasList): array {
        $texto = mb_strtolower($nombre . ' ' . $categoria);
        $best = ['peso' => null, 'largo' => null, 'ancho' => null, 'alto' => null];
        $bestLen = 0;

        foreach ($medidasList as $m) {
            if (strpos($texto, $m['cat']) !== false) {
                $len = mb_strlen($m['cat']);
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $best = [
                        'peso' => $this->parseMedidaFloat($m['peso']),
                        'largo' => $this->parseMedidaFloat($m['grueso']),
                        'ancho' => $this->parseMedidaFloat($m['ancho']),
                        'alto' => $this->parseMedidaFloat($m['alto']),
                    ];
                }
            }
        }

        return $best;
    }

    private function parseMedidaFloat($val): ?float {
        if ($val === null || $val === '' || $val === 'NULL') return null;
        $v = str_replace(',', '.', trim((string)$val));
        return is_numeric($v) ? (float)$v : null;
    }

    private function requireCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'DESCONOCIDO';
            $uri = $_SERVER['REQUEST_URI'] ?? 'desconocida';
            $_SESSION['error'] = "Acción no válida: método «{$method}» no permitido para «{$uri}». Usa POST con token CSRF.";
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
            header('Location: ' . URLROOT . '/webproducts');
            exit;
        }
    }

    private function applyFixableColumns($sheet, array $issues): int {
        $fixMap = [
            'Publicado' => ['col' => 'F', 'val' => '1'],
            '¿Está destacado?' => ['col' => 'G', 'val' => '0'],
            'Visibilidad en el catálogo' => ['col' => 'H', 'val' => 'visible'],
            'Estado del impuesto' => ['col' => 'M', 'val' => 'taxable'],
            'Clase de impuesto' => ['col' => 'N', 'val' => 'parent'],
            '¿Permitir reservas agotados?' => ['col' => 'R', 'val' => '0'],
            '¿Vendido individualmente?' => ['col' => 'S', 'val' => '0'],
            'Precio normal' => ['col' => 'AA', 'val' => '2'],
            'Nombre del atributo 1' => ['col' => 'AP', 'val' => 'Talla'],
            'Atributo visible 1' => ['col' => 'AR', 'val' => '1'],
            'Atributo global 1' => ['col' => 'AS', 'val' => '0'],
            'Nombre del atributo 2' => ['col' => 'AT', 'val' => 'Color'],
            'Atributo visible 2' => ['col' => 'AV', 'val' => '1'],
            'Atributo global 2' => ['col' => 'AW', 'val' => '0'],
        ];

        $fixed = 0;
        foreach ($issues as $issue) {
            if (!$issue['arreglable']) continue;
            $header = $issue['tipo'];
            if (!isset($fixMap[$header])) continue;
            $sheet->setCellValue($fixMap[$header]['col'] . $issue['fila'], $fixMap[$header]['val']);
            $fixed++;
        }
        return $fixed;
    }

    private function runReview(array $rows): array {
        $issues = [];
        $skuCount = [];
        $parentSkus = [];
        $filas = [];

        // Column definitions: [index, header, we_fill, validator]
        $columnDefs = [
            [0, 'ID', false, null],
            [1, 'Tipo', false, null],
            [2, 'SKU', false, null],
            [3, 'GTIN, UPC, EAN o ISBN', false, null],
            [4, 'Nombre', true, fn($v, $t) => empty($v) ? 'Nombre vacío' : null],
            [5, 'Publicado', true, fn($v, $t) => $v !== '1' ? 'Debe ser 1' : null],
            [6, '¿Está destacado?', true, fn($v, $t) => $v !== '0' ? 'Debe ser 0' : null],
            [7, 'Visibilidad en el catálogo', true, fn($v, $t) => $v !== 'visible' ? 'Debe ser "visible"' : null],
            [8, 'Descripción corta', true, fn($v, $t) => null],
            [9, 'Descripción', true, fn($v, $t) => null],
            [10, 'Día inicio precio rebajado', false, null],
            [11, 'Día fin precio rebajado', false, null],
            [12, 'Estado del impuesto', true, fn($v, $t) => mb_strtolower($v) !== 'taxable' ? 'Debe ser "taxable"' : null],
            [13, 'Clase de impuesto', true, fn($v, $t) => (mb_strtolower($t) === 'variation' && mb_strtolower($v) !== 'parent') ? 'Debe ser "parent"' : null],
            [14, '¿Existencias?', false, null],
            [15, 'Inventario', true, fn($v, $t) => null],
            [16, 'Cantidad de bajo inventario', false, null],
            [17, '¿Permitir reservas agotados?', true, fn($v, $t) => $v !== '0' ? 'Debe ser 0' : null],
            [18, '¿Vendido individualmente?', true, fn($v, $t) => $v !== '0' ? 'Debe ser 0' : null],
            [19, 'Peso (kg)', true, fn($v, $t) => (empty($v) || $v === '0') ? 'Peso vacío' : null],
            [20, 'Longitud (cm)', true, fn($v, $t) => empty($v) ? 'Longitud vacía' : null],
            [21, 'Anchura (cm)', true, fn($v, $t) => empty($v) ? 'Anchura vacía' : null],
            [22, 'Altura (cm)', true, fn($v, $t) => empty($v) ? 'Altura vacía' : null],
            [23, '¿Permitir valoraciones?', false, null],
            [24, 'Nota de compra', false, null],
            [25, 'Precio rebajado', false, null],
            [26, 'Precio normal', true, fn($v, $t) => empty($v) ? 'Precio vacío' : null],
            [27, 'Categorías', true, fn($v, $t) => empty($v) ? 'Categorías vacías' : null],
            [28, 'Etiquetas', true, fn($v, $t) => null],
            [29, 'Clase de envío', false, null],
            [30, 'Imágenes', false, null],
            [31, 'Límite de descargas', false, null],
            [32, 'Días de caducidad descarga', false, null],
            [33, 'Superior', true, fn($v, $t) => (mb_strtolower($t) === 'variation' && empty($v)) ? 'Superior vacío (variación sin padre)' : null],
            [34, 'Productos agrupados', false, null],
            [35, 'Ventas dirigidas', false, null],
            [36, 'Ventas cruzadas', false, null],
            [37, 'URL externa', false, null],
            [38, 'Texto del botón', false, null],
            [39, 'Posición', false, null],
            [40, 'Marcas', true, fn($v, $t) => null],
            [41, 'Nombre del atributo 1', true, fn($v, $t) => $v !== 'Talla' ? 'Debe ser "Talla"' : null],
            [42, 'Valor(es) del atributo 1', true, fn($v, $t) => empty($v) ? 'Talla vacía' : null],
            [43, 'Atributo visible 1', true, fn($v, $t) => $v !== '1' ? 'Debe ser 1' : null],
            [44, 'Atributo global 1', true, fn($v, $t) => $v !== '0' ? 'Debe ser 0' : null],
            [45, 'Nombre del atributo 2', true, fn($v, $t) => $v !== 'Color' ? 'Debe ser "Color"' : null],
            [46, 'Valor(es) del atributo 2', true, fn($v, $t) => empty($v) ? 'Color vacío' : null],
            [47, 'Atributo visible 2', true, fn($v, $t) => $v !== '1' ? 'Debe ser 1' : null],
            [48, 'Atributo global 2', true, fn($v, $t) => $v !== '0' ? 'Debe ser 0' : null],
        ];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;
            $cc = []; // column statuses for this row

            $tipo  = trim((string)($row[1] ?? ''));
            $sku   = trim((string)($row[2] ?? ''));
            $nombre = trim((string)($row[4] ?? ''));

            $isEmpty = empty($tipo) && empty($sku) && empty($nombre);
            if ($isEmpty) continue;

            if (!empty($sku)) {
                $skuClean = mb_strtoupper($sku);
                $skuCount[$skuClean] = ($skuCount[$skuClean] ?? 0) + 1;
                if (mb_strtolower($tipo) === 'variable') $parentSkus[] = $skuClean;
            }

            $tipoLower = mb_strtolower($tipo);

            foreach ($columnDefs as $cd) {
                [$idx, $header, $weFill, $validator] = $cd;
                $val = trim((string)($row[$idx] ?? ''));
                $error = null;
                if ($weFill && $validator !== null) {
                    $error = $validator($val, $tipo);
                }
                $cc[] = ['h' => $header, 'v' => $val, 'r' => $weFill, 'e' => $error];
                if ($error !== null) {
                    $isFixable = in_array($header, ['Publicado', '¿Está destacado?', 'Visibilidad en el catálogo', 'Estado del impuesto', 'Clase de impuesto', '¿Permitir reservas agotados?', '¿Vendido individualmente?', 'Precio normal', 'Nombre del atributo 1', 'Atributo visible 1', 'Atributo global 1', 'Nombre del atributo 2', 'Atributo visible 2', 'Atributo global 2']);
                    $issues[] = ['fila' => $rowNum, 'sku' => $sku, 'tipo' => $header, 'descripcion' => $error, 'arreglable' => $isFixable];
                }
            }

            $filas[] = ['fila' => $rowNum, 'sku' => $sku, 'nombre' => $nombre, 'tipo' => $tipo, 'cols' => $cc];
        }

        // Duplicate SKU check
        foreach ($skuCount as $sk => $cnt) {
            if ($cnt > 1 && $sk !== '-') {
                for ($i = 1; $i < count($rows); $i++) {
                    $rsku = mb_strtoupper(trim((string)($rows[$i][2] ?? '')));
                    if ($rsku === $sk) {
                        $issues[] = ['fila' => $i + 1, 'sku' => $sk, 'tipo' => 'SKU duplicado', 'descripcion' => "SKU duplicado ($cnt veces)", 'arreglable' => true];
                        foreach ($filas as &$f) {
                            if ($f['fila'] === $i + 1) {
                                $f['duplicado'] = true;
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Orphan parent check
        if (!empty($parentSkus)) {
            for ($i = 1; $i < count($rows); $i++) {
                $rparent = mb_strtoupper(trim((string)($rows[$i][33] ?? '')));
                if (!empty($rparent) && in_array($rparent, $parentSkus)) {
                    $parentSkus = array_filter($parentSkus, fn($p) => $p !== $rparent);
                }
            }
            foreach ($parentSkus as $orphan) {
                for ($i = 1; $i < count($rows); $i++) {
                    $rsku = mb_strtoupper(trim((string)($rows[$i][2] ?? '')));
                    if ($rsku === $orphan) {
                        $issues[] = ['fila' => $i + 1, 'sku' => $orphan, 'tipo' => 'huerfano', 'descripcion' => 'Producto variable sin variaciones hijas', 'arreglable' => false];
                        foreach ($filas as &$f) {
                            if ($f['fila'] === $i + 1) {
                                $f['huerfano'] = true;
                                break;
                            }
                        }
                        break;
                    }
                }
            }
        }

        return ['issues' => $issues, 'filas' => $filas];
    }

    public function medidas() {
        $db = $this->db();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf_token'] ?? '';
            if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
                $_SESSION['error'] = 'Token inválido.';
                header('Location: ' . URLROOT . '/webproducts/medidas');
                exit;
            }

            $categoria = mb_strtolower(trim($_POST['categoria'] ?? ''));
            $peso = trim($_POST['peso_kg'] ?? '');
            $alto = trim($_POST['alto_cm'] ?? '');
            $ancho = trim($_POST['ancho_cm'] ?? '');
            $grueso = trim($_POST['grueso_cm'] ?? '');
            $variante = trim($_POST['variante'] ?? 'Individual');

            if (empty($categoria)) {
                $_SESSION['error'] = 'La categoría es requerida.';
            } else {
                $stmt = $db->query("INSERT INTO product_medidas (categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoria, $peso, $alto, $ancho, $grueso, $variante]);
                $_SESSION['success'] = "Medida para '$categoria' agregada correctamente.";
            }

            header('Location: ' . URLROOT . '/webproducts/medidas');
            exit;
        }

        $stmt = $db->query("SELECT id, categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante FROM product_medidas ORDER BY categoria ASC");
        $stmt->execute();
        $medidas = $stmt->fetchAll();

        $page_title = 'Gestionar Medidas';
        $page_description = 'Pesos y dimensiones por categoría para la plantilla WooCommerce.';
        $page_icon = icon('package', ['size' => 22]);
        $page_action_url = URLROOT . '/webproducts';
        $page_action_label = icon('arrow-left', ['size' => 14]) . ' Volver';
        $current_nav = 'webproducts';
        $data = [
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'medidas' => $medidas,
        ];
        $view_content = __DIR__ . '/../Views/webproducts/content-medidas.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function deleteMedida($id) {
        if (empty($id)) {
            $_SESSION['error'] = 'ID no válido.';
            header('Location: ' . URLROOT . '/webproducts/medidas');
            exit;
        }

        $db = $this->db();
        $stmt = $db->query("DELETE FROM product_medidas WHERE id = ?");
        $stmt->execute([(int)$id]);
        $_SESSION['info'] = 'Medida eliminada.';
        header('Location: ' . URLROOT . '/webproducts/medidas');
        exit;
    }

    // ---- Categorías ----

    public function categorias() {
        $db = $this->db();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf_token'] ?? '';
            if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
                $_SESSION['error'] = 'Token inválido.';
                header('Location: ' . URLROOT . '/webproducts/categorias');
                exit;
            }

            $nivel = (int)($_POST['nivel'] ?? 2);
            $principal = mb_strtolower(trim($_POST['principal'] ?? ''));
            $secundaria = mb_strtolower(trim($_POST['secundaria'] ?? ''));
            $filtro = mb_strtolower(trim($_POST['filtro'] ?? ''));

            if (empty($principal)) {
                $_SESSION['error'] = 'La categoría principal es requerida.';
            } else {
                $parts = [$principal];
                if (!empty($secundaria)) $parts[] = $secundaria;
                if (!empty($filtro)) $parts[] = $filtro;
                $fullPath = implode(' > ', $parts);

                $stmt = $db->query("INSERT INTO categories_hierarchy (nivel, principal, secundaria, filtro, full_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nivel, $principal, $secundaria ?: null, $filtro ?: null, $fullPath]);
                $_SESSION['success'] = "Categoría '$fullPath' agregada correctamente.";
            }

            header('Location: ' . URLROOT . '/webproducts/categorias');
            exit;
        }

        $stmt = $db->query("SELECT id, nivel, principal, secundaria, filtro, full_path FROM categories_hierarchy ORDER BY full_path ASC");
        $stmt->execute();
        $categorias = $stmt->fetchAll();

        $page_title = 'Gestionar Categorías';
        $page_description = 'Jerarquía de categorías para productos WooCommerce.';
        $page_icon = icon('layers', ['size' => 22]);
        $page_action_url = URLROOT . '/webproducts';
        $page_action_label = icon('arrow-left', ['size' => 14]) . ' Volver';
        $current_nav = 'webproducts';
        $data = [
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'categorias' => $categorias,
        ];
        $view_content = __DIR__ . '/../Views/webproducts/content-categorias.php';
        require __DIR__ . '/../Views/layouts/app.php';
    }

    public function deleteCategoria($id) {
        if (empty($id)) {
            $_SESSION['error'] = 'ID no válido.';
            header('Location: ' . URLROOT . '/webproducts/categorias');
            exit;
        }

        $db = $this->db();
        $stmt = $db->query("DELETE FROM categories_hierarchy WHERE id = ?");
        $stmt->execute([(int)$id]);
        $_SESSION['info'] = 'Categoría eliminada.';
        header('Location: ' . URLROOT . '/webproducts/categorias');
        exit;
    }

    public function descargarPlantillaCategorias() {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['nivel', 'principal', 'secundaria', 'filtro'];
            foreach ($headers as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col . '1', $h);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $sheet->getStyle($col . '1')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFF00');
            }

            $sheet->setCellValue('A2', 2);
            $sheet->setCellValue('B2', 'Mujer');
            $sheet->setCellValue('C2', 'Calzones');
            $sheet->setCellValue('D2', '');

            $sheet->setCellValue('A3', 1);
            $sheet->setCellValue('B3', 'Hombre');
            $sheet->setCellValue('C3', '');
            $sheet->setCellValue('D3', '');

            foreach (range('A', 'D') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="plantilla_categorias.xlsx"');

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
            exit;
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error al generar plantilla: ' . $e->getMessage();
            header('Location: ' . URLROOT . '/webproducts/categorias');
            exit;
        }
    }

    public function importCategorias() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['categorias_file']) || $_FILES['categorias_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Error al subir el archivo.';
            header('Location: ' . URLROOT . '/webproducts/categorias');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/webproducts/categorias');
            exit;
        }

        if ($_FILES['categorias_file']['size'] > 10 * 1024 * 1024) {
            $_SESSION['error'] = 'Archivo demasiado grande. Máximo 10 MB.';
            header('Location: ' . URLROOT . '/webproducts/categorias');
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['categorias_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            $_SESSION['error'] = 'Formato no válido. Solo .xlsx y .xls.';
            header('Location: ' . URLROOT . '/webproducts/categorias');
            exit;
        }

        try {
            ini_set('memory_limit', '256M');

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($_FILES['categorias_file']['tmp_name']);

            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = min($worksheet->getHighestDataRow(), 5001);
            if ($highestRow < 2) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                throw new \Exception('El archivo no tiene datos.');
            }

            $rows = [];
            for ($r = 1; $r <= $highestRow; $r++) {
                $row = [];
                for ($c = 'A'; $c <= 'D'; $c++) {
                    $row[] = $worksheet->getCell($c . $r)->getValue();
                }
                $rows[] = $row;
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet);

            $headers = array_map('strtolower', array_map('trim', $rows[0]));
            $colMap = [];
            $expected = ['nivel', 'principal', 'secundaria', 'filtro'];
            foreach ($expected as $col) {
                $idx = array_search($col, $headers);
                if ($idx === false && $col !== 'secundaria' && $col !== 'filtro') {
                    throw new \Exception("Columna '$col' no encontrada. Columnas esperadas: " . implode(', ', $expected));
                }
                $colMap[$col] = $idx;
            }

            $db = $this->db();
            $inserted = 0;
            $errors = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $nivel = (int)($row[$colMap['nivel']] ?? 2);
                $principal = mb_strtolower(trim($row[$colMap['principal']] ?? ''));
                if (empty($principal)) continue;

                $secundaria = $colMap['secundaria'] !== false ? mb_strtolower(trim($row[$colMap['secundaria']] ?? '')) : '';
                $filtro = $colMap['filtro'] !== false ? mb_strtolower(trim($row[$colMap['filtro']] ?? '')) : '';

                $parts = [$principal];
                if (!empty($secundaria)) $parts[] = $secundaria;
                if (!empty($filtro)) $parts[] = $filtro;
                $fullPath = implode(' > ', $parts);

                try {
                    $stmt = $db->query("INSERT INTO categories_hierarchy (nivel, principal, secundaria, filtro, full_path) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$nivel, $principal, $secundaria ?: null, $filtro ?: null, $fullPath]);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = "Fila " . ($i + 1) . ": " . $e->getMessage();
                }
            }

            $msg = "$inserted categoría(s) importada(s) correctamente.";
            if (!empty($errors)) {
                $msg .= " Errores: " . implode(' | ', $errors);
            }
            if ($inserted > 0) {
                $_SESSION['success'] = $msg;
            } else {
                $_SESSION['error'] = $msg;
            }

        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error al procesar el archivo: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/webproducts/categorias');
        exit;
    }

    public function descargarPlantillaMedidas() {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['categoria', 'peso_kg', 'alto_cm', 'ancho_cm', 'grueso_cm', 'variante'];
            foreach ($headers as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col . '1', $h);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $sheet->getStyle($col . '1')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFF00');
            }

            $sheet->setCellValue('A2', 'camiseta');
            $sheet->setCellValue('B2', '0.3');
            $sheet->setCellValue('C2', '10');
            $sheet->setCellValue('D2', '15');
            $sheet->setCellValue('E2', '1');
            $sheet->setCellValue('F2', 'Individual');

            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="plantilla_medidas.xlsx"');

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
            exit;
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error al generar plantilla: ' . $e->getMessage();
            header('Location: ' . URLROOT . '/webproducts/medidas');
            exit;
        }
    }

    public function importMedidas() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['medidas_file']) || $_FILES['medidas_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Error al subir el archivo.';
            header('Location: ' . URLROOT . '/webproducts/medidas');
            exit;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['error'] = 'Token inválido.';
            header('Location: ' . URLROOT . '/webproducts/medidas');
            exit;
        }

        if ($_FILES['medidas_file']['size'] > 10 * 1024 * 1024) {
            $_SESSION['error'] = 'Archivo demasiado grande. Máximo 10 MB.';
            header('Location: ' . URLROOT . '/webproducts/medidas');
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['medidas_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            $_SESSION['error'] = 'Formato no válido. Solo .xlsx y .xls.';
            header('Location: ' . URLROOT . '/webproducts/medidas');
            exit;
        }

        try {
            ini_set('memory_limit', '256M');

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($_FILES['medidas_file']['tmp_name']);

            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = min($worksheet->getHighestDataRow(), 5001);
            if ($highestRow < 2) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                throw new \Exception('El archivo no tiene datos.');
            }

            $rows = [];
            for ($r = 1; $r <= $highestRow; $r++) {
                $row = [];
                for ($c = 'A'; $c <= 'F'; $c++) {
                    $row[] = $worksheet->getCell($c . $r)->getValue();
                }
                $rows[] = $row;
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet);

            $headers = array_map('strtolower', array_map('trim', $rows[0]));
            $colMap = [];
            $expected = ['categoria', 'peso_kg', 'alto_cm', 'ancho_cm', 'grueso_cm', 'variante'];
            foreach ($expected as $col) {
                $idx = array_search($col, $headers);
                if ($idx === false && $col !== 'variante') {
                    throw new \Exception("Columna '$col' no encontrada en el archivo. Columnas esperadas: " . implode(', ', $expected));
                }
                $colMap[$col] = $idx;
            }

            $db = $this->db();
            $inserted = 0;
            $errors = [];
            $stmt = $db->query("INSERT INTO product_medidas (categoria, peso_kg, alto_cm, ancho_cm, grueso_cm, variante) VALUES (?, ?, ?, ?, ?, ?)");

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $categoria = mb_strtolower(trim($row[$colMap['categoria']] ?? ''));
                if (empty($categoria)) continue;

                $peso = trim($row[$colMap['peso_kg']] ?? '');
                $alto = trim($row[$colMap['alto_cm']] ?? '');
                $ancho = trim($row[$colMap['ancho_cm']] ?? '');
                $grueso = trim($row[$colMap['grueso_cm']] ?? '');
                $variante = $colMap['variante'] !== false ? trim($row[$colMap['variante']] ?? 'Individual') : 'Individual';

                try {
                    $stmt->execute([$categoria, $peso, $alto, $ancho, $grueso, $variante]);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = "Fila " . ($i + 1) . ": " . $e->getMessage();
                }
            }

            $msg = "$inserted medida(s) importada(s) correctamente.";
            if (!empty($errors)) {
                $msg .= " Errores: " . implode(' | ', $errors);
            }
            if ($inserted > 0) {
                $_SESSION['success'] = $msg;
            } else {
                $_SESSION['error'] = $msg;
            }

        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error al procesar el archivo: ' . $e->getMessage();
        }

        header('Location: ' . URLROOT . '/webproducts/medidas');
        exit;
    }
}
