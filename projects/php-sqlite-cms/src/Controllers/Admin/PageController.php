<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Paginator;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Static page management.
 */
final class PageController extends Controller
{
    public function index(): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = max(5, (int) $this->app->config('content.admin_per_page', 15));

        $filters = ['status' => $request->string('status'), 'search' => $request->string('q')];
        $result = $this->pages()->paginate($filters, $page, $perPage);
        $paginator = new Paginator($result['items'], $result['total'], $perPage, $page, '/admin/pages', $request->query());

        $this->app->view()->setMeta(['title' => 'Pages', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/pages/index', [
            'paginator' => $paginator,
            'pages' => $paginator->items(),
            'filters' => $filters,
            'counts' => $this->pages()->counts(),
        ]);
    }

    public function create(): Response
    {
        $this->app->view()->setMeta(['title' => 'New page', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/pages/form', [
            'page' => null,
            'parents' => array_values(array_filter(
                $this->pages()->all(),
                static fn (array $candidate): bool => $candidate['parent_id'] === null
            )),
        ]);
    }

    public function store(): Response
    {
        $request = Request::capture();
        $validator = $this->validate($request->all(), [
            'title' => 'required|min:2|max:200',
            'content' => 'required|min:5',
        ], ['title' => 'Title', 'content' => 'Body']);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/pages/create', $request->all());
        }

        $pageId = $this->pages()->create([
            'user_id' => (int) $this->app->auth()->id(),
            'parent_id' => $request->int('parent_id') ?: null,
            'title' => $request->string('title'),
            'slug' => $this->pages()->uniqueSlug($request->string('slug') !== '' ? $request->string('slug') : $request->string('title')),
            'content' => (string) $request->input('content', ''),
            'content_format' => $request->string('content_format', 'markdown'),
            'template' => $request->string('template', 'default'),
            'status' => $request->string('status', 'published'),
            'show_in_menu' => $request->bool('show_in_menu'),
            'sort_order' => $request->int('sort_order'),
            'seo_title' => $request->string('seo_title'),
            'seo_description' => $request->string('seo_description'),
        ]);

        $this->app->cache()->forget('menu.pages');
        $this->app->cache()->forget('sidebar.categories');
        audit()->record('page.created', 'page', $pageId, ['title' => $request->string('title')]);
        $this->app->flash()->success('Page created.');

        return $this->redirect('/admin/pages/' . $pageId . '/edit');
    }

    public function edit(string $id): Response
    {
        $page = $this->pages()->find((int) $id);
        if ($page === null) {
            return $this->redirect('/admin/pages');
        }

        $this->app->view()->setMeta(['title' => 'Edit: ' . $page['title'], 'robots' => 'noindex,nofollow']);

        return $this->view('admin/pages/form', [
            'page' => $page,
            'parents' => array_values(array_filter(
                $this->pages()->all(),
                static fn (array $candidate): bool => (int) $candidate['id'] !== (int) $page['id'] && $candidate['parent_id'] === null
            )),
        ]);
    }

    public function update(string $id): Response
    {
        $page = $this->pages()->find((int) $id);
        if ($page === null) {
            return $this->redirect('/admin/pages');
        }

        $request = Request::capture();
        $validator = $this->validate($request->all(), [
            'title' => 'required|min:2|max:200',
            'content' => 'required|min:5',
        ], ['title' => 'Title', 'content' => 'Body']);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/pages/' . $id . '/edit', $request->all());
        }

        $parentId = $request->int('parent_id') ?: null;
        if ($parentId === (int) $page['id']) {
            $parentId = null;
        }

        $this->pages()->update((int) $page['id'], [
            'parent_id' => $parentId,
            'title' => $request->string('title'),
            'slug' => $this->pages()->uniqueSlug(
                $request->string('slug') !== '' ? $request->string('slug') : $request->string('title'),
                (int) $page['id']
            ),
            'content' => (string) $request->input('content', ''),
            'content_format' => $request->string('content_format', 'markdown'),
            'template' => $request->string('template', 'default'),
            'status' => $request->string('status', 'published'),
            'show_in_menu' => $request->bool('show_in_menu'),
            'sort_order' => $request->int('sort_order'),
            'seo_title' => $request->string('seo_title'),
            'seo_description' => $request->string('seo_description'),
        ]);

        $this->app->cache()->forget('menu.pages');
        audit()->record('page.updated', 'page', (int) $page['id'], ['title' => $request->string('title')]);
        $this->app->flash()->success('Page updated.');

        return $this->redirect('/admin/pages/' . $page['id'] . '/edit');
    }

    public function destroy(string $id): Response
    {
        $page = $this->pages()->find((int) $id);
        if ($page === null) {
            return $this->redirect('/admin/pages');
        }

        $this->pages()->delete((int) $page['id']);
        $this->app->cache()->forget('menu.pages');
        audit()->record('page.deleted', 'page', (int) $page['id'], ['title' => $page['title']]);
        $this->app->flash()->success('Page deleted.');

        return $this->redirect('/admin/pages');
    }

    public function bulk(): Response
    {
        $request = Request::capture();
        $ids = array_map('intval', (array) $request->input('ids', []));
        $action = $request->string('action');

        if ($ids === [] || $action === '') {
            $this->app->flash()->warning('Select at least one page.');
            return $this->redirect('/admin/pages');
        }

        if ($action === 'delete') {
            $affected = $this->pages()->deleteMany($ids);
        } else {
            $status = $action === 'publish' ? 'published' : 'draft';
            $affected = 0;
            foreach ($ids as $id) {
                $this->pages()->update($id, ['status' => $status]);
                $affected++;
            }
        }

        $this->app->cache()->forget('menu.pages');
        audit()->record('page.bulk', 'page', null, ['action' => $action, 'ids' => $ids]);
        $this->app->flash()->success(sprintf('%d page%s updated.', $affected, $affected === 1 ? '' : 's'));

        return $this->redirect('/admin/pages');
    }
}
