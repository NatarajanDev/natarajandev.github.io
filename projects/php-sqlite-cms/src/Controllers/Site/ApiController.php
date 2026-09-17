<?php

declare(strict_types=1);

namespace Cms\Controllers\Site;

use Cms\Controllers\Controller;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Small public JSON API. Everything is read-only except the view counter.
 *
 * GET  /api/posts            ?page=&per_page=&category=&tag=&q=
 * GET  /api/posts/{slug}
 * GET  /api/categories
 * GET  /api/tags
 * GET  /api/comments/{postId}
 * POST /api/posts/{slug}/view
 * GET  /api/stats
 */
final class ApiController extends Controller
{
    public function posts(): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = min(24, max(1, $request->int('per_page', 6)));
        $filters = array_filter([
            'category_id' => $request->int('category') ?: null,
            'category_slug' => $request->string('category_slug'),
            'tag_slug' => $request->string('tag'),
            'search' => $request->string('q'),
        ]);

        $result = $this->posts()->published($page, $perPage, $filters);
        $tags = $this->posts()->tagsForMany(array_map(static fn (array $p): int => (int) $p['id'], $result['items']));

        $items = array_map(static function (array $post) use ($tags): array {
            return [
                'id' => (int) $post['id'],
                'title' => (string) $post['title'],
                'slug' => (string) $post['slug'],
                'url' => url('/posts/' . $post['slug']),
                'excerpt' => (string) ($post['excerpt'] ?: excerpt((string) $post['content'], 180)),
                'category' => $post['category_name'] === null ? null : [
                    'name' => (string) $post['category_name'],
                    'slug' => (string) $post['category_slug'],
                ],
                'tags' => $tags[(int) $post['id']] ?? [],
                'author' => ['name' => (string) ($post['author_name'] ?? '')],
                'cover_image' => $post['cover_image'] === null ? null : asset((string) $post['cover_image']),
                'reading_time' => reading_time((string) $post['content']),
                'views' => (int) $post['views'],
                'comments' => (int) $post['comment_count'],
                'featured' => (bool) $post['featured'],
                'published_at' => (string) ($post['published_at'] ?? $post['created_at']),
            ];
        }, $result['items']);

        return $this->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'last_page' => (int) max(1, ceil($result['total'] / $perPage)),
            ],
        ]);
    }

    public function post(string $slug): Response
    {
        $post = $this->posts()->findBySlug($slug);
        if ($post === null) {
            return $this->json(['error' => 'not_found', 'message' => 'No such article.'], 404);
        }

        $tags = $this->posts()->tagsFor((int) $post['id']);

        return $this->json([
            'data' => [
                'id' => (int) $post['id'],
                'title' => (string) $post['title'],
                'slug' => (string) $post['slug'],
                'url' => url('/posts/' . $post['slug']),
                'html' => body_html($post),
                'excerpt' => (string) ($post['excerpt'] ?: ''),
                'tags' => $tags,
                'category' => $post['category_name'] === null ? null : [
                    'name' => (string) $post['category_name'],
                    'slug' => (string) $post['category_slug'],
                ],
                'author' => ['name' => (string) ($post['author_name'] ?? '')],
                'published_at' => (string) ($post['published_at'] ?? $post['created_at']),
                'updated_at' => (string) $post['updated_at'],
                'views' => (int) $post['views'],
                'comments' => $this->comments()->countForPost((int) $post['id']),
                'reading_time' => reading_time((string) $post['content']),
            ],
        ]);
    }

    public function categories(): Response
    {
        return $this->json(['data' => array_map(static fn (array $category): array => [
            'id' => (int) $category['id'],
            'name' => (string) $category['name'],
            'slug' => (string) $category['slug'],
            'description' => $category['description'],
            'url' => url('/category/' . $category['slug']),
            'published_count' => (int) ($category['published_count'] ?? 0),
        ], $this->categories()->all())]);
    }

    public function tags(): Response
    {
        return $this->json(['data' => array_map(static fn (array $tag): array => [
            'id' => (int) $tag['id'],
            'name' => (string) $tag['name'],
            'slug' => (string) $tag['slug'],
            'url' => url('/tag/' . $tag['slug']),
        ], $this->tags()->popular(50))]);
    }

    public function comments(int $postId): Response
    {
        $post = $this->posts()->find($postId);
        if ($post === null) {
            return $this->json(['error' => 'not_found'], 404);
        }

        $flatten = static function (array $nodes) use (&$flatten): array {
            $flat = [];
            foreach ($nodes as $node) {
                $flat[] = [
                    'id' => (int) $node['id'],
                    'parent_id' => $node['parent_id'] === null ? null : (int) $node['parent_id'],
                    'author' => (string) $node['author_name'],
                    'body' => (string) $node['body'],
                    'created_at' => (string) $node['created_at'],
                ];
                $flat = array_merge($flat, $flatten($node['children'] ?? []));
            }

            return $flat;
        };

        return $this->json(['data' => $flatten($this->comments()->treeForPost($postId))]);
    }

    public function recordView(string $slug): Response
    {
        $post = $this->posts()->findBySlug($slug);
        if ($post === null) {
            return $this->json(['error' => 'not_found'], 404);
        }

        $this->posts()->incrementViews((int) $post['id']);
        $updated = $this->posts()->find((int) $post['id']);

        return $this->json(['data' => ['slug' => $slug, 'views' => (int) ($updated['views'] ?? 0)]]);
    }

    public function stats(): Response
    {
        $postCounts = $this->posts()->counts();
        $commentCounts = $this->comments()->counts();

        return $this->json([
            'data' => [
                'posts' => $postCounts['published'],
                'drafts' => $postCounts['draft'],
                'categories' => $this->categories()->count(),
                'tags' => $this->tags()->count(),
                'comments' => $commentCounts['approved'],
                'views' => $postCounts['views'],
            ],
        ]);
    }

    /** Health probe for uptime monitors. */
    public function health(): Response
    {
        try {
            $this->app->db()->scalar('SELECT 1');
            $healthy = true;
        } catch (\Throwable) {
            $healthy = false;
        }

        return $this->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'database' => $healthy ? 'up' : 'down',
            'time' => date('c'),
            'version' => $this->app->config('app.version', '1.0.0'),
        ], $healthy ? 200 : 503);
    }
}
