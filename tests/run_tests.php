<?php
/**
 * Suite de Pruebas Automatizadas Unitarias, Funcionales y de Sistema
 * Proyecto: Sistema de Gestión - Módulo Cencosud, ML, Walmart & Core
 */

$baseDir = dirname(__DIR__);
require_once $baseDir . '/vendor/autoload.php';
require_once $baseDir . '/app/config/config.php';

echo "\n==========================================================\n";
echo "🚀 SUITE DE PRUEBAS DEL SISTEMA E INTEGRACIÓN CENCOSUD\n";
echo "==========================================================\n\n";

$passedCount = 0;
$failedCount = 0;
$tests = [];

function runAssert($title, callable $fn) {
    global $passedCount, $failedCount, $tests;
    $start = microtime(true);
    try {
        $fn();
        $ms = round((microtime(true) - $start) * 1000, 2);
        echo "  [PASS] {$title} ({$ms}ms)\n";
        $passedCount++;
        $tests[] = ['name' => $title, 'status' => 'PASS', 'ms' => $ms, 'error' => null];
    } catch (Throwable $e) {
        $ms = round((microtime(true) - $start) * 1000, 2);
        echo "  [FAIL] {$title} ({$ms}ms)\n";
        echo "         -> Error: " . $e->getMessage() . "\n";
        $failedCount++;
        $tests[] = ['name' => $title, 'status' => 'FAIL', 'ms' => $ms, 'error' => $e->getMessage()];
    }
}

// --------------------------------------------------------
// BLOQUE 1: PING & DIAGNÓSTICO DE BASE DE DATOS
// --------------------------------------------------------
echo "📌 BLOQUE 1: Ping y Diagnóstico de Salud de Base de Datos\n";

runAssert("Ping DB: Conexión activa a MariaDB/MySQL", function() {
    $db = new Database();
    $stmt = $db->query("SELECT 1 as ping");
    $stmt->execute();
    $res = $stmt->fetch();
    if (!$res || $res->ping != 1) {
        throw new Exception("El ping a la base de datos retornó un resultado inválido.");
    }
});

runAssert("Estructura BD: Existencia de columnas 2FA en tabla users", function() {
    $userModel = new UserModel();
    if (!$userModel->hasTwoFactorColumns()) {
        throw new Exception("Faltan columnas de doble factor de autenticación en la tabla users.");
    }
});

runAssert("Estructura BD: Tablas de Cencosud (connections, cache, sync, orders)", function() {
    $db = new Database();
    $tables = ['cencosud_connections', 'cencosud_products_cache', 'cencosud_sync_status', 'cencosud_update_batches', 'cencosud_update_logs', 'cencosud_orders_cache'];
    foreach ($tables as $t) {
        $stmt = $db->query("SHOW TABLES LIKE '$t'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            throw new Exception("La tabla $t no existe en la base de datos.");
        }
    }
});

// --------------------------------------------------------
// BLOQUE 2: ÓRDENES CENCOSUD (MIGRACIÓN ABRAHAM)
// --------------------------------------------------------
echo "\n📌 BLOQUE 2: Caché y Gestión de Órdenes Cencosud (cencosud_orders_cache)\n";

runAssert("Cencosud Orders: Upsert y consulta de ordenes de prueba", function() {
    $ordersModel = new CencosudOrdersCacheModel();
    $connModel = new CencosudConnectionModel();
    $conns = $connModel->findAll();
    
    $createdTempConn = false;
    $db = new Database();

    if (empty($conns)) {
        $stmt = $db->query("SELECT id FROM users LIMIT 1");
        $stmt->execute();
        $u = $stmt->fetch();
        $userId = $u ? $u->id : 1;

        $stmtIns = $db->query("INSERT INTO cencosud_connections (user_id, store_name, client_id, client_secret, is_active) VALUES (:uid, 'Tienda Test Pruebas', 'test_client_id', 'test_secret', 1)");
        $stmtIns->execute([':uid' => $userId]);
        $connId = $db->lastInsertId();
        $createdTempConn = true;
    } else {
        $connId = $conns[0]->id;
    }

    $orderId = 'TEST-ORDER-' . time();
    $orderData = [
        'status' => 'pending',
        'total_amount' => 15990.00,
        'customer_name' => 'Juan Pérez Prueba',
        'shipping_status' => 'ready_for_pickup',
        'created_at_cenco' => date('Y-m-d H:i:s'),
        'raw_data' => json_encode(['orderId' => $orderId, 'test' => true])
    ];

    $id = $ordersModel->upsert($connId, $orderId, $orderData);
    if (!$id) throw new Exception("No se pudo insertar la orden en cencosud_orders_cache.");

    $retrieved = $ordersModel->getOrder($connId, $orderId);
    if (!$retrieved || $retrieved->customer_name !== 'Juan Pérez Prueba') {
        throw new Exception("Los datos recuperados de la orden no coinciden.");
    }

    if ($createdTempConn) {
        $stmtDel = $db->query("DELETE FROM cencosud_connections WHERE id = :id");
        $stmtDel->execute([':id' => $connId]);
    }
});

