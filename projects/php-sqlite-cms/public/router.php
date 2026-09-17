<?php
/**
 * Router for the PHP built-in development server.
 *
 *   php -S localhost:8080 -t public public/router.php
 *
 * Serves real files (CSS, JS, uploads) directly and sends everything else to
 * the front controller, which mirrors what the Apache/Nginx rules do.
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file) && !str_ends_with($path, '.php')) {
    return false; // let the built-in server stream the static asset
}

require __DIR__ . '/index.php';
