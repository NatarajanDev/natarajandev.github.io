<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Categories and tags.
 */
final class TaxonomyController extends Controller
{
    // ------------------------------------------------------------- categories

    public function categories(): Response
    {
        $this->app->view()->setMeta(['title' => 'Categories', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/taxonomy/categories', [
            'categories' => $this->categories()->all(),
            'tree' => $this->categories()->tree(),
        ]);
    }

    public function storeCategory(): Response
    {
        $request = Request::capture();
        $validator = $this->validate($request->all(), [
            'name' => 'required|min:2|max:120',
        ], ['name' => 'Name']);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/categories', $request->all());
        }

        $id = $this->categories()->create([
            'name' => $request->string('name'),
            'slug' => $this->categories()->uniqueSlug($request->string('slug') !== '' ? $request->string('slug') : $request->string('name')),
            'parent_id' => $request->int('parent_id') ?: null,
            'description' => $request->string('description'),
            'color' => $request->string('color', '#6366f1'),
            'sort_order' => $request->int('sort_order'),
        ]);

        $this->app->cache()->forget('sidebar.categories');
        audit()->record('category.created', 'category', $id, ['name' => $request->string('name')]);
        $this->app->flash()->success('Category created.');

        return $this->redirect('/admin/categories');
    }

    public function updateCategory(string $id): Response
    {
        $category = $this->categories()->find((int) $id);
        if ($category === null) {
            return $this->redirect('/admin/categories');
        }

        $request = Request::capture();
        $validator = $this->validate($request->all(), ['name' => 'required|min:2|max:120'], ['name' => 'Name']);
        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/categories', $request->all());
        }

        $parentId = $request->int('parent_id') ?: null;
        if ($parentId === (int) $category['id']) {
            $parentId = null;
        }

        $this->categories()->update((int) $category['id'], [
            'name' => $request->string('name'),
            'slug' => $this->categories()->uniqueSlug(
                $request->string('slug') !== '' ? $request->string('slug') : $request->string('name'),
                (int) $category['id']
            ),
            'parent_id' => $parentId,
            'description' => $request->string('description'),
            'color' => $request->string('color', '#6366f1'),
            'sort_order' => $request->int('sort_order'),
        ]);

        $this->app->cache()->forget('sidebar.categories');
        audit()->record('category.updated', 'category', (int) $category['id'], ['name' => $request->string('name')]);
        $this->app->flash()->success('Category updated.');

        return $this->redirect('/admin/categories');
    }

    public function destroyCategory(string $id): Response
    {
        $category = $this->categories()->find((int) $id);
        if ($category === null) {
            return $this->redirect('/admin/categories');
        }

        $this->categories()->delete((int) $category['id']);
        $this->app->cache()->forget('sidebar.categories');
        audit()->record('category.deleted', 'category', (int) $category['id'], ['name' => $category['name']]);
        $this->app->flash()->success('Category deleted. Its posts are now uncategorised.');

        return $this->redirect('/admin/categories');
    }

    // ------------------------------------------------------------------- tags

    public function tags(): Response
    {
        $this->app->view()->setMeta(['title' => 'Tags', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/taxonomy/tags', [
            'tags' => $this->tags()->all(),
            'popular' => $this->tags()->popular(12),
        ]);
    }

    public function storeTag(): Response
    {
        $request = Request::capture();
        $validator = $this->validate($request->all(), ['name' => 'required|min:1|max:60'], ['name' => 'Name']);
        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/tags', $request->all());
        }

        $slug = str_slug($request->string('name'));
        if ($this->tags()->findBySlug($slug) !== null) {
            $this->app->flash()->warning('That tag already exists.');
            return $this->redirect('/admin/tags');
        }

        $id = $this->tags()->create($request->string('name'));
        $this->app->cache()->forget('sidebar.tags');
        audit()->record('tag.created', 'tag', $id, ['name' => $request->string('name')]);
        $this->app->flash()->success('Tag created.');

        return $this->redirect('/admin/tags');
    }

    public function updateTag(string $id): Response
    {
        $tag = $this->tags()->find((int) $id);
        if ($tag === null) {
            return $this->redirect('/admin/tags');
        }

        $request = Request::capture();
        if ($request->string('name') === '') {
            $this->app->flash()->error('A tag needs a name.');
            return $this->redirect('/admin/tags');
        }

        $this->tags()->update((int) $tag['id'], $request->string('name'));
        $this->app->cache()->forget('sidebar.tags');
        audit()->record('tag.updated', 'tag', (int) $tag['id'], ['name' => $request->string('name')]);
        $this->app->flash()->success('Tag updated.');

        return $this->redirect('/admin/tags');
    }

    public function mergeTag(string $id): Response
    {
        $request = Request::capture();
        $targetId = $request->int('target_id');

        if ($targetId === 0 || $this->tags()->find((int) $id) === null || $this->tags()->find($targetId) === null) {
            $this->app->flash()->error('Choose a valid tag to merge into.');
            return $this->redirect('/admin/tags');
        }

        $this->tags()->merge((int) $id, $targetId);
        $this->app->cache()->forget('sidebar.tags');
        audit()->record('tag.merged', 'tag', (int) $id, ['target' => $targetId]);
        $this->app->flash()->success('Tags merged.');

        return $this->redirect('/admin/tags');
    }

    public function destroyTag(string $id): Response
    {
        $tag = $this->tags()->find((int) $id);
        if ($tag === null) {
            return $this->redirect('/admin/tags');
        }

        $this->tags()->delete((int) $tag['id']);
        $this->app->cache()->forget('sidebar.tags');
        audit()->record('tag.deleted', 'tag', (int) $tag['id'], ['name' => $tag['name']]);
        $this->app->flash()->success('Tag deleted.');

        return $this->redirect('/admin/tags');
    }
}
