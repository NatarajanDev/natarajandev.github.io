<?php
/**
 * Front controller — every request enters here.
 *
 * Works behind Apache (.htaccess), Nginx (try_files) or the bundled
 * development server: php -S localhost:8080 -t public public/router.php
 */

declare(strict_types=1);

use Cms\Support\Request;
use Cms\Support\Response;
use Cms\Support\Router;

/** @var \Cms\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$router = new Router();
$request = Request::capture();

// ---------------------------------------------------------------------------
// Installation guard: until an administrator exists, everything is routed to
// the installer except static assets and the health probe.
// ---------------------------------------------------------------------------
$installed = $app->installer()->isInstalled();
$isInstallRoute = str_starts_with($request->path(), '/install');

if (!$installed && !$isInstallRoute) {
    header('Location: ' . url('/install'), true, 302);
    exit;
}

if ($installed && $isInstallRoute) {
    header('Location: ' . url('/admin/login'), true, 302);
    exit;
}

require dirname(__DIR__) . '/routes/site.php';
require dirname(__DIR__) . '/routes/admin.php';

$response = $router->dispatch($request, $app);

if (!$response instanceof Response) {
    $response = Response::make((string) $response);
}

// Baseline security headers; adjust in your web server if you terminate TLS elsewhere.
$response = $response
    ->withHeader('X-Content-Type-Options', 'nosniff')
    ->withHeader('X-Frame-Options', 'SAMEORIGIN')
    ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
    ->withHeader('X-Powered-By', '');

$response->send();