// --------------------------------------------------------
// BLOQUE 3: BÚSQUEDA Y AGRUPACIÓN POR SKU (PACKS Y TRIPACKS)
// --------------------------------------------------------
echo "\n📌 BLOQUE 3: Buscador Avanzado por SKU y Variantes Regex\n";

runAssert("SKU Regex Search: Filtro por SKU base y variaciones", function() {
    $cacheModel = new CencosudProductsCacheModel();
    $connModel = new CencosudConnectionModel();
    $conns = $connModel->findAll();
    $connId = !empty($conns) ? $conns[0]->id : 1;

    // Probar consulta con SKU base
    $res = $cacheModel->getProducts($connId, 1, 10, 'A-201', 'all');
    if (!isset($res['items'])) {
        throw new Exception("Respuesta inválida al consultar productos por SKU.");
    }
});

// --------------------------------------------------------
// BLOQUE 4: REGLAS DE NEGOCIO Y PROTECCIÓN (SERNAC & STOCK)
// --------------------------------------------------------
echo "\n📌 BLOQUE 4: Protección de Precios SERNAC y Stock Crítico (≤ 5)\n";

runAssert("Filtro SERNAC: Bloqueo de precios <= $500 CLP", function() {
    $db = new Database();
    $updateService = new CencosudUpdateService($db);
    try {
        $updateService->validatePrice('A-201', 400.00, 10000.00);
        throw new Exception("Falló la protección: Se permitió publicar un precio de $400 CLP.");
    } catch (InvalidArgumentException $e) {
        // Correcto, el filtro bloqueó el precio
    }
});

runAssert("Filtro SERNAC: Bloqueo por caída drástica de precio (>70% desc)", function() {
    $db = new Database();
    $updateService = new CencosudUpdateService($db);
    try {
        // Precio original 10.000, intenta bajar a 2.000 (80% desc)
        $updateService->validatePrice('A-201', 2000.00, 10000.00);
        throw new Exception("Falló la protección: Se permitió una caída de precio superior al 70%.");
    } catch (InvalidArgumentException $e) {
        // Correcto, el filtro congeló el cambio
    }
});

runAssert("Regla de Stock Crítico: Forzado de stock <= 5 a 0", function() {
    $db = new Database();
    $updateService = new CencosudUpdateService($db);
    $effectiveStock = $updateService->calculateEffectiveStock(4);
    if ($effectiveStock !== 0) {
        throw new Exception("La regla crítica falló: Stock de 4 unidades debió ser forzado a 0 (retornó $effectiveStock).");
    }

    $normalStock = $updateService->calculateEffectiveStock(8);
    if ($normalStock !== 8) {
        throw new Exception("El stock normal falló: Stock de 8 unidades debió mantenerse en 8 (retornó $normalStock).");
    }
});

// --------------------------------------------------------
// BLOQUE 5: MEDIDAS FISICAS Y PESO VOLUMÉTRICO
// --------------------------------------------------------
echo "\n📌 BLOQUE 5: Cálculo Tarifario y Medidas Volumétricas\n";

runAssert("Cálculo Volumétrico: (Alto x Ancho x Largo / 4000)", function() {
    $alto = 20; $ancho = 30; $largo = 40; // 24.000 cm3 / 4000 = 6 kg
    $pesoVol = ($alto * $ancho * $largo) / 4000;
    if ($pesoVol != 6.0) {
        throw new Exception("Cálculo de peso volumétrico incorrecto: Esperado 6.0kg, obtenido {$pesoVol}kg.");
    }
});

// --------------------------------------------------------
// BLOQUE 6: AUTENTICACIÓN Y API KEYS (CENCOSUD)
// --------------------------------------------------------
echo "\n📌 BLOQUE 6: Autenticación y API Keys\n";

