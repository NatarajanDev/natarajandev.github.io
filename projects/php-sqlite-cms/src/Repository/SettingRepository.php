<?php

declare(strict_types=1);

namespace Cms\Repository;

use Cms\Database\Connection;

/**
 * Key/value site settings with sensible defaults and an in-process cache.
 */
final class SettingRepository extends Repository
{
    /** @var array<string, string|null>|null */
    private ?array $cache = null;

    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'site_title' => 'Nova CMS',
        'site_tagline' => 'A content platform built with PHP and SQLite',
        'site_description' => 'Nova CMS is a lightweight publishing platform with a full admin panel, markdown authoring, media library and comment moderation.',
        'site_keywords' => 'php, sqlite, cms, blog, admin panel',
        'posts_per_page' => '6',
        'default_status' => 'draft',
        'comments_enabled' => '1',
        'comment_moderation' => '1',
        'comment_auto_approve_known' => '1',
        'theme_accent' => '#6366f1',
        'theme_mode' => 'dark',
        'footer_text' => 'Built with Nova CMS — PHP 8, SQLite and vanilla JavaScript.',
        'social_twitter' => '',
        'social_github' => '',
        'social_linkedin' => '',
        'seo_title_template' => '%s — %s',
        'analytics_snippet' => '',
        'contact_email' => 'hello@example.com',
        'timezone_display' => 'UTC',
        'maintenance_mode' => '0',
    ];

    public function __construct(Connection $db)
    {
        parent::__construct($db);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->db->all('SELECT key, value FROM settings') as $row) {
                $this->cache[(string) $row['key']] = $row['value'];
            }
        }

        return array_merge(self::DEFAULTS, array_filter($this->cache, static fn ($v): bool => $v !== null));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        $now = $this->now();

        foreach ($values as $key => $value) {
            $key = (string) $key;
            $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $existing = $this->db->first('SELECT key FROM settings WHERE key = ?', [$key]);

            if ($existing === null) {
                $this->db->insert('settings', ['key' => $key, 'value' => $stringValue, 'updated_at' => $now]);
            } else {
                $this->db->update('settings', ['value' => $stringValue, 'updated_at' => $now], 'key', $key);
            }

            $this->cache[$key] = $stringValue;
        }
    }

    public function forget(string $key): void
    {
        $this->db->delete('settings', 'key = ?', [$key]);
        unset($this->cache[$key]);
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return self::DEFAULTS;
    }

    public function seedDefaults(): void
    {
        $this->setMany(self::DEFAULTS);
    }

    public function refresh(): void
    {
        $this->cache = null;
    }
}
