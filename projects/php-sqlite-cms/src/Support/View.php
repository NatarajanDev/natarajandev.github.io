<?php

declare(strict_types=1);

namespace Cms\Support;

use Cms\App;

/**
 * Plain-PHP template renderer with layout + meta support.
 *
 * Views live in /views/<name>.php and receive every data key as a local
 * variable plus `$view` (this object). A layout is rendered afterwards with the
 * view output available as `$content`.
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    public function __construct(private App $app)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function setMeta(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    /** @param array<string, mixed> $values */
    public function meta(array $values): void
    {
        $this->meta = array_merge($this->meta, $values);
    }

    /** @return array<string, mixed> */
    public function metaValues(): array
    {
        return $this->meta;
    }

    public function resetMeta(): void
    {
        $this->meta = [];
    }

    /**
     * Render a template, optionally wrapped in a layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $layout ??= $this->defaultLayout($template);
        $content = $this->capture($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->capture($layout, array_merge($data, ['content' => $content]), null, true);
    }

    /** Render a fragment (no layout) — used for partials and emails. */
    public function partial(string $template, array $data = []): string
    {
        return $this->capture($this->normalise($template, 'partials'), $data, null, true);
    }

    /** Render any template file without layout. */
    public function fragment(string $template, array $data = []): string
    {
        return $this->capture($template, $data, null, true);
    }

    private function normalise(string $template, string $folder): string
    {
        return str_starts_with($template, $folder . '/') ? $template : $folder . '/' . $template;
    }

    private function defaultLayout(string $template): ?string
    {
        return match (true) {
            str_starts_with($template, 'auth/') => 'layouts/auth',
            str_starts_with($template, 'admin/') => 'layouts/admin',
            str_starts_with($template, 'site/') => 'layouts/site',
            default => null,
        };
    }

    /** @param array<string, mixed> $data */
    private function capture(string $template, array $data, ?string $layout = null, bool $inheritShared = true): string
    {
        $file = $this->resolve($template);

        // Settings come from the database: a broken schema must still render
        // the error pages, so fall back to an empty set.
        try {
            $settings = $this->app->settings()->all();
        } catch (\Throwable) {
            $settings = [];
        }

        $variables = array_merge($inheritShared ? $this->shared : [], $data, [
            'view' => $this,
            'meta' => $this->meta,
            'app' => $this->app,
            'settings' => $settings,
        ]);

        extract($variables, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    private function resolve(string $template): string
    {
        $template = str_replace(['..', '\\'], '', $template);
        $file = $this->app->path('views', $template . '.php');
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        return $file;
    }
}
