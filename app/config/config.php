<?php

EnvLoader::load();

function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}

/**
 * Lee una variable de entorno como booleano.
 *
 * EnvLoader devuelve siempre texto, y en PHP (bool) "false" es true. Sin esta
 * conversión, escribir DRY_RUN=false en el .env dejaría el modo simulación
 * activo sin ningún aviso. Se aceptan las formas habituales en ambos sentidos.
 */
function env_bool($key, $default = false) {
    $raw = env($key, null);
    if ($raw === null) return (bool) $default;
    $v = strtolower(trim((string) $raw));
    if (in_array($v, ['1', 'true', 'yes', 'on'], true))       return true;
    if (in_array($v, ['0', 'false', 'no', 'off', ''], true))  return false;
    return (bool) $default;   // valor no reconocido: se respeta el default
}

function isHttps() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] !== 'off') return true;
    return false;
}

// Configuración de la bd
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'gestion'));

// Configuración de la App — detecta protocolo HTTP/HTTPS automáticamente
$urlrootScheme = isHttps() ? 'https' : 'http';
// Subdirectorio donde vive index.php (ej. "/software" o "" en la raíz).
// dirname() en Windows devuelve "\" — se normaliza a "/" y se quita la barra final.
$urlrootBase = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$urlrootBase = rtrim($urlrootBase, '/');
define('URLROOT', rtrim(env('URLROOT', $urlrootScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $urlrootBase), '/'));
define('SITENAME', 'Sistema de gestión');

// Branding / Identidad del sistema
define('BRAND_NAME',        'Sistema de gestión');
define('BRAND_ICON',        'layout-grid');  // icono del sprite.svg; definir BRAND_LOGO para usar imagen
define('BRAND_LOGO',        URLROOT . '/assets/img/logo-sin-texto.png');
define('BRAND_VERSION',     'v1.0.0');
define('BRAND_YEAR',        date('Y'));
define('BRAND_COMPANY',     'e-practicas');
define('BRAND_LOCATION',    'Chile, 2026');
define('APP_EMAIL',         env('APP_EMAIL', 'soporte@ofertasimperdibles.cl'));

// Entorno de ejecución: development | production
define('APP_ENV', env('APP_ENV', 'development'));

// Cache busting para assets CSS/JS.
// El backend puede vivir dentro del proyecto (local) o fuera del docroot
// (Hostinger: backend-software junto a public_html/software). Se prueban ambas
// ubicaciones; la segunda se deriva del subdirectorio de URLROOT, para no
// hardcodear el nombre de la carpeta de cada entorno.
$cssCandidatos = [__DIR__ . '/../../public_html/assets/css/dist/tailwind.css'];
$urlSubdir = parse_url(URLROOT, PHP_URL_PATH);
if (!empty($urlSubdir)) {
    $cssCandidatos[] = dirname(__DIR__, 3) . '/public_html' . $urlSubdir . '/assets/css/dist/tailwind.css';
}
$cssPath = null;
foreach ($cssCandidatos as $candidato) {
    if (file_exists($candidato)) { $cssPath = $candidato; break; }
}
define('CSS_VERSION', $cssPath ? filemtime($cssPath) : time());

// Zona horaria
date_default_timezone_set('America/Santiago');

// Seguridad de autenticación
define('AUTH_MAX_ATTEMPTS', (int) env('AUTH_MAX_ATTEMPTS', 5));
define('AUTH_LOCKOUT_MINUTES', (int) env('AUTH_LOCKOUT_MINUTES', 15));
define('TWOFA_REMEMBER_SECONDS', (int) env('TWOFA_REMEMBER_SECONDS', 604800));

// Tiempo máximo de inactividad antes de cerrar sesión (en segundos)
define('SESSION_IDLE_TIMEOUT', (int) env('SESSION_IDLE_TIMEOUT', 1800));

// Límites para verificación 2FA
define('TFA_MAX_ATTEMPTS', (int) env('TFA_MAX_ATTEMPTS', 5));
define('TFA_LOCKOUT_MINUTES', (int) env('TFA_LOCKOUT_MINUTES', 15));

