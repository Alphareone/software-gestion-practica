<?php
/**
 * Endpoint de Diagnóstico y Ping de Conectividad
 * Verifica salud de la BD y conectividad con APIs (Cencosud, ML, Walmart)
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$baseDir = dirname(__DIR__);
if (file_exists($baseDir . '/vendor/autoload.php')) {
    require_once $baseDir . '/vendor/autoload.php';
}
if (file_exists($baseDir . '/app/config/config.php')) {
    require_once $baseDir . '/app/config/config.php';
}

$startTime = microtime(true);
$results = [
    'timestamp' => date('c'),
    'status' => 'OK',
    'environment' => defined('APP_ENV') ? APP_ENV : 'development',
    'checks' => [],
    'summary' => [
        'total_checks' => 0,
        'passed' => 0,
        'failed' => 0,
        'execution_time_ms' => 0
    ]
];

// Helper para probar sockets/HTTP ping
function pingHost($url, $timeoutSec = 3) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSec);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutSec);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $start = microtime(true);
    curl_exec($ch);
    $latencyMs = round((microtime(true) - $start) * 1000, 2);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'reachable' => ($httpCode > 0 && $httpCode < 500),
        'http_code' => $httpCode,
        'latency_ms' => $latencyMs,
        'error' => $error ?: null
    ];
}

// 1. CHEQUEO BASE DE DATOS (MariaDB / MySQL)
try {
    $dbHost = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $dbPort = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: '3306');
    $dbName = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'gestion_db');
    $dbUser = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $dbPass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

    $dbStart = microtime(true);
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3
    ]);
    $pdo->query('SELECT 1');
    $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);

    $results['checks']['database'] = [
        'status' => 'UP',
        'host' => $dbHost,
        'port' => $dbPort,
        'database' => $dbName,
        'latency_ms' => $dbLatency,
    ];
    $results['summary']['passed']++;
} catch (Exception $e) {
    $results['checks']['database'] = [
        'status' => 'DOWN',
        'error' => $e->getMessage()
    ];
    $results['status'] = 'DEGRADED';
    $results['summary']['failed']++;
}
$results['summary']['total_checks']++;

// 2. CHEQUEO API CENCOSUD (Paris.cl)
$cencoUrl = defined('CENCOSUD_API_BASE_URL') ? CENCOSUD_API_BASE_URL : 'https://api.cencosud.cl';
$cencoPing = pingHost($cencoUrl);
$results['checks']['cencosud_api'] = array_merge([
    'status' => $cencoPing['reachable'] ? 'UP' : 'UNREACHABLE',
    'target_url' => $cencoUrl,
    'dry_run' => defined('CENCOSUD_DRY_RUN') ? CENCOSUD_DRY_RUN : true
], $cencoPing);
if ($cencoPing['reachable']) $results['summary']['passed']++; else $results['summary']['failed']++;
$results['summary']['total_checks']++;

// 3. CHEQUEO API MERCADOLIBRE
$mlUrl = 'https://api.mercadolibre.com';
$mlPing = pingHost($mlUrl);
$results['checks']['mercadolibre_api'] = array_merge([
    'status' => $mlPing['reachable'] ? 'UP' : 'UNREACHABLE',
    'target_url' => $mlUrl
], $mlPing);
if ($mlPing['reachable']) $results['summary']['passed']++; else $results['summary']['failed']++;
$results['summary']['total_checks']++;

// 4. CHEQUEO API WALMART
$wmUrl = defined('WALMART_API_BASE_URL') ? WALMART_API_BASE_URL : 'https://marketplace.walmartapis.com';
$wmPing = pingHost($wmUrl);
$results['checks']['walmart_api'] = array_merge([
    'status' => $wmPing['reachable'] ? 'UP' : 'UNREACHABLE',
    'target_url' => $wmUrl,
    'dry_run' => defined('WALMART_DRY_RUN') ? WALMART_DRY_RUN : false
], $wmPing);
if ($wmPing['reachable']) $results['summary']['passed']++; else $results['summary']['failed']++;
$results['summary']['total_checks']++;

// Resumen final
$results['summary']['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

http_response_code($results['status'] === 'OK' ? 200 : 503);
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
