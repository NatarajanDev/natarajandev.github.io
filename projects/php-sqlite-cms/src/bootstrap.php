<?php
/**
 * Application bootstrap.
 *
 * Requires: config/config.php (+ optional config/config.local.php).
 * Sets up autoloading, error handling, session storage and global helpers.
 *
 * This file is loaded by public/index.php (web) and bin/*.php (CLI).
 */

declare(strict_types=1);

use Cms\App;
use Cms\Support\Logger;

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}
if (!defined('CMS_START')) {
    define('CMS_START', microtime(true));
}

// ---------------------------------------------------------------- autoloading
spl_autoload_register(static function (string $class): void {
    $prefix = 'Cms\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = CMS_ROOT . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ------------------------------------------------------------------- config
/** @var array<string, mixed> $config */
$config = require CMS_ROOT . '/config/config.php';
$localConfig = CMS_ROOT . '/config/config.local.php';
if (is_file($localConfig)) {
    $override = require $localConfig;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

date_default_timezone_set((string) ($config['app']['timezone'] ?? 'UTC'));
mb_internal_encoding('UTF-8');

$app = App::boot($config);

// ------------------------------------------------------- error + log handling
$logger = $app->log();
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0) use ($logger): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$debug = $app->isDebug();
ini_set('display_errors', $debug ? '1' : '0');

set_exception_handler(static function (Throwable $e) use ($logger, $app, $debug): void {
    $logger->error(sprintf(
        '%s: %s in %s:%d',
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ), ['trace' => $e->getTraceAsString()]);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, '[error] ' . $e->getMessage() . "\n");
        exit(1);
    }

    http_response_code(500);
    if ($debug) {
        echo '<pre style="padding:1.5rem;font:14px/1.6 ui-monospace,monospace;background:#111;color:#f87171">';
        echo htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    } else {
        $file = CMS_ROOT . '/views/errors/500.php';
        if (is_file($file)) {
            require $file;
        } else {
            echo 'Internal Server Error';
        }
    }
});

// ---------------------------------------------------------------- session
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $sessionDir = $app->path('storage', 'sessions');
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0o775, true);
    }

    // Some hosts (and the WASM CLI SAPI) point PHP at a non-writable directory.
    $savePath = is_dir($sessionDir) && is_writable($sessionDir) ? $sessionDir : sys_get_temp_dir();
    session_save_path($savePath);
    ini_set('session.gc_maxlifetime', (string) ((int) $app->config('security.session_lifetime', 7200)));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_name((string) $app->config('security.session_name', 'cms_session'));
    session_start();
}

require __DIR__ . '/Support/helpers.php';

return $app;
