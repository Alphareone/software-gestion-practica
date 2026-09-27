<?php
// Determinar la carpeta base (soporta local y Hostinger produccion)
$baseDir = dirname(__DIR__, 2) . '/backend-software';
if (!file_exists($baseDir . '/vendor/autoload.php')) {
    $baseDir = dirname(__DIR__, 1);
}

// 1. Cargar autoloader de Composer
require_once $baseDir . '/vendor/autoload.php';

// 2. Cargar configuración
require_once $baseDir . '/app/config/config.php';

// 3. Configuración de errores por entorno
if (defined('APP_ENV') && APP_ENV === 'production') {
	ini_set('display_errors', '0');
	ini_set('log_errors', '1');
	error_reporting(E_ALL);
} else {
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
}

// 3.5. Manejo global de excepciones y errores (Throwable)
set_exception_handler(function (Throwable $exception) use ($baseDir) {
	// Loguear error detallado de forma segura en el servidor
	error_log("Excepción no capturada: " . $exception->getMessage() . "\n" . $exception->getTraceAsString());

	// Limpiar búfer de salida para evitar renderizados rotos parciales
	if (ob_get_level() > 0) {
		ob_end_clean();
	}

	http_response_code(500);

	if (defined('APP_ENV') && APP_ENV === 'production') {
		// Mostrar la hermosa página de error 500 premium
		require_once $baseDir . '/app/Views/errors/500.php';
	} else {
		// En desarrollo mostrar detalles interactivos completos de la traza
		echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>[Desarrollo] Error 500</title>';
		echo '<style>body{font-family:sans-serif;background:#0b0f19;color:#f3f4f6;padding:2rem;line-height:1.6;}pre{background:#111827;color:#34d399;padding:1.5rem;border:1px solid #374151;border-radius:12px;overflow-x:auto;font-family:monospace;}</style></head><body>';
		echo '<h1 style="color:#f87171;border-bottom:1px solid #374151;padding-bottom:1rem;">[Desarrollo] Excepción No Controlada</h1>';
		echo '<p><strong>Mensaje:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>';
		echo '<p><strong>Archivo:</strong> ' . htmlspecialchars($exception->getFile()) . ' (Línea ' . $exception->getLine() . ')</p>';
		echo '<h3>Traza de pila (Stack Trace):</h3>';
		echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
		echo '</body></html>';
	}
	exit;
});

// 4. Headers de seguridad
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
// CSP manejado desde .htaccess para evitar duplicación

// 5. Sesión endurecida
$isHttps = defined('URLROOT') && strpos(URLROOT, 'https://') === 0;
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// 6. Iniciar la aplicación
$app = new App();
