<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * File cache with TTL, used for expensive-but-stable queries (widgets,
 * dashboard counts, sitemap). No external dependency, safe to delete.
 */
final class Cache
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0o775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return $default;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return $default;
        }

        $payload = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            @unlink($file);

            return $default;
        }

        if ($payload['expires'] !== 0 && (int) $payload['expires'] < time()) {
            @unlink($file);

            return $default;
        }

        return $payload['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): void
    {
        $payload = serialize([
            'expires' => $ttl === 0 ? 0 : time() + $ttl,
            'value' => $value,
        ]);

        $file = $this->path($key);
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($temporary, $payload) !== false) {
            @rename($temporary, $file);
        }
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key, '__miss__');
        if ($cached !== '__miss__') {
            return $cached;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    public function forget(string $key): void
    {
        @unlink($this->path($key));
    }

    /** Drop every cached entry. Returns the number of files removed. */
    public function flush(): int
    {
        $removed = 0;
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    /** @return array{files: int, bytes: int} */
    public function stats(): array
    {
        $files = glob($this->directory . '/*.cache') ?: [];
        $bytes = 0;
        foreach ($files as $file) {
            $bytes += (int) @filesize($file);
        }

        return ['files' => count($files), 'bytes' => $bytes];
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . sha1($key) . '.cache';
    }
}
