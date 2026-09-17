<?php

declare(strict_types=1);

namespace Cms\Controllers;

use Cms\App;
use Cms\Support\Request;
use Cms\Support\Response;
use Cms\Support\Validator;

/**
 * Shared controller behaviour: view rendering, validation and redirects.
 */
abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return new Response($this->app->view()->render($template, $data), $status);
    }

    /** @param array<string, mixed> $data */
    protected function fragment(string $template, array $data = []): string
    {
        return $this->app->view()->render($template, $data);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect($path, $status);
    }

    protected function back(string $fallback = '/'): Response
    {
        $referer = Request::capture()->header('referer');

        return Response::redirect($referer !== null && $referer !== '' ? $referer : url($fallback));
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    protected function validate(array $data, array $rules, array $labels = []): Validator
    {
        return Validator::make($data, $rules, $labels);
    }

    /** Store errors + old input, then send the user back to the form. */
    protected function validationFailed(Validator $validator, string $path, array $input, array $except = ['password', 'password_confirmation', '_token']): Response
    {
        $_SESSION['_errors'] = $validator->errors();
        remember_input($input, $except);
        $this->app->flash()->error(implode(' ', $validator->flatErrors()));

        return $this->redirect($path);
    }

    /** @return array<string, list<string>> */
    protected function sessionErrors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);

        return is_array($errors) ? $errors : [];
    }

    protected function requirePost(): void
    {
        if (!Request::capture()->isPost()) {
            throw new \RuntimeException('This endpoint only accepts POST requests.');
        }
    }

    protected function settings(): \Cms\Repository\SettingRepository
    {
        return $this->app->settings();
    }

    protected function posts(): \Cms\Repository\PostRepository
    {
        return new \Cms\Repository\PostRepository($this->app->db());
    }

    protected function pages(): \Cms\Repository\PageRepository
    {
        return new \Cms\Repository\PageRepository($this->app->db());
    }

    protected function categories(): \Cms\Repository\CategoryRepository
    {
        return new \Cms\Repository\CategoryRepository($this->app->db());
    }

    protected function tags(): \Cms\Repository\TagRepository
    {
        return new \Cms\Repository\TagRepository($this->app->db());
    }

    protected function comments(): \Cms\Repository\CommentRepository
    {
        return new \Cms\Repository\CommentRepository($this->app->db());
    }

    protected function media(): \Cms\Repository\MediaRepository
    {
        return new \Cms\Repository\MediaRepository($this->app->db());
    }

    protected function users(): \Cms\Repository\UserRepository
    {
        return new \Cms\Repository\UserRepository($this->app->db());
    }

    protected function messages(): \Cms\Repository\MessageRepository
    {
        return new \Cms\Repository\MessageRepository($this->app->db());
    }

    /** Sidebar widgets shared by every public page. */
    protected function sidebarData(): array
    {
        $posts = $this->posts();

        return [
            'sidebarPopular' => $this->app->cache()->remember('sidebar.popular', 300, fn (): array => $posts->popular(5, 60)),
            'sidebarTags' => $this->app->cache()->remember('sidebar.tags', 300, fn (): array => $this->tags()->popular(14)),
            'sidebarArchive' => $this->app->cache()->remember('sidebar.archive', 600, fn (): array => $posts->archive()),
            'sidebarCategories' => $this->app->cache()->remember('sidebar.categories', 600, fn (): array => $this->categories()->popular(6)),
        ];
    }
}
