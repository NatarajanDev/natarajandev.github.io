<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Paginator;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Media library: uploads, metadata editing, deletion and the JSON picker feed.
 */
final class MediaController extends Controller
{
    public function index(): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = 24;

        $filters = ['type' => $request->string('type'), 'search' => $request->string('q')];
        $result = $this->media()->paginate($filters, $page, $perPage);
        $paginator = new Paginator($result['items'], $result['total'], $perPage, $page, '/admin/media', $request->query());

        $this->app->view()->setMeta(['title' => 'Media library', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/media/index', [
            'paginator' => $paginator,
            'items' => $paginator->items(),
            'filters' => $filters,
            'stats' => $this->media()->stats(),
            'maxUpload' => (int) $this->app->config('uploads.max_bytes', 5242880),
        ]);
    }

    public function upload(): Response
    {
        $request = Request::capture();
        $file = $request->file('file');

        if ($file === null) {
            if ($request->wantsJson()) {
                return $this->json(['ok' => false, 'error' => 'No file received.'], 422);
            }
            $this->app->flash()->error('Choose a file to upload first.');

            return $this->redirect('/admin/media');
        }

        $result = $this->app->uploader()->store($file);

        if (!$result['ok']) {
            if ($request->wantsJson()) {
                return $this->json(['ok' => false, 'error' => $result['error']], 422);
            }
            $this->app->flash()->error((string) $result['error']);

            return $this->redirect('/admin/media');
        }

        $media = $result['media'];
        $media['user_id'] = (int) $this->app->auth()->id();
        $id = $this->media()->create($media);

        audit()->record('media.uploaded', 'media', $id, ['filename' => $media['filename']]);

        if ($request->wantsJson()) {
            return $this->json([
                'ok' => true,
                'media' => [
                    'id' => $id,
                    'url' => url('/' . $media['path']),
                    'thumb' => url('/' . ($media['thumb_path'] ?? $media['path'])),
                    'alt' => $media['alt_text'],
                    'filename' => $media['filename'],
                ],
            ]);
        }

        $this->app->flash()->success('“' . $media['original_name'] . '” uploaded.');

        return $this->redirect('/admin/media');
    }

    public function update(string $id): Response
    {
        $item = $this->media()->find((int) $id);
        if ($item === null) {
            return $this->redirect('/admin/media');
        }

        $request = Request::capture();
        $this->media()->update((int) $item['id'], [
            'alt_text' => $request->string('alt_text'),
            'caption' => $request->string('caption'),
        ]);

        audit()->record('media.updated', 'media', (int) $item['id']);
        $this->app->flash()->success('Media details saved.');

        return $this->redirect('/admin/media');
    }

    public function destroy(string $id): Response
    {
        $item = $this->media()->find((int) $id);
        if ($item === null) {
            return $this->redirect('/admin/media');
        }

        if (!$this->app->auth()->canModerate()) {
            $this->app->flash()->error('Only editors and administrators can delete media.');
            return $this->redirect('/admin/media');
        }

        $this->app->uploader()->delete((string) $item['path']);
        if (!empty($item['thumb_path'])) {
            $this->app->uploader()->delete((string) $item['thumb_path']);
        }

        $this->media()->delete((int) $item['id']);
        audit()->record('media.deleted', 'media', (int) $item['id'], ['filename' => $item['filename']]);
        $this->app->flash()->success('Media deleted.');

        return $this->redirect('/admin/media');
    }

    /** JSON feed used by the media picker modal in the editors. */
    public function picker(): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = 24;
        $result = $this->media()->paginate(
            ['type' => 'image', 'search' => $request->string('q')],
            $page,
            $perPage
        );

        return $this->json([
            'items' => array_map(static fn (array $item): array => [
                'id' => (int) $item['id'],
                'url' => url('/' . $item['path']),
                'thumb' => url('/' . ($item['thumb_path'] ?? $item['path'])),
                'alt' => (string) ($item['alt_text'] ?? ''),
                'name' => (string) $item['original_name'],
                'size' => human_bytes((int) $item['size']),
                'width' => $item['width'] === null ? null : (int) $item['width'],
                'height' => $item['height'] === null ? null : (int) $item['height'],
            ], $result['items']),
            'page' => $page,
            'total' => $result['total'],
            'last_page' => (int) max(1, ceil($result['total'] / $perPage)),
        ]);
    }
}
