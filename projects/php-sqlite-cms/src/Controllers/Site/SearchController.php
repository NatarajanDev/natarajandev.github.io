<?php

declare(strict_types=1);

namespace Cms\Controllers\Site;

use Cms\Controllers\Controller;
use Cms\Support\Paginator;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Server-rendered search plus the JSON endpoint used by the live search box.
 */
final class SearchController extends Controller
{
    public function index(): Response
    {
        $request = Request::capture();
        $term = trim($request->string('q'));
        $page = max(1, $request->int('page', 1));
        $perPage = max(3, (int) $this->settings()->get('posts_per_page', '6'));

        $result = $term === ''
            ? ['items' => [], 'total' => 0]
            : $this->posts()->published($page, $perPage, ['search' => $term]);

        $paginator = new Paginator($result['items'], $result['total'], $perPage, $page, '/search', $request->query());

        $this->app->view()->setMeta([
            'title' => $term === '' ? 'Search' : 'Search: ' . $term,
            'description' => 'Search results for ' . $term,
            'robots' => 'noindex,follow',
        ]);

        return $this->view('site/search', array_merge($this->sidebarData(), [
            'term' => $term,
            'paginator' => $paginator,
            'results' => $paginator->items(),
        ]));
    }

    /** GET /api/search?q=term — used by the header search box. */
    public function api(): Response
    {
        $request = Request::capture();
        $term = trim($request->string('q'));
        if (mb_strlen($term) < 2) {
            return $this->json(['query' => $term, 'results' => []]);
        }

        $limit = min(10, max(1, $request->int('limit', 6)));
        $result = $this->posts()->published(1, $limit, ['search' => $term]);

        $results = array_map(static fn (array $post): array => [
            'title' => (string) $post['title'],
            'slug' => (string) $post['slug'],
            'url' => url('/posts/' . $post['slug']),
            'excerpt' => excerpt((string) ($post['excerpt'] ?: $post['content']), 120),
            'category' => $post['category_name'],
            'published_at' => $post['published_at'],
            'reading_time' => reading_time((string) $post['content']),
        ], $result['items']);

        return $this->json([
            'query' => $term,
            'total' => $result['total'],
            'results' => $results,
        ]);
    }
}
