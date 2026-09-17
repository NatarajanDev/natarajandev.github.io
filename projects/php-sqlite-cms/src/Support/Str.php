<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * Small, dependency-free string helpers.
 */
final class Str
{
    /** Turn any headline into a URL-safe slug. */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = trim($value);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value) ?? '';
        $value = trim($value, $separator);

        return $value === '' ? 'item' : $value;
    }

    /** Shorten plain text on a word boundary. */
    public static function excerpt(string $text, int $length = 180, string $suffix = '…'): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut) . $suffix;
    }

    /** Escape a value for HTML output. */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Human friendly relative time, e.g. "3 hours ago". */
    public static function timeAgo(?string $datetime, ?\DateTimeImmutable $now = null): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }

        try {
            $then = new \DateTimeImmutable($datetime);
        } catch (\Exception) {
            return '—';
        }

        $now ??= new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $then->getTimestamp();
        $future = $diff < 0;
        $diff = abs($diff);

        $units = [
            ['year', 31536000],
            ['month', 2592000],
            ['week', 604800],
            ['day', 86400],
            ['hour', 3600],
            ['minute', 60],
        ];

        foreach ($units as [$label, $seconds]) {
            if ($diff >= $seconds) {
                $count = (int) floor($diff / $seconds);
                $plural = $count === 1 ? '' : 's';

                return $future
                    ? sprintf('in %d %s%s', $count, $label, $plural)
                    : sprintf('%d %s%s ago', $count, $label, $plural);
            }
        }

        return $future ? 'in a moment' : 'just now';
    }

    /** Format a date for display using the configured timezone. */
    public static function date(string $datetime, string $format = 'M j, Y'): string
    {
        try {
            return (new \DateTimeImmutable($datetime))->format($format);
        } catch (\Exception) {
            return '—';
        }
    }

    /** "2.4 MB" style byte formatting. */
    public static function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return sprintf('%s %s', $i === 0 ? (string) $value : number_format($value, 1), $units[$i]);
    }

    /** Reading time estimate in whole minutes. */
    public static function readingTime(string $text): int
    {
        $words = str_word_count(strip_tags($text));

        return max(1, (int) ceil($words / 220));
    }
}
