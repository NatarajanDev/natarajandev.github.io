<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * CSRF protection: one token per session, verified with a timing-safe compare.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    public static function verify(?string $token): bool
    {
        $session = $_SESSION[self::KEY] ?? null;
        if (!is_string($session) || $session === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($session, $token);
    }

    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