runAssert("Validación de API Keys: Simulación Dry-Run (Mocking)", function() {
    $db = new Database();
    $authService = new CencosudAuthService($db);
    
    // Probamos con un Client ID y Secret inventados
    $result = $authService->testCredentials('FAKE_CLIENT_ID_123', 'FAKE_SECRET_XYZ');
    
    if (defined('CENCOSUD_DRY_RUN') && CENCOSUD_DRY_RUN) {
        // En modo simulación (Dry Run), la petición nunca llega a Cencosud,
        // por lo que el Helper intercepta la llamada y simula un éxito (HTTP 200).
        if ($result['ok'] !== true) {
            throw new Exception("El Dry-Run falló en simular el éxito de las credenciales.");
        }
    } else {
        // Si estuviéramos en producción real (sin Dry Run), el sistema las rechazaría.
        if ($result['ok'] !== false) {
            throw new Exception("Error crítico: El sistema aceptó credenciales falsas en entorno real.");
        }
    }
});

// --------------------------------------------------------
// BLOQUE 7: TRANSFORMACIÓN DE DATOS (PAYLOAD BUILDER)
// --------------------------------------------------------
echo "\n📌 BLOQUE 7: Generación de Payload para Producción\n";

runAssert("Estructura JSON: Generación correcta del Payload con Fallbacks", function() {
    $db = new Database();
    $apiService = new CencosudApiService($db);
    
    // Datos crudos simulando lo que el usuario ingresaría
    $masterData = [
        'sku' => 'TEST-001',
        'title' => '', // Título vacío a propósito para forzar el fallback
    ];
    
    $extraData = [
        'title' => 'Polera de Prueba',
        'brand' => 'MarcaX',
        'color' => 'Azul'
    ];
    
    // Generamos el payload que se enviaría a Cencosud en Producción
    $payload = $apiService->buildProductPayload($masterData, $extraData);
    
    // Validamos que la estructura generada sea la que Cencosud exige
    if (!isset($payload['product']['sellerSku']) || $payload['product']['sellerSku'] !== 'TEST-001') {
        throw new Exception("El SKU no se asignó correctamente en el payload.");
    }
    
    // Validamos que el sistema haya usado el título de extraData (Fallback) porque el principal estaba vacío
    if ($payload['product']['name'] !== 'Polera de Prueba') {
        throw new Exception("El sistema no aplicó el respaldo del título correctamente.");
    }
    
    // Validamos que se haya inyectado un atributo de color en las variantes
    $hasColor = false;
    foreach ($payload['variants'][0]['attributes'] as $attr) {
        if ($attr['name'] === 'Color Comercial' && $attr['value'] === 'Azul') {
            $hasColor = true;
            break;
        }
    }
    if (!$hasColor) {
        throw new Exception("El atributo Color no se inyectó correctamente en la variante.");
    }
});

// --------------------------------------------------------
// BLOQUE 8: PARSEO DE EXCEL Y LIMPIEZA DE DATOS (CENCOSUD)
// --------------------------------------------------------
echo "\n📌 BLOQUE 8: Parseo de Excel y Limpieza de Datos\n";

runAssert("Limpieza de Precios (parsePrice): Formatos de Excel", function() {
    $db = new Database();
    $updateService = new CencosudUpdateService($db);
    
    // El sistema debe poder leer cualquier formato raro que el usuario ponga en el Excel
    $pruebas = [
        "$12.990"   => 12990.0,
        "12.990"    => 12990.0,
        "12,990"    => 12990.0,
        " 12990 "   => 12990.0,
        "$ 12.990 " => 12990.0
    ];
    
    foreach ($pruebas as $entrada => $esperado) {
        $resultado = $updateService->parsePrice($entrada);
        if ($resultado !== $esperado) {
            throw new Exception("El parseo falló para '$entrada'. Esperado: $esperado, Obtenido: $resultado");
        }
    }
});

// --------------------------------------------------------
// RESUMEN FINAL DE PRUEBAS
// --------------------------------------------------------
echo "\n==========================================================\n";
echo "📊 RESUMEN FINAL DE EJECUCIÓN DE PRUEBAS\n";
echo "==========================================================\n";
echo "  Total de Pruebas: " . count($tests) . "\n";
echo "  [PASS] Aprobadas: {$passedCount}\n";
echo "  [FAIL] Falladas:  {$failedCount}\n";
echo "  Resultado: " . ($failedCount === 0 ? "SUCCESS (100% PASS)" : "ATTENTION REQUIRED") . "\n";
echo "==========================================================\n\n";

exit($failedCount === 0 ? 0 : 1);
