<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicDir = __DIR__;

$filePath = $publicDir . $uri;

if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

$_GET['url'] = ltrim($uri, '/');
$_SERVER['SCRIPT_NAME'] = '/index.php';

require $publicDir . '/index.php';
