<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * Immutable view of the current HTTP request.
 */
final class Request
{
    private static ?Request $current = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, array<string, mixed>> $files
     * @param array<string, string> $server
     * @param array<string, array<string, mixed>> $cookies
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $files,
        private array $server,
        private array $cookies,
        private string $rawBody = ''
    ) {
    }

    public static function capture(): self
    {
        if (self::$current instanceof Request) {
            return self::$current;
        }

        $server = $_SERVER;
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

        // Allow method spoofing from HTML forms: <input name="_method" value="PUT">
        $body = $_POST;
        if ($method === 'POST' && isset($body['_method'])) {
            $spoofed = strtoupper((string) $body['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoofed;
            }
        }

        $rawBody = (string) file_get_contents('php://input');
        $contentType = (string) ($server['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json') && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        // Normalise so /a/b/ and /a/b behave the same.
        $path = '/' . trim(rawurldecode($path), '/');

        self::$current = new self($method, $path, $_GET, $body, self::normaliseFiles($_FILES), $server, $_COOKIE, $rawBody);

        return self::$current;
    }

    /** @param array<string, array<string, mixed>> $files */
    private static function normaliseFiles(array $files): array
    {
        /** @var array<string, array<string, mixed>> $files */
        return $files;
    }

    /** Works out the URL prefix the app is served from (sub-directory friendly). */
    public function basePath(): string
    {
        $script = (string) ($this->server['SCRIPT_NAME'] ?? '');
        if (!str_ends_with($script, '.php')) {
            return '';
        }

        $dir = str_replace('\\', '/', dirname($script));

        return $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
    }

    public function method(): string
    {
        return $this->method;
    }

    /** Request path with the base path stripped: always starts with "/". */
    public function path(): string
    {
        $base = $this->basePath();
        if ($base !== '' && str_starts_with($this->path, $base)) {
            $trimmed = substr($this->path, strlen($base));
            $path = $trimmed === '' ? '/' : $trimmed;

            return $path === '' ? '/' : $path;
        }

        return $this->path === '' ? '/' : $this->path;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isJson(): bool
    {
        return str_contains((string) ($this->server['CONTENT_TYPE'] ?? ''), 'application/json')
            || str_contains((string) ($this->server['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key, null);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /** @return list<string> */
    public function arrayOfStrings(string $key): array
    {
        $value = $this->input($key, []);
        if (!is_array($value)) {
            return $value === null || $value === '' ? [] : [(string) $value];
        }

        return array_values(array_map('strval', $value));
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$key]) ? (string) $this->server[$key] : $default;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return isset($this->cookies[$name]) ? (string) $this->cookies[$name] : $default;
    }

    public function ip(): string
    {
        $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($candidates as $key) {
            $value = (string) ($this->server[$key] ?? '');
            if ($value === '') {
                continue;
            }
            $first = trim(explode(',', $value)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        $https = (string) ($this->server['HTTPS'] ?? '');

        return ($https !== '' && $https !== 'off') || ($this->server['SERVER_PORT'] ?? '') === '443';
    }

    public function origin(): string
    {
        $host = (string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost');

        return ($this->isSecure() ? 'https' : 'http') . '://' . $host;
    }

    public function fullUrl(): string
    {
        return $this->origin() . (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');

        return $this->isJson() || str_starts_with($this->path(), '/api/') || str_contains($accept, 'application/json');
    }

    /** Override the captured request (used by tests). */
    public static function swap(?Request $request): void
    {
        self::$current = $request;
    }
}
