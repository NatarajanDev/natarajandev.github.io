<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Paginator;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Post management: listing with filters, editor, revisions, bulk actions.
 */
final class PostController extends Controller
{
    private const STATUSES = ['draft', 'published', 'scheduled', 'archived'];

    public function index(): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = max(5, (int) $this->app->config('content.admin_per_page', 15));

        $filters = [
            'status' => $request->string('status'),
            'category_id' => $request->int('category') ?: null,
            'user_id' => $request->int('author') ?: null,
            'search' => $request->string('q'),
        ];

        // Authors only ever see their own posts.
        if (!$this->app->auth()->canModerate()) {
            $filters['user_id'] = $this->app->auth()->id();
        }

        $result = $this->posts()->paginate($filters, $page, $perPage, 'p.updated_at DESC');
        $paginator = new Paginator($result['items'], $result['total'], $perPage, $page, '/admin/posts', $request->query());

        $this->app->view()->setMeta(['title' => 'Posts', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/posts/index', [
            'paginator' => $paginator,
            'posts' => $paginator->items(),
            'tags' => $this->posts()->tagsForMany(array_map(static fn (array $p): int => (int) $p['id'], $paginator->items())),
            'categories' => $this->categories()->all(),
            'authors' => $this->users()->all(),
            'filters' => $filters,
            'counts' => $this->posts()->counts($this->app->auth()->canModerate() ? null : $this->app->auth()->id()),
        ]);
    }

    public function create(): Response
    {
        $this->app->view()->setMeta(['title' => 'New post', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/posts/form', [
            'post' => null,
            'tags' => [],
            'categories' => $this->categories()->all(),
            'statuses' => self::STATUSES,
            'recentMedia' => $this->media()->recent(8),
            'revisions' => [],
        ]);
    }

    public function store(): Response
    {
        $request = Request::capture();

        if (!$this->app->auth()->canModerate() && $this->app->auth()->hasRole('author')) {
            $authorId = (int) $this->app->auth()->id();
        } else {
            $authorId = $request->int('user_id') ?: (int) $this->app->auth()->id();
        }

        $validator = $this->validate($request->all(), [
            'title' => 'required|min:3|max:200',
            'status' => 'in:draft,published,scheduled,archived',
            'content' => 'required|min:10',
        ], ['title' => 'Title', 'content' => 'Body']);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/posts/create', $request->all());
        }

        $status = $request->string('status', 'draft');
        $publishedAt = $this->resolvePublishedAt($request, $status);
        $slug = $request->string('slug') !== '' ? str_slug($request->string('slug')) : $request->string('title');
        $postId = $this->posts()->create([
            'user_id' => $authorId,
            'category_id' => $request->int('category_id') ?: null,
            'title' => $request->string('title'),
            'slug' => $this->posts()->uniqueSlug($slug),
            'excerpt' => $request->string('excerpt'),
            'content' => (string) $request->input('content', ''),
            'content_format' => $request->string('content_format', 'markdown'),
            'cover_image' => $request->string('cover_image'),
            'status' => $status,
            'featured' => $request->bool('featured'),
            'allow_comments' => $request->bool('allow_comments', true),
            'seo_title' => $request->string('seo_title'),
            'seo_description' => $request->string('seo_description'),
            'og_image' => $request->string('og_image'),
            'canonical_url' => $request->string('canonical_url'),
            'published_at' => $publishedAt,
        ]);

        $this->syncTags($postId, $request->string('tags'));
        $this->storeRevision($postId);
        $this->flushContentCache();

        audit()->record('post.created', 'post', $postId, ['title' => $request->string('title'), 'status' => $status]);
        $this->app->flash()->success('Post created.');

        return $this->redirect('/admin/posts/' . $postId . '/edit');
    }

    public function edit(string $id): Response
    {
        $post = $this->posts()->find((int) $id);
        if ($post === null) {
            return $this->redirect('/admin/posts');
        }

        if (($guard = $this->guardOwnership($post)) !== null) {
            return $guard;
        }

        $this->app->view()->setMeta(['title' => 'Edit: ' . $post['title'], 'robots' => 'noindex,nofollow']);

        return $this->view('admin/posts/form', [
            'post' => $post,
            'tags' => $this->posts()->tagsFor((int) $post['id']),
            'categories' => $this->categories()->all(),
            'statuses' => self::STATUSES,
            'recentMedia' => $this->media()->recent(8),
            'revisions' => $this->posts()->revisions((int) $post['id'], 8),
        ]);
    }

    public function update(string $id): Response
    {
        $post = $this->posts()->find((int) $id);
        if ($post === null) {
            return $this->redirect('/admin/posts');
        }

        if (($guard = $this->guardOwnership($post)) !== null) {
            return $guard;
        }

        $request = Request::capture();

        $validator = $this->validate($request->all(), [
            'title' => 'required|min:3|max:200',
            'status' => 'in:draft,published,scheduled,archived',
            'content' => 'required|min:10',
        ], ['title' => 'Title', 'content' => 'Body']);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/posts/' . $id . '/edit', $request->all());
        }

        $status = $request->string('status', 'draft');
        $slugInput = $request->string('slug') !== '' ? $request->string('slug') : $request->string('title');

        $this->posts()->addRevision($post, $this->app->auth()->id());

        $this->posts()->update((int) $post['id'], [
            'category_id' => $request->int('category_id') ?: null,
            'title' => $request->string('title'),
            'slug' => $this->posts()->uniqueSlug(str_slug($slugInput), (int) $post['id']),
            'excerpt' => $request->string('excerpt'),
            'content' => (string) $request->input('content', ''),
            'content_format' => $request->string('content_format', 'markdown'),
            'cover_image' => $request->string('cover_image'),
            'status' => $status,
            'featured' => $request->bool('featured'),
            'allow_comments' => $request->bool('allow_comments'),
            'seo_title' => $request->string('seo_title'),
            'seo_description' => $request->string('seo_description'),
            'og_image' => $request->string('og_image'),
            'canonical_url' => $request->string('canonical_url'),
            'published_at' => $this->resolvePublishedAt($request, $status, $post),
        ]);

        $this->syncTags((int) $post['id'], $request->string('tags'));
        $this->flushContentCache();

        audit()->record('post.updated', 'post', (int) $post['id'], ['title' => $request->string('title')]);
        $this->app->flash()->success('Post updated.');

        return $this->redirect('/admin/posts/' . $post['id'] . '/edit');
    }

    public function destroy(string $id): Response
    {
        $post = $this->posts()->find((int) $id);
        if ($post === null) {
            return $this->redirect('/admin/posts');
        }

        if (($guard = $this->guardOwnership($post)) !== null) {
            return $guard;
        }

        $this->posts()->delete((int) $post['id']);
        $this->flushContentCache();

        audit()->record('post.deleted', 'post', (int) $post['id'], ['title' => $post['title']]);
        $this->app->flash()->success('Post deleted.');

        return $this->redirect('/admin/posts');
    }

    public function bulk(): Response
    {
        $request = Request::capture();
        $ids = array_map('intval', (array) $request->input('ids', []));
        $action = $request->string('action');

        if ($ids === [] || $action === '') {
            $this->app->flash()->warning('Select at least one post first.');
            return $this->redirect('/admin/posts');
        }

        if (!$this->app->auth()->canModerate()) {
            $this->app->flash()->error('Only editors and administrators can run bulk actions.');
            return $this->redirect('/admin/posts');
        }

        $affected = match ($action) {
            'delete' => $this->posts()->deleteMany($ids),
            'publish' => $this->posts()->setStatus($ids, 'published'),
            'draft' => $this->posts()->setStatus($ids, 'draft'),
            'archive' => $this->posts()->setStatus($ids, 'archived'),
            'feature' => $this->posts()->setFeatured($ids, true),
            'unfeature' => $this->posts()->setFeatured($ids, false),
            default => 0,
        };

        $this->flushContentCache();
        audit()->record('post.bulk', 'post', null, ['action' => $action, 'ids' => $ids, 'affected' => $affected]);
        $this->app->flash()->success(sprintf('%d post%s updated.', $affected, $affected === 1 ? '' : 's'));

        return $this->redirect('/admin/posts');
    }

    public function revisions(string $id): Response
    {
        $post = $this->posts()->find((int) $id);
        if ($post === null) {
            return $this->redirect('/admin/posts');
        }

        $this->app->view()->setMeta(['title' => 'Revisions: ' . $post['title'], 'robots' => 'noindex,nofollow']);

        return $this->view('admin/posts/revisions', [
            'post' => $post,
            'revisions' => $this->posts()->revisions((int) $post['id'], 30),
        ]);
    }

    public function restoreRevision(string $id, string $revisionId): Response
    {
        $post = $this->posts()->find((int) $id);
        $revision = $post === null ? null : $this->posts()->findRevision((int) $revisionId, (int) $post['id']);

        if ($post === null || $revision === null) {
            $this->app->flash()->error('That revision is no longer available.');
            return $this->redirect('/admin/posts');
        }

        if (($guard = $this->guardOwnership($post)) !== null) {
            return $guard;
        }

        $this->posts()->addRevision($post, $this->app->auth()->id());
        $this->posts()->update((int) $post['id'], [
            'title' => (string) $revision['title'],
            'content' => (string) $revision['content'],
            'status' => (string) $revision['status'],
        ]);

        audit()->record('post.revision_restored', 'post', (int) $post['id'], ['revision' => (int) $revision['id']]);
        $this->app->flash()->success('Revision restored.');
        $this->flushContentCache();

        return $this->redirect('/admin/posts/' . $post['id'] . '/edit');
    }

    private function resolvePublishedAt(Request $request, string $status, ?array $post = null): ?string
    {
        $input = $request->string('published_at');
        if ($input !== '') {
            $timestamp = strtotime($input);

            return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
        }

        if ($status === 'published') {
            return $post['published_at'] ?? null ?: date('Y-m-d H:i:s');
        }

        return $post['published_at'] ?? null;
    }

    private function syncTags(int $postId, string $tagString): void
    {
        $names = array_filter(array_map('trim', explode(',', $tagString)));
        $ids = $this->tags()->resolveNames(array_values($names));
        $this->posts()->syncTags($postId, $ids);
    }

    private function storeRevision(int $postId): void
    {
        $fresh = $this->posts()->find($postId);
        if ($fresh !== null) {
            $this->posts()->addRevision($fresh, $this->app->auth()->id());
        }
    }

    /** Authors may only touch their own posts; returns a redirect when denied. */
    private function guardOwnership(array $post): ?Response
    {
        if ($this->app->auth()->canModerate()) {
            return null;
        }

        if ((int) $post['user_id'] !== (int) $this->app->auth()->id()) {
            $this->app->flash()->error('You can only manage your own posts.');

            return $this->redirect('/admin/posts');
        }

        return null;
    }

    private function flushContentCache(): void
    {
        foreach (['sidebar.popular', 'sidebar.tags', 'sidebar.archive', 'sidebar.categories', 'menu.pages', 'sitemap'] as $key) {
            $this->app->cache()->forget($key);
        }
    }
}
