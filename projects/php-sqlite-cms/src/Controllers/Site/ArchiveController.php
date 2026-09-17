<?php

declare(strict_types=1);

namespace Cms\Controllers\Site;

use Cms\Controllers\Controller;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Listings: all posts, categories, tags, authors and date archives.
 */
final class ArchiveController extends Controller
{
    public function index(): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = max(3, (int) $this->settings()->get('posts_per_page', '6'));
        $filters = array_filter([
            'category_slug' => $request->string('category'),
            'tag_slug' => $request->string('tag'),
            'search' => $request->string('q'),
            'year' => $request->int('year') ?: null,
            'month' => $request->int('month') ?: null,
        ]);

        $result = $this->posts()->published($page, $perPage, $filters);
        $paginator = new \Cms\Support\Paginator($result['items'], $result['total'], $perPage, $page, '/posts', $request->query());

        $this->app->view()->setMeta([
            'title' => 'All articles',
            'description' => 'Every published article on ' . $this->settings()->get('site_title') . '.',
            'canonical' => absolute_url('/posts'),
        ]);

        return $this->view('site/archive', array_merge($this->sidebarData(), [
            'heading' => 'All articles',
            'subheading' => $paginator->summary(),
            'paginator' => $paginator,
            'posts' => $paginator->items(),
            'activeFilter' => $filters,
        ]));
    }

    public function category(string $slug): Response
    {
        $category = $this->categories()->findBySlug($slug);
        if ($category === null) {
            return $this->miss('Category not found');
        }

        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = max(3, (int) $this->settings()->get('posts_per_page', '6'));
        $result = $this->posts()->published($page, $perPage, ['category_id' => (int) $category['id']]);
        $paginator = new \Cms\Support\Paginator($result['items'], $result['total'], $perPage, $page, '/category/' . $category['slug'], $request->query());

        $this->app->view()->setMeta([
            'title' => $category['name'] . ' — articles',
            'description' => (string) ($category['description'] ?: $paginator->summary()),
            'canonical' => absolute_url('/category/' . $category['slug']),
        ]);

        return $this->view('site/archive', array_merge($this->sidebarData(), [
            'heading' => (string) $category['name'],
            'subheading' => (string) ($category['description'] ?: $paginator->summary()),
            'paginator' => $paginator,
            'posts' => $paginator->items(),
            'activeFilter' => ['category' => $category['slug']],
            'eyebrow' => 'Category',
        ]));
    }

    public function tag(string $slug): Response
    {
        $tag = $this->tags()->findBySlug($slug);
        if ($tag === null) {
            return $this->miss('Tag not found');
        }

        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = max(3, (int) $this->settings()->get('posts_per_page', '6'));
        $result = $this->posts()->published($page, $perPage, ['tag_slug' => $tag['slug']]);
        $paginator = new \Cms\Support\Paginator($result['items'], $result['total'], $perPage, $page, '/tag/' . $tag['slug'], $request->query());

        $this->app->view()->setMeta([
            'title' => '#' . $tag['name'],
            'description' => 'Articles tagged ' . $tag['name'] . '.',
            'canonical' => absolute_url('/tag/' . $tag['slug']),
        ]);

        return $this->view('site/archive', array_merge($this->sidebarData(), [
            'heading' => '#' . $tag['name'],
            'subheading' => $paginator->summary(),
            'paginator' => $paginator,
            'posts' => $paginator->items(),
            'activeFilter' => ['tag' => $tag['slug']],
            'eyebrow' => 'Tag',
        ]));
    }

    public function author(string $slug): Response
    {
        $author = $this->users()->findByNameSlug($slug);
        if ($author === null) {
            return $this->miss('Author not found');
        }

        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = max(3, (int) $this->settings()->get('posts_per_page', '6'));
        $result = $this->posts()->published($page, $perPage, ['user_id' => (int) $author['id']]);
        $paginator = new \Cms\Support\Paginator($result['items'], $result['total'], $perPage, $page, '/author/' . str_slug((string) $author['name']), $request->query());

        $this->app->view()->setMeta([
            'title' => 'Articles by ' . $author['name'],
            'description' => (string) ($author['bio'] ?? ''),
            'canonical' => absolute_url('/author/' . str_slug((string) $author['name'])),
        ]);

        return $this->view('site/archive', array_merge($this->sidebarData(), [
            'heading' => (string) $author['name'],
            'subheading' => (string) ($author['bio'] ?? $paginator->summary()),
            'paginator' => $paginator,
            'posts' => $paginator->items(),
            'activeFilter' => ['author' => $slug],
            'eyebrow' => 'Author',
            'author' => $author,
        ]));
    }

    public function date(int $year, int $month = 0): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = max(3, (int) $this->settings()->get('posts_per_page', '6'));
        $filters = $month > 0 ? ['year' => $year, 'month' => $month] : ['year' => $year];
        $result = $this->posts()->published($page, $perPage, $filters);
        $path = $month > 0 ? sprintf('/archive/%d/%02d', $year, $month) : sprintf('/archive/%d', $year);
        $paginator = new \Cms\Support\Paginator($result['items'], $result['total'], $perPage, $page, $path, $request->query());

        $label = $month > 0
            ? (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('F Y')
            : (string) $year;

        $this->app->view()->setMeta([
            'title' => 'Archive: ' . $label,
            'canonical' => absolute_url($path),
        ]);

        return $this->view('site/archive', array_merge($this->sidebarData(), [
            'heading' => $label,
            'subheading' => $paginator->summary(),
            'paginator' => $paginator,
            'posts' => $paginator->items(),
            'activeFilter' => ['year' => $year, 'month' => $month],
            'eyebrow' => 'Archive',
        ]));
    }

    private function miss(string $title): Response
    {
        return new Response($this->app->view()->render('errors/404', ['meta' => ['title' => $title]]), 404);
    }
}
