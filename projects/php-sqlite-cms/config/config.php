<?php
/**
 * Application configuration.
 *
 * Values may be overridden with environment variables (see .env.example) so the
 * same code runs on a laptop, in Docker or on a shared PHP host.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$env = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
};

return [
    'app' => [
        'name' => $env('CMS_APP_NAME', 'Nova CMS'),
        'env' => $env('CMS_ENV', 'production'),
        'debug' => filter_var($env('CMS_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
        'url' => rtrim((string) $env('CMS_URL', 'http://localhost:8080'), '/'),
        'timezone' => $env('CMS_TIMEZONE', 'UTC'),
        'key' => $env('CMS_KEY', 'change-me-development-key'),
    ],

    'paths' => [
        'root' => $root,
        'src' => $root . '/src',
        'views' => $root . '/views',
        'storage' => $env('CMS_STORAGE_PATH', $root . '/storage'),
        'migrations' => $root . '/database/migrations',
        'uploads' => $env('CMS_UPLOAD_PATH', $root . '/public/uploads'),
    ],

    'database' => [
        // SQLite by default. Set CMS_DB_DRIVER=mysql (+ host/name/user/pass) for MySQL.
        'driver' => $env('CMS_DB_DRIVER', 'sqlite'),
        'sqlite' => $env('CMS_DB_PATH', $root . '/storage/cms.sqlite'),
        'mysql' => [
            'host' => $env('CMS_DB_HOST', '127.0.0.1'),
            'port' => (int) $env('CMS_DB_PORT', '3306'),
            'database' => $env('CMS_DB_NAME', 'nova_cms'),
            'username' => $env('CMS_DB_USER', 'root'),
            'password' => $env('CMS_DB_PASS', ''),
            'charset' => 'utf8mb4',
        ],
    ],

    'security' => [
        'session_name' => $env('CMS_SESSION_NAME', 'nova_cms_session'),
        'session_lifetime' => (int) $env('CMS_SESSION_LIFETIME', '7200'),
        'login_max_attempts' => (int) $env('CMS_LOGIN_MAX_ATTEMPTS', '5'),
        'login_lockout_minutes' => (int) $env('CMS_LOGIN_LOCKOUT', '15'),
        'password_min_length' => 8,
    ],

    'uploads' => [
        'max_bytes' => (int) $env('CMS_UPLOAD_MAX_BYTES', (string) (5 * 1024 * 1024)),
        'allowed_mime' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
        ],
    ],

    'content' => [
        'posts_per_page' => 6,
        'admin_per_page' => 15,
        'excerpt_length' => 180,
        'comment_moderation' => true,
    ],
];