// Umbral de actividad online (segundos desde last_activity_at para considerar usuario en línea)
define('USER_ONLINE_THRESHOLD', (int) env('USER_ONLINE_THRESHOLD', 120));

// Máximo de productos a exportar desde ML
define('ML_EXPORT_MAX_ITEMS', (int) env('ML_EXPORT_MAX_ITEMS', 1000));

// MercadoLibre OAuth
// Credenciales (APP ID y CLIENT SECRET) se configuran desde Panel → ML Credenciales
// Registrar app en https://developers.mercadolibre.com.cl
define('ML_REDIRECT_URI', env('ML_REDIRECT_URI', URLROOT . ''));
define('ML_AUTH_URL', env('ML_AUTH_URL', 'https://auth.mercadolibre.cl'));

// Walmart Chile Marketplace
// Credenciales (Client ID / Secret) se guardan por conexión en walmart_connections
// Registrar app en https://developer.walmart.com/cl-marketplace
define('WALMART_API_BASE_URL', env('WALMART_API_BASE_URL', 'https://marketplace.walmartapis.com'));
define('WALMART_DRY_RUN', env_bool('WALMART_DRY_RUN', false));

// Cencosud Chile Marketplace
// Autenticacion por API Key (Panel de Cencosud -> Mi Cuenta -> Integraciones).
// La key se guarda cifrada por conexión en cencosud_connections y se canjea
// por un Access Token en POST /v1/auth/apiKey. Documentación oficial:
// https://developers.ecomm.cencosud.com
define('CENCOSUD_API_BASE_URL', env('CENCOSUD_API_BASE_URL', 'https://api-developers.ecomm.cencosud.com'));
define('CENCOSUD_DRY_RUN', env_bool('CENCOSUD_DRY_RUN', true));

// Cifrado de tokens (32 bytes en hex = 64 caracteres)
// Generar con: bin2hex(openssl_random_pseudo_bytes(32))
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', ''));
define('ENCRYPTION_CIPHER', 'aes-256-cbc');

// Validar que los secretos críticos estén configurados
if (APP_ENV === 'production') {
    if (empty(ENCRYPTION_KEY) || strlen(ENCRYPTION_KEY) !== 64) {
        throw new RuntimeException('ENCRYPTION_KEY debe estar configurada (64 caracteres hex).');
    }
}

// Rate Limiting
define('RATE_LIMIT_LOGIN_MAX', (int) env('RATE_LIMIT_LOGIN_MAX', 5));
define('RATE_LIMIT_LOGIN_WINDOW', (int) env('RATE_LIMIT_LOGIN_WINDOW', 15));
define('RATE_LIMIT_2FA_MAX', (int) env('RATE_LIMIT_2FA_MAX', 5));
define('RATE_LIMIT_2FA_WINDOW', (int) env('RATE_LIMIT_2FA_WINDOW', 15));
define('RATE_LIMIT_IMPORT_MAX', (int) env('RATE_LIMIT_IMPORT_MAX', 3));
define('RATE_LIMIT_IMPORT_WINDOW', (int) env('RATE_LIMIT_IMPORT_WINDOW', 60));
define('RATE_LIMIT_EXPORT_MAX', (int) env('RATE_LIMIT_EXPORT_MAX', 10));
define('RATE_LIMIT_EXPORT_WINDOW', (int) env('RATE_LIMIT_EXPORT_WINDOW', 60));
define('RATE_LIMIT_ADMIN_MAX', (int) env('RATE_LIMIT_ADMIN_MAX', 20));
define('RATE_LIMIT_ADMIN_WINDOW', (int) env('RATE_LIMIT_ADMIN_WINDOW', 5));
define('RATE_LIMIT_PASSWORD_RESET_MAX', (int) env('RATE_LIMIT_PASSWORD_RESET_MAX', 5));
define('RATE_LIMIT_PASSWORD_RESET_WINDOW', (int) env('RATE_LIMIT_PASSWORD_RESET_WINDOW', 15));