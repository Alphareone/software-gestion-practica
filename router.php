<?php
// router.php para simular las reglas de reescritura de Apache (.htaccess) en el servidor local de PHP.
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Resolver la ruta del archivo físico dentro de public_html
$cleanUri = preg_replace('/^\/public_html/', '', $uri);
$file = __DIR__ . '/public_html' . $cleanUri;

// Si el archivo físico existe y no es un directorio, que el servidor lo sirva directamente (CSS, JS, imágenes)
if ($uri !== '/' && is_file($file)) {
    return false;
}

// De lo contrario, simular la reescritura mandando el parámetro a index.php
$url = ltrim($uri, '/');
if (strpos($url, 'public_html/') === 0) {
    $url = substr($url, 12);
}

$_GET['url'] = $url;
require_once __DIR__ . '/public_html/index.php';
