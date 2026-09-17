<?php
/**
 * Post list with filters and bulk actions.
 *
 * @var \Cms\Support\Paginator $paginator
 * @var array<int, array<string, mixed>> $posts
 * @var array<int, array<int, array<string, mixed>>> $tags
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, array<string, mixed>> $authors
 * @var array<string, mixed> $filters
 * @var array<string, int> $counts
 */
$statusTabs = [
    '' => ['All', $counts['total']],
    'published' => ['Published', $counts['published']],
    'draft' => ['Drafts', $counts['draft']],
    'scheduled' => ['Scheduled', $counts['scheduled']],
];
?>
<section class="block">
    <div class="tabs">
        <?php foreach ($statusTabs as $value => [$label, $total]): ?>
            <a class="tabs__item<?= (string) $filters['status'] === (string) $value ? ' is-active' : '' ?>"
               href="<?= e(url('/admin/posts' . ($value === '' ? '' : '?status=' . $value))) ?>">
                <?= e($label) ?> <em><?= (int) $total ?></em>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="filter-bar" method="get" action="<?= e(url('/admin/posts')) ?>">
        <?php if ($filters['status'] !== ''): ?>
            <input type="hidden" name="status" value="<?= e((string) $filters['status']) ?>">
        <?php endif; ?>
        <label class="filter-bar__search">
            <?= icon('search', 16) ?>
            <input type="search" name="q" value="<?= e((string) $filters['search']) ?>" placeholder="Search posts…">
        </label>
        <select name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= (int) $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= e((string) $category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (\Cms\App::instance()->auth()->canModerate()): ?>
            <select name="author">
                <option value="">All authors</option>
                <?php foreach ($authors as $author): ?>
                    <option value="<?= (int) $author['id'] ?>" <?= (int) $filters['user_id'] === (int) $author['id'] ? 'selected' : '' ?>>
                        <?= e((string) $author['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button class="btn btn--ghost btn--sm" type="submit">Filter</button>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/posts')) ?>">Reset</a>
    </form>

    <form method="post" action="<?= e(url('/admin/posts/bulk')) ?>" data-bulk-form>
        <?= csrf_field() ?>

        <div class="bulk-bar">
            <span class="bulk-bar__count"><strong data-bulk-count>0</strong> selected</span>
            <select name="action" required>
                <option value="">Bulk action…</option>
                <option value="publish">Publish</option>
                <option value="draft">Move to drafts</option>
                <option value="archive">Archive</option>
                <option value="feature">Mark as featured</option>
                <option value="unfeature">Remove featured</option>
                <option value="delete">Delete permanently</option>
            </select>
            <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Apply this action to the selected posts?">Apply</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="table__check"><input type="checkbox" data-check-all aria-label="Select all posts"></th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Author</th>
                        <th class="table__num">Views</th>
                        <th class="table__num">Comments</th>
                        <th>Updated</th>
                        <th class="table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td class="table__check">
                            <input type="checkbox" name="ids[]" value="<?= (int) $post['id'] ?>" data-check-item aria-label="Select <?= e((string) $post['title']) ?>">
                        </td>
                        <td>
                            <a class="table__title" href="<?= e(url('/admin/posts/' . $post['id'] . '/edit')) ?>">
                                <?= e((string) $post['title']) ?>
                                <?php if ((int) $post['featured'] === 1): ?>
                                    <span class="badge badge--info">Featured</span>
                                <?php endif; ?>
                            </a>
                            <div class="table__meta">
                                <code>/posts/<?= e((string) $post['slug']) ?></code>
                                <?php foreach (array_slice($tags[(int) $post['id']] ?? [], 0, 3) as $tag): ?>
                                    <span class="chip chip--xs">#<?= e((string) $tag['name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><?= $post['category_name'] === null ? '<span class="muted">—</span>' : e((string) $post['category_name']) ?></td>
                        <td><?= status_badge((string) $post['status']) ?></td>
                        <td><?= e((string) ($post['author_name'] ?? '—')) ?></td>
                        <td class="table__num"><?= (int) $post['views'] ?></td>
                        <td class="table__num"><?= (int) $post['comment_count'] ?></td>
                        <td class="muted"><?= e(time_ago((string) $post['updated_at'])) ?></td>
                        <td class="table__actions">
                            <div class="table__actions-inner">
                                <a class="btn btn--ghost btn--icon" href="<?= e(url('/posts/' . $post['slug'])) ?>" target="_blank" rel="noopener" title="View"><?= icon('eye', 16) ?></a>
                                <a class="btn btn--ghost btn--icon" href="<?= e(url('/admin/posts/' . $post['id'] . '/edit')) ?>" title="Edit"><?= icon('edit', 16) ?></a>
                                <a class="btn btn--ghost btn--icon" href="<?= e(url('/admin/posts/' . $post['id'] . '/revisions')) ?>" title="Revisions"><?= icon('clock', 16) ?></a>
                                <button class="btn btn--ghost btn--icon btn--danger" type="submit" title="Delete"
                                        formaction="<?= e(url('/admin/posts/' . $post['id'])) ?>"
                                        formmethod="post"
                                        name="_method" value="DELETE"
                                        data-confirm="Delete “<?= e((string) $post['title']) ?>” permanently?"><?= icon('trash', 16) ?></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($posts === []): ?>
                    <tr><td colspan="9" class="table__empty">No posts match these filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <?= $view->partial('partials/pagination', ['paginator' => $paginator]) ?>
</section>
