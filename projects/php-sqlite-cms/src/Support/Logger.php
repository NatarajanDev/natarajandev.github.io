<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * JSON-lines file logger (one file per day) with a small reader for the
 * admin "System log" screen.
 */
final class Logger
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0o775, true);
        }
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'time' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'ip' => PHP_SAPI === 'cli' ? null : Request::capture()->ip(),
            'user_id' => $_SESSION['auth_user_id'] ?? null,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($this->path(), (string) $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @return list<array{time: string, level: string, message: string, context: array<string, mixed>}> */
    public function tail(int $lines = 100, ?string $date = null): array
    {
        $file = $this->directory . '/cms-' . ($date ?? date('Y-m-d')) . '.log';
        if (!is_file($file)) {
            return [];
        }

        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return [];
        }

        $rawLines = array_filter(explode(PHP_EOL, trim($content)), static fn (string $l): bool => $l !== '');
        $rawLines = array_slice($rawLines, -$lines);

        $entries = [];
        foreach ($rawLines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return array_reverse($entries);
    }

    /** @return list<string> */
    public function dates(): array
    {
        $dates = [];
        foreach (glob($this->directory . '/cms-*.log') ?: [] as $file) {
            if (preg_match('/cms-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m) === 1) {
                $dates[] = $m[1];
            }
        }
        rsort($dates);

        return $dates;
    }

    public function prune(int $days = 14): int
    {
        $removed = 0;
        $threshold = (new \DateTimeImmutable("-{$days} days"))->getTimestamp();
        foreach (glob($this->directory . '/cms-*.log') ?: [] as $file) {
            if ((int) @filemtime($file) < $threshold && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    public function clear(?string $date = null): void
    {
        $file = $this->directory . '/cms-' . ($date ?? date('Y-m-d')) . '.log';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function path(): string
    {
        return $this->directory . '/cms-' . date('Y-m-d') . '.log';
    }
}
