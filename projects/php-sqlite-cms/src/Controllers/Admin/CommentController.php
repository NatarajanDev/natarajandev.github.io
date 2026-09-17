<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Paginator;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Comment moderation queue.
 */
final class CommentController extends Controller
{
    public function index(): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = max(5, (int) $this->app->config('content.admin_per_page', 15));

        $filters = [
            'status' => $request->string('status', 'pending'),
            'search' => $request->string('q'),
        ];
        if ($filters['status'] === 'all') {
            $filters['status'] = '';
        }

        $result = $this->comments()->paginate($filters, $page, $perPage);
        $paginator = new Paginator($result['items'], $result['total'], $perPage, $page, '/admin/comments', $request->query());

        $this->app->view()->setMeta(['title' => 'Comments', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/comments/index', [
            'paginator' => $paginator,
            'comments' => $paginator->items(),
            'filters' => $filters,
            'counts' => $this->comments()->counts(),
        ]);
    }

    public function approve(string $id): Response
    {
        return $this->setStatus((int) $id, 'approved', 'Comment approved.');
    }

    public function unapprove(string $id): Response
    {
        return $this->setStatus((int) $id, 'pending', 'Comment moved back to pending.');
    }

    public function spam(string $id): Response
    {
        return $this->setStatus((int) $id, 'spam', 'Comment marked as spam.');
    }

    public function trash(string $id): Response
    {
        return $this->setStatus((int) $id, 'trash', 'Comment moved to trash.');
    }

    public function destroy(string $id): Response
    {
        $comment = $this->comments()->find((int) $id);
        if ($comment !== null) {
            $this->comments()->delete((int) $comment['id']);
            audit()->record('comment.deleted', 'comment', (int) $comment['id'], ['author' => $comment['author_name']]);
            $this->app->flash()->success('Comment deleted.');
        }

        return $this->redirect('/admin/comments' . $this->returnQuery());
    }

    public function bulk(): Response
    {
        $request = Request::capture();
        $ids = array_map('intval', (array) $request->input('ids', []));
        $action = $request->string('action');

        if ($ids === [] || $action === '') {
            $this->app->flash()->warning('Select at least one comment.');
            return $this->redirect('/admin/comments');
        }

        $affected = match ($action) {
            'approve' => $this->comments()->setStatusMany($ids, 'approved'),
            'pending' => $this->comments()->setStatusMany($ids, 'pending'),
            'spam' => $this->comments()->setStatusMany($ids, 'spam'),
            'trash' => $this->comments()->setStatusMany($ids, 'trash'),
            'delete' => $this->comments()->deleteMany($ids),
            default => 0,
        };

        audit()->record('comment.bulk', 'comment', null, ['action' => $action, 'ids' => $ids, 'affected' => $affected]);
        $this->app->flash()->success(sprintf('%d comment%s updated.', $affected, $affected === 1 ? '' : 's'));

        return $this->redirect('/admin/comments' . $this->returnQuery());
    }

    /** Editor reply posted directly from the moderation queue. */
    public function reply(string $id): Response
    {
        $comment = $this->comments()->find((int) $id);
        if ($comment === null) {
            return $this->redirect('/admin/comments');
        }

        $request = Request::capture();
        $body = trim($request->string('body'));
        if (mb_strlen($body) < 3) {
            $this->app->flash()->error('Write a short reply first.');
            return $this->redirect('/admin/comments');
        }

        $user = $this->app->auth()->user();
        $this->comments()->create([
            'post_id' => (int) $comment['post_id'],
            'parent_id' => (int) $comment['id'],
            'author_name' => (string) ($user['name'] ?? 'Editor'),
            'author_email' => (string) ($user['email'] ?? 'editor@example.com'),
            'body' => $body,
            'status' => 'approved',
            'ip' => $request->ip(),
            'user_agent' => 'admin-reply',
        ]);

        audit()->record('comment.replied', 'comment', (int) $comment['id']);
        $this->app->flash()->success('Reply published.');

        return $this->redirect('/admin/comments' . $this->returnQuery());
    }

    private function setStatus(int $id, string $status, string $message): Response
    {
        $comment = $this->comments()->find($id);
        if ($comment !== null) {
            $this->comments()->setStatus($id, $status);
            audit()->record('comment.' . $status, 'comment', $id, ['post_id' => (int) $comment['post_id']]);
            $this->app->flash()->success($message);
        }

        return $this->redirect('/admin/comments' . $this->returnQuery());
    }

    private function returnQuery(): string
    {
        $status = Request::capture()->string('return_status');

        return $status === '' ? '' : '?status=' . urlencode($status);
    }
}
