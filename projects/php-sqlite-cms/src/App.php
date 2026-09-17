<?php

declare(strict_types=1);

namespace Cms;

use Cms\Database\Connection;
use Cms\Repository\SettingRepository;
use Cms\Service\ActivityLog;
use Cms\Service\DashboardStats;
use Cms\Service\Installer;
use Cms\Support\Auth;
use Cms\Support\Cache;
use Cms\Support\Container;
use Cms\Support\Flash;
use Cms\Support\Logger;
use Cms\Support\Uploader;
use Cms\Support\View;

/**
 * Service registry / application container.
 *
 * Services are lazily created on first use so a request only pays for what it
 * touches (a static asset or a JSON API call never opens the database).
 */
final class App
{
    private static ?App $instance = null;

    private Container $container;

    /** @param array<string, mixed> $config */
    private function __construct(private array $config)
    {
        $this->container = new Container();
        $this->registerCoreServices();
    }

    /** @param array<string, mixed> $config */
    public static function boot(array $config): self
    {
        return self::$instance = new self($config);
    }

    public static function instance(): self
    {
        if (!self::$instance instanceof App) {
            throw new \RuntimeException('Application has not been booted yet.');
        }

        return self::$instance;
    }

    /** Read a config value with dot notation, e.g. config('database.sqlite'). */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function path(string $key, string $suffix = ''): string
    {
        $base = (string) $this->config('paths.' . $key, '');

        return $suffix === '' ? $base : $base . '/' . ltrim($suffix, '/');
    }

    public function isDebug(): bool
    {
        return (bool) $this->config('app.debug', false);
    }

    public function container(): Container
    {
        return $this->container;
    }

    private function registerCoreServices(): void
    {
        $this->container->singleton(Connection::class, fn (): Connection => new Connection((array) $this->config('database')));
        $this->container->singleton(Logger::class, fn (): Logger => new Logger($this->path('storage', 'logs')));
        $this->container->singleton(Cache::class, fn (): Cache => new Cache($this->path('storage', 'cache')));
        $this->container->singleton(Flash::class, static fn (): Flash => new Flash());
        $this->container->singleton(Auth::class, fn (): Auth => new Auth($this));
        $this->container->singleton(View::class, fn (): View => new View($this));
        $this->container->singleton(SettingRepository::class, fn (): SettingRepository => new SettingRepository($this->db()));
        $this->container->singleton(ActivityLog::class, fn (): ActivityLog => new ActivityLog($this));
        $this->container->singleton(Uploader::class, fn (): Uploader => new Uploader($this));
        $this->container->singleton(Installer::class, fn (): Installer => new Installer($this));
        $this->container->singleton(DashboardStats::class, fn (): DashboardStats => new DashboardStats($this));
    }

    public function db(): Connection
    {
        return $this->container->get(Connection::class);
    }

    public function log(): Logger
    {
        return $this->container->get(Logger::class);
    }

    public function cache(): Cache
    {
        return $this->container->get(Cache::class);
    }

    public function view(): View
    {
        return $this->container->get(View::class);
    }

    public function auth(): Auth
    {
        return $this->container->get(Auth::class);
    }

    public function flash(): Flash
    {
        return $this->container->get(Flash::class);
    }

    /** Settings are cached in-process; the store is tiny (key/value). */
    public function settings(): SettingRepository
    {
        return $this->container->get(SettingRepository::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings()->get($key, $default);
    }

    public function activity(): ActivityLog
    {
        return $this->container->get(ActivityLog::class);
    }

    public function uploader(): Uploader
    {
        return $this->container->get(Uploader::class);
    }

    public function installer(): Installer
    {
        return $this->container->get(Installer::class);
    }

    public function dashboard(): DashboardStats
    {
        return $this->container->get(DashboardStats::class);
    }
}
