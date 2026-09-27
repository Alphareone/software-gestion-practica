<?php
/**
 * Bootstrap para scripts CLI (cron jobs, sincronizaciones, etc.)
 * Carga autoloader + configuración + conexión a DB.
 * Sin sesión, sin headers HTTP.
 */

// Cargar autoloader de Composer
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Cargar configuración (define constantes, carga .env)
require_once dirname(__DIR__, 2) . '/app/config/config.php';

// Configurar errores para CLI
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
