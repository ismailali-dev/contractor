<?php

/**
 * Local development router for PHP built-in server.
 *
 * This repo is missing Laravel's default `server.php`, but `php -S ... server.php`
 * is the most reliable way to run locally when we need custom PHP ini flags.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

// Serve existing files directly.
$publicPath = __DIR__ . DIRECTORY_SEPARATOR . 'public';
$file = $publicPath . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}

require_once $publicPath . DIRECTORY_SEPARATOR . 'index.php';

