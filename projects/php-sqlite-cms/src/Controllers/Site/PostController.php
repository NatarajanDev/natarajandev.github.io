<?php

declare(strict_types=1);

namespace Cms\Controllers\Site;

use Cms\Controllers\Controller;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Single article view + public comment posting.
 */
final class PostController extends Controller
{
    public function show(string $slug): Response
    {
        $post = $this->posts()->findBySlug($slug);
        if ($post === null) {
            return $this->notFound();
        }

        $this->recordView($post);

        $perPage = max(3, (int) $this->settings()->get('posts_per_page', '6'));
        $request = Request::capture();

        $this->app->view()->setMeta([
            'title' => (string) ($post['seo_title'] ?: $post['title']),
            'description' => (string) ($post['seo_description'] ?: $post['excerpt'] ?: excerpt((string) $post['content'], 160)),
            'canonical' => (string) ($post['canonical_url'] ?: absolute_url('/posts/' . $post['slug'])),
            'type' => 'article',
            'image' => $post['og_image'] ?: $post['cover_image'],
            'published_time' => (string) ($post['published_at'] ?? $post['created_at']),
        ]);

        return $this->view('site/posts/show', array_merge($this->sidebarData(), [
            'post' => $post,
            'tags' => $this->posts()->tagsFor((int) $post['id']),
            'adjacent' => $this->posts()->adjacent($post),
            'related' => $this->posts()->related($post, 3),
            'comments' => $this->comments()->treeForPost((int) $post['id']),
            'commentCount' => $this->comments()->countForPost((int) $post['id']),
            'totalComments' => $this->comments()->counts()['approved'],
            'allowComments' => (bool) $post['allow_comments'] && $this->settings()->bool('comments_enabled', true),
            'perPage' => $perPage,
            'formStartedAt' => $this->commentFormTimestamp($request),
        ]));
    }

    public function comment(string $slug): Response
    {
        $request = Request::capture();
        $post = $this->posts()->findBySlug($slug);

        if ($post === null) {
            return $this->notFound();
        }

        if (!$this->settings()->bool('comments_enabled', true) || !(bool) $post['allow_comments']) {
            $this->app->flash()->error('Comments are closed on this article.');
            return $this->redirect('/posts/' . $post['slug']);
        }

        $redirect = '/posts/' . $post['slug'] . '#comments';
        $input = $request->all();

        // --- spam guards -------------------------------------------------
        if ($request->string('website') !== '') {
            // Honeypot field: pretend everything went fine for the bot.
            return $this->redirect($redirect);
        }

        $startedAt = (int) $request->input('started_at', 0);
        if ($startedAt > 0 && (time() - $startedAt) < 2) {
            $this->app->flash()->error('That was suspiciously fast — please try again.');
            return $this->redirect($redirect);
        }

        if ($this->comments()->recentCountByIp($request->ip(), 5) >= 4) {
            $this->app->flash()->error('Too many comments from your connection. Please try again later.');
            return $this->redirect($redirect);
        }

        $validator = $this->validate($input, [
            'author_name' => 'required|min:2|max:80',
            'author_email' => 'required|email|max:190',
            'author_url' => 'url|max:190',
            'body' => 'required|min:8|max:4000',
        ], [
            'author_name' => 'Name',
            'author_email' => 'Email',
            'author_url' => 'Website',
            'body' => 'Comment',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator, $redirect, $input);
        }

        if ($this->comments()->isDuplicate((int) $post['id'], $request->string('author_email'), $request->string('body'))) {
            $this->app->flash()->info('You already posted that comment.');
            return $this->redirect($redirect);
        }

        $parentId = $request->int('parent_id') > 0 ? $request->int('parent_id') : null;
        if ($parentId !== null && $this->comments()->find($parentId) === null) {
            $parentId = null;
        }

        $moderate = $this->settings()->bool('comment_moderation', true) && !$this->app->auth()->canModerate();
        $status = $moderate ? 'pending' : 'approved';

        $this->comments()->create([
            'post_id' => (int) $post['id'],
            'parent_id' => $parentId,
            'author_name' => $request->string('author_name'),
            'author_email' => $request->string('author_email'),
            'author_url' => $request->string('author_url'),
            'body' => $request->string('body'),
            'status' => $status,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->app->cache()->forget('sidebar.popular');
        audit()->record('comment.posted', 'post', (int) $post['id'], ['status' => $status]);

        $this->app->flash()->success(
            $status === 'approved'
                ? 'Thanks! Your comment is live.'
                : 'Thanks! Your comment is waiting for moderation.'
        );

        return $this->redirect($redirect);
    }

    private function notFound(): Response
    {
        return new Response($this->app->view()->render('errors/404', [
            'meta' => ['title' => 'Article not found'],
        ]), 404);
    }

    /** Count one view per session per post (and per day in post_views). */
    private function recordView(array $post): void
    {
        $key = 'viewed_posts';
        $seen = $_SESSION[$key] ?? [];
        $postId = (int) $post['id'];

        if (in_array($postId, $seen, true)) {
            return;
        }

        $seen[] = $postId;
        $_SESSION[$key] = array_slice($seen, -50);
        $this->posts()->incrementViews($postId);
    }

    private function commentFormTimestamp(Request $request): int
    {
        $timestamp = (int) ($_SESSION['comment_form_started'] ?? 0);
        if ($timestamp === 0 || (time() - $timestamp) > 7200) {
            $timestamp = time();
            $_SESSION['comment_form_started'] = $timestamp;
        }

        return $timestamp;
    }
}
