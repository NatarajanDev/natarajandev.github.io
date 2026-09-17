<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Paginator;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Contact-form inbox.
 */
final class MessageController extends Controller
{
    public function index(): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = max(5, (int) $this->app->config('content.admin_per_page', 15));

        $filters = ['status' => $request->string('status'), 'search' => $request->string('q')];
        $result = $this->messages()->paginate($filters, $page, $perPage);
        $paginator = new Paginator($result['items'], $result['total'], $perPage, $page, '/admin/messages', $request->query());

        $this->app->view()->setMeta(['title' => 'Inbox', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/messages/index', [
            'paginator' => $paginator,
            'messages' => $paginator->items(),
            'filters' => $filters,
            'counts' => $this->messages()->counts(),
        ]);
    }

    public function show(string $id): Response
    {
        $message = $this->messages()->find((int) $id);
        if ($message === null) {
            return $this->redirect('/admin/messages');
        }

        if ($message['status'] === 'new') {
            $this->messages()->setStatus((int) $message['id'], 'read');
            $message['status'] = 'read';
        }

        $this->app->view()->setMeta(['title' => 'Message: ' . $message['subject'], 'robots' => 'noindex,nofollow']);

        return $this->view('admin/messages/show', ['message' => $message]);
    }

    public function update(string $id): Response
    {
        $message = $this->messages()->find((int) $id);
        if ($message === null) {
            return $this->redirect('/admin/messages');
        }

        $status = Request::capture()->string('status', 'read');
        $this->messages()->setStatus((int) $message['id'], $status);
        audit()->record('message.status', 'message', (int) $message['id'], ['status' => $status]);
        $this->app->flash()->success('Message updated.');

        return $this->redirect('/admin/messages/' . $message['id']);
    }

    public function destroy(string $id): Response
    {
        $message = $this->messages()->find((int) $id);
        if ($message === null) {
            return $this->redirect('/admin/messages');
        }

        $this->messages()->delete((int) $message['id']);
        audit()->record('message.deleted', 'message', (int) $message['id']);
        $this->app->flash()->success('Message deleted.');

        return $this->redirect('/admin/messages');
    }
}
