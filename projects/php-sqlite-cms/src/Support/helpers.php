<?php

declare(strict_types=1);

use Cms\App;
use Cms\Support\Str;

if (!function_exists('app')) {
    function app(): App
    {
        return App::instance();
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return App::instance()->config($key, $default);
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return App::instance()->setting($key, $default);
    }
}

if (!function_exists('db')) {
    function db(): \Cms\Database\Connection
    {
        return App::instance()->db();
    }
}

if (!function_exists('cache')) {
    function cache(): \Cms\Support\Cache
    {
        return App::instance()->cache();
    }
}

if (!function_exists('auth')) {
    function auth(): \Cms\Support\Auth
    {
        return App::instance()->auth();
    }
}

if (!function_exists('flash')) {
    function flash(): \Cms\Support\Flash
    {
        return App::instance()->flash();
    }
}

if (!function_exists('view')) {
    function view(): \Cms\Support\View
    {
        return App::instance()->view();
    }
}

if (!function_exists('audit')) {
    function audit(): \Cms\Service\ActivityLog
    {
        return App::instance()->activity();
    }
}

if (!function_exists('icon')) {
    /** Inline SVG icon (stroke-based, 24x24 grid) used across the admin UI. */
    function icon(string $name, int $size = 18): string
    {
        $paths = [
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
            'file' => '<path d="M14 3v5h5"/><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2Z"/>',
            'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
            'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0l-7.2-7.2A2 2 0 0 1 3 12V5a2 2 0 0 1 2-2h7a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.6Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
            'message' => '<path d="M21 15a3 3 0 0 1-3 3H8l-5 4V6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3Z"/>',
            'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
            'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.4 5.4 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.6A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.8 1.4Z"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 15a2 2 0 1 1 0-4 1.7 1.7 0 0 0 1.5-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 4.6a2 2 0 1 1 4 0 1.7 1.7 0 0 0 2.7 1.5l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.7 1.7 0 0 0 21 11a2 2 0 1 1 0 4Z"/>',
            'tool' => '<path d="M14.7 6.3a4 4 0 0 0 5 5l-9 9a2.8 2.8 0 0 1-4-4l9-9Z"/>',
            'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
            'trash' => '<path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
            'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
            'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
            'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
            'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 9 5-5 5 5"/><path d="M12 4v12"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
            'link' => '<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
        ];

        $path = $paths[$name] ?? $paths['grid'];

        return sprintf(
            '<svg class="icon icon--%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
            e($name),
            $size,
            $size,
            $path
        );
    }
}

if (!function_exists('e')) {
    /** Escape for HTML output. Every dynamic value in a view goes through this. */
    function e(mixed $value): string
    {
        return Str::e($value === null ? '' : (is_scalar($value) ? (string) $value : (string) json_encode($value)));
    }
}

if (!function_exists('base_path')) {
    /** Base URL path the app is mounted on (e.g. "" or "/cms/public"). */
    function base_path(): string
    {
        return \Cms\Support\Request::capture()->basePath();
    }
}

if (!function_exists('url')) {
    /** Build an absolute-path URL that respects the install location. */
    function url(string $path = '/'): string
    {
        $path = '/' . ltrim($path, '/');

        return base_path() . ($path === '/' ? '/' : rtrim($path, '/'));
    }
}

if (!function_exists('asset')) {
    /** Versioned asset URL so CSS/JS changes bust caches. */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = CMS_ROOT . '/public/' . $path;
        $version = is_file($file) ? '?v=' . substr((string) filemtime($file), -6) : '';

        return base_path() . '/assets/' . $path . $version;
    }
}

if (!function_exists('absolute_url')) {
    function absolute_url(string $path = '/'): string
    {
        $configured = (string) config('app.url', '');
        $origin = $configured !== '' ? $configured : (\Cms\Support\Request::capture()->origin());
        $path = '/' . ltrim($path, '/');

        return rtrim($origin, '/') . base_path() . ($path === '/' ? '/' : rtrim($path, '/'));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \Cms\Support\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    /** Repopulate a form field after a validation failure. */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = $_SESSION['_old'] ?? [];

        return $old[$key] ?? $default;
    }
}

if (!function_exists('remember_input')) {
    function remember_input(array $input, array $except = ['password', 'password_confirmation', '_token']): void
    {
        $_SESSION['_old'] = array_diff_key($input, array_flip($except));
    }
}

if (!function_exists('clear_input')) {
    function clear_input(): void
    {
        unset($_SESSION['_old']);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): \Cms\Support\Response
    {
        return \Cms\Support\Response::redirect($path);
    }
}

if (!function_exists('json_response')) {
    function json_response(mixed $data, int $status = 200): \Cms\Support\Response
    {
        return \Cms\Support\Response::json($data, $status);
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $value): string
    {
        return Str::slug($value);
    }
}

if (!function_exists('excerpt')) {
    function excerpt(string $text, int $length = 180): string
    {
        return Str::excerpt($text, $length);
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string
    {
        return Str::timeAgo($datetime);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $datetime, string $format = 'M j, Y'): string
    {
        return $datetime === null || $datetime === '' ? '—' : Str::date($datetime, $format);
    }
}

if (!function_exists('human_bytes')) {
    function human_bytes(int $bytes): string
    {
        return Str::bytes($bytes);
    }
}

if (!function_exists('reading_time')) {
    function reading_time(string $text): int
    {
        return Str::readingTime($text);
    }
}

if (!function_exists('markdown')) {
    function markdown(string $text): string
    {
        return \Cms\Support\Markdown::toHtml($text);
    }
}

if (!function_exists('body_html')) {
    /** Render post/page bodies according to their stored format. */
    function body_html(array $row): string
    {
        $content = (string) ($row['content'] ?? '');
        $format = (string) ($row['content_format'] ?? 'markdown');

        return $format === 'html' ? $content : \Cms\Support\Markdown::toHtml($content);
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $map = [
            'published' => 'ok',
            'draft' => 'muted',
            'scheduled' => 'info',
            'archived' => 'warn',
            'pending' => 'warn',
            'approved' => 'ok',
            'spam' => 'danger',
            'trash' => 'muted',
            'active' => 'ok',
            'suspended' => 'danger',
        ];
        $tone = $map[$status] ?? 'muted';

        return '<span class="badge badge--' . e($tone) . '">' . e(ucfirst($status)) . '</span>';
    }
}

if (!function_exists('avatar_initials')) {
    function avatar_initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials;
    }
}

if (!function_exists('menu_tree')) {
    /** Ordered list of published pages flagged for the main menu. */
    function menu_tree(): array
    {
        static $menu = null;
        if ($menu === null) {
            $menu = cache()->remember('menu.pages', 300, static fn (): array => db()->all(
                'SELECT id, title, slug, parent_id FROM pages
                 WHERE status = ? AND show_in_menu = 1
                 ORDER BY sort_order ASC, title ASC',
                ['published']
            ));
        }

        return $menu;
    }
}
