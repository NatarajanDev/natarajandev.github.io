<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * One-shot session messages ("Post saved", "Invalid credentials", ...).
 */
final class Flash
{
    private const KEY = '_flash';

    public function add(string $type, string $message): void
    {
        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }

    public function success(string $message): void
    {
        $this->add('success', $message);
    }

    public function error(string $message): void
    {
        $this->add('error', $message);
    }

    public function info(string $message): void
    {
        $this->add('info', $message);
    }

    public function warning(string $message): void
    {
        $this->add('warning', $message);
    }

    /** @return list<array{type: string, message: string}> */
    public function pull(): array
    {
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);

        return is_array($messages) ? array_values($messages) : [];
    }

    public function has(): bool
    {
        return !empty($_SESSION[self::KEY]);
    }
}
